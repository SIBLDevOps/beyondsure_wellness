<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Rank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Rank $silver;

    private Rank $gold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => bcrypt('x'), 'role' => 'admin']);
        $this->silver = Rank::create(['name' => 'Silver', 'sort_order' => 1, 'matched_bv_threshold' => 20000, 'min_active_directs' => 2, 'share' => 1]);
        $this->gold = Rank::create(['name' => 'Gold', 'sort_order' => 2, 'matched_bv_threshold' => 100000, 'min_active_directs' => 2, 'share' => 2]);
    }

    private function row(Rank $r, array $over = []): array
    {
        return array_merge([
            'name' => $r->name,
            'matched_bv_threshold' => (float) $r->matched_bv_threshold,
            'min_active_directs' => $r->min_active_directs,
            'share' => $r->share,
        ], $over);
    }

    private function save(array $payload)
    {
        return $this->actingAs($this->admin, 'web')->put(route('admin.settings.ranks'), $payload);
    }

    public function test_guests_cannot_edit_ranks(): void
    {
        $this->put(route('admin.settings.ranks'), [])->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_change_a_ranks_requirements(): void
    {
        $this->save(['ranks' => [
            $this->silver->id => $this->row($this->silver, ['matched_bv_threshold' => 50000, 'min_active_directs' => 3, 'share' => 4]),
            $this->gold->id => $this->row($this->gold),
        ]])->assertRedirect()->assertSessionHas('status');

        $silver = $this->silver->fresh();
        $this->assertEquals(50000, (float) $silver->matched_bv_threshold);
        $this->assertSame(3, $silver->min_active_directs);
        $this->assertSame(4, $silver->share);
    }

    public function test_admin_can_add_a_rank_and_the_ladder_is_resorted_by_threshold(): void
    {
        $this->save([
            'ranks' => [$this->silver->id => $this->row($this->silver), $this->gold->id => $this->row($this->gold)],
            'new_ranks' => [['name' => 'Bronze', 'matched_bv_threshold' => 5000, 'min_active_directs' => 1, 'share' => 1]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Bronze', 'Silver', 'Gold'], Rank::orderBy('sort_order')->pluck('name')->all());
    }

    public function test_renaming_a_rank_renames_it_for_the_members_who_hold_it(): void
    {
        $m = Member::create(['member_code' => 'BSRK0001', 'name' => 'Holder', 'phone' => '9000000001', 'status' => 'active', 'rank' => 'Silver']);

        $this->save(['ranks' => [
            $this->silver->id => $this->row($this->silver, ['name' => 'Starter']),
            $this->gold->id => $this->row($this->gold),
        ]]);

        $this->assertSame('Starter', $m->fresh()->rank);
    }

    public function test_removing_a_rank_deletes_it_and_resets_its_holders(): void
    {
        $m = Member::create(['member_code' => 'BSRK0002', 'name' => 'Holder', 'phone' => '9000000002', 'status' => 'active', 'rank' => 'Gold']);

        $this->save(['ranks' => [
            $this->silver->id => $this->row($this->silver),
            $this->gold->id => $this->row($this->gold, ['remove' => 1]),
        ]]);

        $this->assertNull(Rank::where('name', 'Gold')->first());
        $this->assertSame('none', $m->fresh()->rank);
    }

    public function test_duplicate_rank_names_are_rejected(): void
    {
        $this->save(['ranks' => [
            $this->silver->id => $this->row($this->silver, ['name' => 'Gold']),
            $this->gold->id => $this->row($this->gold),
        ]])->assertSessionHasErrors('ranks');

        $this->assertSame('Silver', $this->silver->fresh()->name);
    }

    public function test_pool_share_must_be_at_least_one(): void
    {
        $this->save(['ranks' => [
            $this->silver->id => $this->row($this->silver, ['share' => 0]),
            $this->gold->id => $this->row($this->gold),
        ]])->assertSessionHasErrors('ranks.'.$this->silver->id.'.share');
    }

    public function test_settings_page_renders_the_editable_rank_rows(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('name="ranks['.$this->silver->id.'][name]"', false)
            ->assertSee('Save ranks');
    }
}
