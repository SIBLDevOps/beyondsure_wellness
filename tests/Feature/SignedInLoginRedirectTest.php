<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignedInLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_in_admin_opening_the_admin_login_goes_to_the_admin_dashboard(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin, 'web')->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_signed_in_member_opening_the_member_login_goes_to_their_dashboard(): void
    {
        $member = Member::create(['member_code' => 'M1', 'name' => 'M', 'phone' => '9811100000', 'status' => 'active']);

        $this->actingAs($member, 'member')->get(route('login'))->assertRedirect(route('member.dashboard'));
    }

    public function test_admin_can_sign_in_with_email_and_password(): void
    {
        User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);

        $this->post(route('admin.login.submit'), ['email' => 'a@t.com', 'password' => 'secret123'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('web');
    }
}
