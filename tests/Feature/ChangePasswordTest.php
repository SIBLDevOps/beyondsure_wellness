<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role, array $perms = []): User
    {
        return User::create(['name' => 'S', 'email' => $role.'@t.com', 'password' => 'oldpass123', 'role' => $role, 'permissions' => $perms, 'is_active' => true]);
    }

    public function test_super_admin_and_a_sub_admin_with_no_permissions_can_change_their_own_password(): void
    {
        foreach (['admin', 'sub_admin'] as $role) {
            $user = $this->staff($role);

            $this->actingAs($user, 'web')->get(route('admin.account.password'))->assertOk()->assertSee('Change your password');
            $this->actingAs($user, 'web')->put(route('admin.account.password.update'), [
                'current_password' => 'oldpass123', 'password' => 'newpass456', 'password_confirmation' => 'newpass456',
            ])->assertSessionHasNoErrors()->assertRedirect(route('admin.account.password'));

            $this->assertTrue(Hash::check('newpass456', $user->fresh()->password));
        }
    }

    public function test_the_current_password_must_be_right(): void
    {
        $user = $this->staff('sub_admin');

        $this->actingAs($user, 'web')->put(route('admin.account.password.update'), [
            'current_password' => 'wrong', 'password' => 'newpass456', 'password_confirmation' => 'newpass456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));
    }

    public function test_weak_mismatched_or_unchanged_passwords_are_refused(): void
    {
        $user = $this->staff('admin');

        foreach ([
            ['password' => 'short1', 'password_confirmation' => 'short1'],
            ['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'],
            ['password' => 'newpass456', 'password_confirmation' => 'different789'],
            ['password' => 'oldpass123', 'password_confirmation' => 'oldpass123'],
        ] as $case) {
            $this->actingAs($user, 'web')->put(route('admin.account.password.update'), ['current_password' => 'oldpass123'] + $case)
                ->assertSessionHasErrors('password');
        }

        $this->assertTrue(Hash::check('oldpass123', $user->fresh()->password));
    }

    public function test_a_guest_cannot_reach_it(): void
    {
        $this->get(route('admin.account.password'))->assertRedirect();
    }

    public function test_the_change_password_link_is_in_the_menu(): void
    {
        $this->actingAs($this->staff('sub_admin'), 'web')->get(route('admin.dashboard'))->assertSee('Change password');
    }
}
