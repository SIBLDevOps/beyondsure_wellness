<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuideTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin', array $permissions = []): User
    {
        return User::create([
            'name' => 'Staff', 'email' => 'g'.random_int(1, 99999).'@t.com', 'password' => 'secret123',
            'role' => $role, 'permissions' => $permissions, 'is_active' => true,
        ]);
    }

    public function test_super_admin_sees_the_guide_with_live_settings(): void
    {
        Setting::set('self_pct', 10);
        Setting::set('sponsor_pct', 15);

        $res = $this->actingAs($this->admin())->get(route('admin.guide.index'));

        $res->assertOk()
            ->assertSee('The big picture')
            ->assertSee('Sign-up to payout', false)
            ->assertSee('Matching &amp; levels', false)
            ->assertSee('Rank pool')
            ->assertSee('Wallet &amp; withdrawals', false)
            ->assertSee('Circuit breaker');
    }

    public function test_sub_admin_needs_finance_to_open_the_guide(): void
    {
        $this->actingAs($this->admin('sub_admin', ['orders']))
            ->get(route('admin.guide.index'))->assertForbidden();

        $this->actingAs($this->admin('sub_admin', ['finance']))
            ->get(route('admin.guide.index'))->assertOk();
    }

    public function test_guide_link_is_hidden_from_sub_admin_without_finance(): void
    {
        $this->actingAs($this->admin('sub_admin', ['orders']))
            ->get(route('admin.dashboard'))->assertOk()->assertDontSee('Business Guide');
    }
}
