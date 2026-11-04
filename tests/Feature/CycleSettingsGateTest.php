<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\Setting;
use App\Models\User;
use App\Services\CycleService;
use App\Support\CompensationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** No cycle can be created or approved until the compensation settings have been saved and make sense. */
class CycleSettingsGateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
    }

    private function create(array $period = [])
    {
        return $this->actingAs($this->admin, 'web')->post(route('admin.cycles.store'), $period + ['period_start' => '2026-10-01', 'period_end' => '2026-10-31']);
    }

    // ------------------------------------------------------------------------------------------ nothing saved yet

    public function test_with_no_settings_saved_a_cycle_cannot_be_created(): void
    {
        $this->create()->assertSessionHasErrors('cycle');

        $this->assertSame(0, Cycle::count());
        $this->assertStringContainsString('settings', strtolower(session('errors')->first('cycle')));
    }

    public function test_the_message_names_what_is_missing_and_where_to_fix_it(): void
    {
        $this->create()->assertSessionHasErrors('cycle');
        $message = session('errors')->first('cycle');

        foreach (['Sponsor bonus', 'Level income', 'Weekly matching cap'] as $label) {
            $this->assertStringContainsString($label, $message);
        }
        $this->assertSame(10, count(CompensationSettings::missing()));
    }

    public function test_the_cycles_page_warns_and_does_not_offer_the_create_button_until_settings_are_saved(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.cycles.index'))->assertOk()
            ->assertSee('Set up your compensation settings first')
            ->assertSee(route('admin.settings.edit'), false)
            ->assertDontSee('Create draft cycle');

        $this->configureCompensation();

        $this->actingAs($this->admin, 'web')->get(route('admin.cycles.index'))->assertOk()
            ->assertDontSee('Set up your compensation settings first')->assertSee('Create draft cycle');
    }

    // ------------------------------------------------------------------------------------------ saving the settings opens the gate

    private function settingsPayload(array $extra = []): array
    {
        return $extra + [
            'split_cost_pct' => 40, 'split_pool_pct' => 40, 'split_company_pct' => 20, 'self_pct' => 10, 'sponsor_pct' => 20,
            'rank_pool_pct' => 3, 'match_cap_per_cycle' => 600000, 'match_cap_per_week' => 150000, 'circuit_breaker_pct' => 60, 'levels' => [10, 10, 10],
        ];
    }

    public function test_pressing_save_on_the_settings_page_is_enough_to_open_the_gate(): void
    {
        $this->create()->assertSessionHasErrors('cycle');

        $this->actingAs($this->admin, 'web')->put(route('admin.settings.update'), $this->settingsPayload())->assertSessionHasNoErrors();
        $this->assertSame([], CompensationSettings::missing());

        $this->create()->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertSame(1, Cycle::count());
    }

    #[DataProvider('everyRequiredSetting')]
    public function test_each_required_setting_is_needed_on_its_own(string $key, string $label): void
    {
        $this->configureCompensation();
        Setting::where('key', $key)->delete();
        \Illuminate\Support\Facades\Cache::flush();

        $this->create()->assertSessionHasErrors('cycle');
        $this->assertStringContainsString($label, session('errors')->first('cycle'));
        $this->assertSame(0, Cycle::count());
    }

    public static function everyRequiredSetting(): array
    {
        return [
            'cost split' => ['split_cost_pct', 'Product cost share'],
            'pool split' => ['split_pool_pct', 'Pool share'],
            'company split' => ['split_company_pct', 'Company share'],
            'self' => ['self_pct', 'Self income'],
            'sponsor' => ['sponsor_pct', 'Sponsor bonus'],
            'rank pool' => ['rank_pool_pct', 'Rank pool'],
            'monthly cap' => ['match_cap_per_cycle', 'Monthly matching cap'],
            'weekly cap' => ['match_cap_per_week', 'Weekly matching cap'],
            'breaker' => ['circuit_breaker_pct', 'Circuit breaker'],
            'levels' => ['level_payout_pcts', 'Level income'],
        ];
    }

    // ------------------------------------------------------------------------------------------ nonsense values

    public function test_a_revenue_split_that_does_not_add_up_to_100_is_refused(): void
    {
        $this->configureCompensation(['split_cost_pct' => 50, 'split_pool_pct' => 40, 'split_company_pct' => 20]);

        $this->create()->assertSessionHasErrors('cycle');
        $this->assertStringContainsString('100', session('errors')->first('cycle'));
        $this->assertSame(0, Cycle::count());
    }

    public function test_non_numeric_or_negative_settings_are_refused(): void
    {
        foreach (['abc', '-5', '', '101'] as $bad) {
            $this->configureCompensation(['self_pct' => $bad]);
            $this->create()->assertSessionHasErrors('cycle');
        }
        $this->assertSame(0, Cycle::count());
    }

    public function test_level_percentages_must_be_a_real_list(): void
    {
        $this->configureCompensation();
        Setting::set('level_payout_pcts', 'not json');
        $this->create()->assertSessionHasErrors('cycle');

        Setting::set('level_payout_pcts', json_encode([]));
        $this->create()->assertSessionHasErrors('cycle');
        $this->assertSame(0, Cycle::count());
    }

    public function test_zero_is_a_valid_choice_for_a_percentage(): void
    {
        $this->configureCompensation(['rank_pool_pct' => 0, 'self_pct' => 0]);

        $this->create()->assertSessionHasNoErrors();
        $this->assertSame(1, Cycle::count());
    }

    // ------------------------------------------------------------------------------------------ approving is guarded too

    public function test_a_draft_cycle_cannot_be_approved_if_the_settings_were_removed_afterwards(): void
    {
        $this->configureCompensation();
        $this->create()->assertSessionHasNoErrors();
        $cycle = Cycle::firstOrFail();

        Setting::where('key', 'sponsor_pct')->delete();
        \Illuminate\Support\Facades\Cache::flush();

        $this->actingAs($this->admin, 'web')->put(route('admin.cycles.approve', $cycle))->assertSessionHasErrors('cycle');
        $this->assertSame('draft', $cycle->fresh()->status);
        $this->assertSame(0, \App\Models\LedgerEntry::count());
    }

    public function test_the_service_itself_refuses_so_no_other_code_path_can_skip_the_check(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Settings');

        app(CycleService::class)->create('2026-10-01', '2026-10-31');
    }

    public function test_a_complete_set_of_settings_lets_a_cycle_be_created_and_approved(): void
    {
        $this->configureCompensation();
        $this->create()->assertSessionHasNoErrors();

        $this->actingAs($this->admin, 'web')->put(route('admin.cycles.approve', Cycle::firstOrFail()))->assertSessionHasNoErrors();

        $this->assertSame('approved', Cycle::first()->status);
    }

    public function test_the_simulation_also_explains_instead_of_crashing_when_settings_are_missing(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.cycles.simulate'))->assertOk()->assertSee('Settings are not complete');
        $this->actingAs($this->admin, 'web')->post(route('admin.cycles.simulate.run'))->assertOk()->assertSee('Settings are not complete');

        $this->assertSame(0, Cycle::count());
    }

    // ------------------------------------------------------------------------------------------ who sees what

    public function test_staff_without_finance_access_are_told_to_ask_the_owner_not_shown_a_settings_link(): void
    {
        $ops = User::create(['name' => 'Ops', 'email' => 'o@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['cycles'], 'is_active' => true]);

        $this->actingAs($ops, 'web')->get(route('admin.cycles.index'))->assertOk()
            ->assertSee('Set up your compensation settings first')->assertSee('Ask the owner')->assertDontSee(route('admin.settings.edit'), false);
        $this->actingAs($ops, 'web')->post(route('admin.cycles.store'), ['period_start' => '2026-10-01', 'period_end' => '2026-10-31'])->assertSessionHasErrors('cycle');
    }
}
