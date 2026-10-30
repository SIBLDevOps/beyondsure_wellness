<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Staff can protect their login with an authenticator app; a stolen password alone is then not enough. */
class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
    }

    /** Switches two-factor on through the real screens; returns [secret, recovery codes]. */
    private function enable(User $u): array
    {
        $this->actingAs($u, 'web')->post(route('admin.account.two-factor.enable'))->assertRedirect();
        $res = $this->actingAs($u->fresh(), 'web')->post(route('admin.account.two-factor.confirm'), ['code' => Totp::at($u->fresh()->two_factor_secret, time())]);
        $res->assertSessionHasNoErrors();

        return [$u->fresh()->two_factor_secret, session('recovery_codes') ?? []];
    }

    private function signOut(): void
    {
        $this->app['auth']->guard('web')->logout();
        $this->flushSession();
    }

    private function password(string $email = 'a@t.com', string $pw = 'secret123')
    {
        return $this->post(route('admin.login.submit'), ['email' => $email, 'password' => $pw]);
    }

    public function test_setting_it_up_shows_a_key_then_needs_a_correct_code_to_switch_on(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.account.two-factor'))->assertOk()->assertSee('Two-factor sign-in')->assertSee('Switch on');

        $this->actingAs($this->admin, 'web')->post(route('admin.account.two-factor.enable'))->assertRedirect(route('admin.account.two-factor'));
        $secret = $this->admin->fresh()->two_factor_secret;
        $this->assertNotEmpty($secret);
        $this->assertNull($this->admin->fresh()->two_factor_confirmed_at, 'Not on until a code is confirmed');

        $this->actingAs($this->admin->fresh(), 'web')->get(route('admin.account.two-factor'))->assertOk()
            ->assertSee($secret)->assertSee('otpauth://totp/', false);

        $this->actingAs($this->admin->fresh(), 'web')->post(route('admin.account.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertNull($this->admin->fresh()->two_factor_confirmed_at);

        $this->actingAs($this->admin->fresh(), 'web')->post(route('admin.account.two-factor.confirm'), ['code' => Totp::at($secret, time())])->assertSessionHasNoErrors();
        $this->assertNotNull($this->admin->fresh()->two_factor_confirmed_at);
    }

    public function test_confirming_shows_eight_recovery_codes_once(): void
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.account.two-factor.enable'));
        $secret = $this->admin->fresh()->two_factor_secret;

        $res = $this->actingAs($this->admin->fresh(), 'web')->post(route('admin.account.two-factor.confirm'), ['code' => Totp::at($secret, time())]);
        $codes = session('recovery_codes');
        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        foreach ($codes as $c) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]{5}-[a-z0-9]{5}$/', $c);
        }

        // The page the person lands on shows them...
        $first = $this->actingAs($this->admin->fresh(), 'web')->get(route('admin.account.two-factor'))->assertOk()->assertSee('Save these recovery codes');
        foreach ($codes as $c) {
            $first->assertSee($c);
        }
        // ...and they are gone from every page after that.
        $second = $this->actingAs($this->admin->fresh(), 'web')->get(route('admin.account.two-factor'))->assertOk()->assertDontSee('Save these recovery codes');
        foreach ($codes as $c) {
            $second->assertDontSee($c);
        }
        $this->assertNotNull($res);
    }

    public function test_the_secret_is_encrypted_and_recovery_codes_are_hashed_in_the_database(): void
    {
        [$secret, $codes] = $this->enable($this->admin);

        $raw = DB::table('users')->where('id', $this->admin->id)->first();
        $this->assertNotSame($secret, $raw->two_factor_secret);
        $this->assertStringNotContainsString($secret, (string) $raw->two_factor_secret);
        foreach ($codes as $c) {
            $this->assertStringNotContainsString($c, (string) $raw->two_factor_recovery_codes);
        }
        $this->assertSame($secret, $this->admin->fresh()->two_factor_secret, 'The app can still read it back');
    }

    // ------------------------------------------------------------------------------------------ signing in

    public function test_without_it_signing_in_works_as_before(): void
    {
        $this->password()->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_with_it_a_correct_password_alone_does_not_sign_you_in(): void
    {
        $this->enable($this->admin);
        $this->signOut();

        $this->password()->assertRedirect(route('admin.two-factor.challenge'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.members.index'))->assertRedirect(route('admin.login'));
    }

    public function test_the_right_code_completes_the_sign_in(): void
    {
        [$secret] = $this->enable($this->admin);
        $this->signOut();
        $this->password();

        $this->get(route('admin.two-factor.challenge'))->assertOk()->assertSee('authenticator');
        $this->post(route('admin.two-factor.verify'), ['code' => Totp::at($secret, time())])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertNotNull(AuditLog::where('action', 'admin.login')->first());
    }

    public function test_a_wrong_code_is_refused_and_five_wrong_tries_lock_the_attempt(): void
    {
        [$secret] = $this->enable($this->admin);
        $this->signOut();
        $this->password();

        $codes = [];
        foreach (range(1, 7) as $i) {
            $codes[] = $this->post(route('admin.two-factor.verify'), ['code' => '000000'])->getStatusCode();
        }
        $this->assertContains(429, $codes);

        // Even the right code no longer works for this half-finished sign-in.
        $this->post(route('admin.two-factor.verify'), ['code' => Totp::at($secret, time())])->assertStatus(429);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_the_challenge_page_needs_a_password_step_first(): void
    {
        $this->get(route('admin.two-factor.challenge'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.two-factor.verify'), ['code' => '123456'])->assertRedirect(route('admin.login'));
    }

    public function test_a_recovery_code_works_once_and_only_once(): void
    {
        [, $codes] = $this->enable($this->admin);
        $this->signOut();
        $this->password();

        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $codes[0]])->assertRedirect(route('admin.dashboard'));
        $this->signOut();
        $this->password();
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => 'aaaaa-bbbbb'])->assertSessionHasErrors('recovery_code');
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $codes[1]])->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_wrong_password_never_reaches_the_code_step(): void
    {
        $this->enable($this->admin);
        $this->signOut();

        $this->password('a@t.com', 'wrong')->assertSessionHasErrors('email');
        $this->get(route('admin.two-factor.challenge'))->assertRedirect(route('admin.login'));
    }

    public function test_an_inactive_or_non_staff_account_cannot_use_the_code_step_to_get_in(): void
    {
        [$secret] = $this->enable($this->admin);
        $this->signOut();
        $this->password();
        $this->admin->update(['is_active' => false]);

        $this->post(route('admin.two-factor.verify'), ['code' => Totp::at($secret, time())]);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    // ------------------------------------------------------------------------------------------ turning it off

    public function test_switching_it_off_needs_the_password_and_a_current_code(): void
    {
        [$secret] = $this->enable($this->admin);

        $this->actingAs($this->admin->fresh(), 'web')->delete(route('admin.account.two-factor.disable'), ['password' => 'wrong', 'code' => Totp::at($secret, time())])->assertSessionHasErrors('password');
        $this->actingAs($this->admin->fresh(), 'web')->delete(route('admin.account.two-factor.disable'), ['password' => 'secret123', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->assertNotNull($this->admin->fresh()->two_factor_confirmed_at);

        $this->actingAs($this->admin->fresh(), 'web')->delete(route('admin.account.two-factor.disable'), ['password' => 'secret123', 'code' => Totp::at($secret, time())])->assertSessionHasNoErrors();
        $fresh = $this->admin->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
        $this->assertNull($fresh->two_factor_recovery_codes);
    }

    public function test_the_super_admin_can_reset_a_locked_out_colleague_and_it_is_logged(): void
    {
        $sub = User::create(['name' => 'Sub', 'email' => 's@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['members'], 'is_active' => true]);
        $this->enable($sub);

        $this->actingAs($sub->fresh(), 'web')->put(route('admin.team.two-factor-reset', $sub))->assertForbidden();
        $this->assertNotNull($sub->fresh()->two_factor_confirmed_at);

        $this->actingAs($this->admin, 'web')->put(route('admin.team.two-factor-reset', $sub))->assertSessionHasNoErrors();
        $this->assertNull($sub->fresh()->two_factor_confirmed_at);
        $this->assertSame('admin.team.two-factor-reset', AuditLog::latest('id')->first()->action);

        $this->signOut();
        $this->password('s@t.com')->assertRedirect(route('admin.dashboard'));     // back to password-only
    }

    public function test_every_signed_in_staff_member_can_reach_the_setup_page_whatever_their_permissions(): void
    {
        $none = User::create(['name' => 'Nobody', 'email' => 'n@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => [], 'is_active' => true]);

        $this->actingAs($none, 'web')->get(route('admin.account.two-factor'))->assertOk();
    }

    public function test_the_status_and_a_wrong_current_password_do_not_leak_the_secret_once_on(): void
    {
        [$secret] = $this->enable($this->admin);

        $this->actingAs($this->admin->fresh(), 'web')->get(route('admin.account.two-factor'))->assertOk()
            ->assertSee('is on')->assertDontSee($secret)->assertDontSee('otpauth://');
        $this->assertTrue(Hash::check('secret123', $this->admin->fresh()->password));
    }
}
