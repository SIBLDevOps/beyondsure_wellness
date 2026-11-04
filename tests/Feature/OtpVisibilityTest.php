<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Until an SMS gateway is connected, UAT and local machines need the one-time code on screen. It is controlled by one
 * explicit switch (OTP_SHOW_ON_SCREEN), off by default so a live site never leaks it.
 */
class OtpVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function asEnvironment(string $env, bool $debug = false, ?bool $show = null): void
    {
        // The test runner only skips CSRF checks in the "testing" environment.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
        $this->app->detectEnvironment(fn () => $env);
        config(['app.debug' => $debug, 'otp.show_on_screen' => $show ?? false]);
    }

    private function member(): Member
    {
        return Member::create(['member_code' => 'V1', 'name' => 'V', 'phone' => '9811100001', 'status' => 'active']);
    }

    public function test_the_switch_exists_and_is_off_by_default(): void
    {
        $this->assertFalse(config('otp.show_on_screen'));
    }

    public function test_with_the_switch_on_a_uat_server_shows_the_code_on_the_login_verify_page(): void
    {
        $this->asEnvironment('production', debug: false, show: true);   // UAT often runs with the production settings
        $this->member();
        Log::spy();

        $this->post(route('login.send'), ['phone' => '9811100001'])->assertRedirect(route('login.verify'));

        $code = session('debug_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);
        $this->get(route('login.verify'))->assertOk()->assertSee('Your OTP is', false)->assertSee('>'.$code.'<', false);
        Log::shouldHaveReceived('info')->withArgs(fn ($message) => str_contains((string) $message, 'OTP for 9811100001'));
    }

    public function test_with_the_switch_on_the_code_also_shows_on_the_register_verify_page(): void
    {
        $this->startRegion();
        $this->asEnvironment('production', debug: false, show: true);

        $this->post(route('register.send'), ['name' => 'New Person', 'phone' => '9811100002', 'region' => 'MAIN'])->assertRedirect(route('register.verify'));

        $code = session('debug_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);
        $this->get(route('register.verify'))->assertSee('Your OTP is', false)->assertSee('>'.$code.'<', false);
    }

    public function test_the_shown_code_really_logs_the_member_in(): void
    {
        $this->asEnvironment('production', debug: false, show: true);
        $member = $this->member();

        $this->post(route('login.send'), ['phone' => '9811100001']);
        $this->post(route('login.verify.submit'), ['otp' => session('debug_code')])->assertRedirect(route('member.dashboard'));

        $this->assertSame($member->id, auth('member')->id());
    }

    public function test_with_the_switch_off_a_production_server_never_shows_or_logs_the_code_even_with_debug_on(): void
    {
        $this->asEnvironment('production', debug: true, show: false);
        $this->member();
        Log::spy();

        $this->post(route('login.send'), ['phone' => '9811100001'])->assertRedirect(route('login.verify'));

        $this->assertNull(session('debug_code'));
        $this->get(route('login.verify'))->assertDontSee('Your OTP is');
        Log::shouldNotHaveReceived('info', fn ($message) => str_contains((string) $message, 'OTP for'));
    }

    public function test_a_local_machine_with_debug_on_still_shows_the_code_without_the_switch(): void
    {
        $this->asEnvironment('local', debug: true, show: false);
        $this->member();

        $this->post(route('login.send'), ['phone' => '9811100001']);

        $this->assertNotNull(session('debug_code'));
    }

    public function test_the_service_reports_a_null_code_when_neither_condition_is_met(): void
    {
        $this->asEnvironment('production', debug: false, show: false);

        $this->assertNull(app(OtpService::class)->issue('9811100003', 'login')['debug_code']);
    }

    public function test_the_example_env_documents_the_switch_as_off(): void
    {
        $example = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^OTP_SHOW_ON_SCREEN=false$/m', $example);
    }
}
