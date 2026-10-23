<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationLegTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $code, ?Member $parent = null, ?string $position = null, ?Member $sponsor = null): Member
    {
        return Member::create([
            'member_code' => $code,
            'name' => $code,
            'phone' => '9'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'sponsor_id' => ($sponsor ?? $parent)?->id,
            'placement_id' => $parent?->id,
            'position' => $position,
            'path' => $parent ? (($parent->path ?? '').$parent->id.'.') : null,
            'status' => 'active',
        ]);
    }

    private function register(array $data): \Illuminate\Testing\TestResponse
    {
        $response = $this->post(route('register.send'), $data + ['name' => 'New Person', 'phone' => '9876543210']);
        if ($response->getSession()->has('pending_registration')) {
            $code = $response->getSession()->get('debug_code');
            $response = $this->post(route('register.verify.submit'), ['otp' => $code]);
        }

        return $response;
    }

    public function test_a_chosen_leg_places_the_new_member_on_that_side(): void
    {
        $sponsor = $this->member('BSSPON001');

        $right = app(MemberService::class)->placeUnderSponsor($sponsor, 'right');
        $left = app(MemberService::class)->placeUnderSponsor($sponsor, 'left');

        $this->assertSame($sponsor->id, $right['placement_id']);
        $this->assertSame('right', $right['position']);
        $this->assertSame('left', $left['position']);
    }

    public function test_when_the_chosen_slot_is_taken_the_member_spills_down_inside_the_same_leg(): void
    {
        $sponsor = $this->member('BSSPON002');
        $this->member('BSLEFT002', $sponsor, 'left');
        $rightChild = $this->member('BSRIGHT02', $sponsor, 'right');

        // Right is occupied: a new right-leg member goes under the right child, never into the left leg.
        $placement = app(MemberService::class)->placeUnderSponsor($sponsor, 'right');

        $this->assertSame($rightChild->id, $placement['placement_id']);
        $this->assertStringStartsWith($sponsor->id.'.'.'', (string) $placement['path']);
        $this->assertSame($sponsor->id.'.'.$rightChild->id.'.', $placement['path']);
    }

    public function test_without_a_leg_placement_is_unchanged_left_first(): void
    {
        $sponsor = $this->member('BSSPON003');

        $this->assertSame('left', app(MemberService::class)->placeUnderSponsor($sponsor)['position']);
    }

    public function test_registering_through_a_right_leg_link_lands_on_the_sponsors_right(): void
    {
        $sponsor = $this->member('BSSPON004');

        $this->register(['sponsor_code' => 'BSSPON004', 'side' => 'right']);

        $new = Member::where('phone', '9876543210')->firstOrFail();
        $this->assertSame($sponsor->id, $new->sponsor_id);
        $this->assertSame($sponsor->id, $new->placement_id);
        $this->assertSame('right', $new->position);
    }

    public function test_an_invalid_side_value_is_rejected(): void
    {
        $this->member('BSSPON005');

        $this->post(route('register.send'), ['name' => 'X', 'phone' => '9876543211', 'sponsor_code' => 'BSSPON005', 'side' => 'middle'])
            ->assertSessionHasErrors('side');
    }

    public function test_sponsor_is_required_when_the_admin_setting_is_on(): void
    {
        $this->member('BSSPON006');
        Setting::set('require_sponsor', 1);

        $this->post(route('register.send'), ['name' => 'No Sponsor', 'phone' => '9876543212'])
            ->assertSessionHasErrors('sponsor_code');
    }

    public function test_sponsor_stays_optional_when_the_setting_is_off(): void
    {
        $this->member('BSSPON007');
        Setting::set('require_sponsor', 0);

        $this->post(route('register.send'), ['name' => 'No Sponsor', 'phone' => '9876543213'])
            ->assertSessionHasNoErrors();
    }

    public function test_the_first_ever_member_can_register_without_a_sponsor_even_when_required(): void
    {
        Setting::set('require_sponsor', 1);

        $this->post(route('register.send'), ['name' => 'Founder', 'phone' => '9876543214'])
            ->assertSessionHasNoErrors();
    }

    public function test_register_form_marks_the_sponsor_required_and_shows_the_chosen_leg(): void
    {
        $this->member('BSSPON008');
        Setting::set('require_sponsor', 1);

        $this->get(route('register', ['sponsor' => 'BSSPON008', 'side' => 'right']))
            ->assertOk()
            ->assertSee('name="side" value="right"', false)
            ->assertSee('A sponsor code is required');
    }

    public function test_admin_can_switch_the_required_sponsor_rule_on_and_off(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => bcrypt('x'), 'role' => 'admin']);
        $payload = [
            'gst_pct' => 12, 'split_cost_pct' => 40, 'split_pool_pct' => 40, 'split_company_pct' => 20,
            'self_pct' => 10, 'sponsor_pct' => 20, 'rank_pool_pct' => 3,
            'match_cap_per_cycle' => 600000, 'match_cap_per_week' => 150000, 'circuit_breaker_pct' => 60,
            'levels' => [10, 10, 10],
        ];

        $this->actingAs($admin, 'web')->put(route('admin.settings.update'), $payload + ['require_sponsor' => '1']);
        $this->assertSame(1, (int) Setting::get('require_sponsor'));

        $this->actingAs($admin, 'web')->put(route('admin.settings.update'), $payload);
        $this->assertSame(0, (int) Setting::get('require_sponsor'));
    }
}
