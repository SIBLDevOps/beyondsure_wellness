<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Services\MemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Only the admin can start a region: the admin creates its root member. The public region link can never make a
 * stranger the root (the root earns on every match in the region, so it is the most valuable seat in the tree).
 */
class RegionStartTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Region $main;

    private Region $gj;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $this->main = Region::where('code', 'MAIN')->firstOrFail();
        $this->gj = Region::create(['name' => 'Gujarat', 'code' => 'GJ']);
    }

    private function sub(array $perms, ?array $regions = null): User
    {
        return User::create(['name' => 'Sub', 'email' => 's'.random_int(1, 99999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'region_ids' => $regions, 'is_active' => true]);
    }

    private function createRoot(Region $region, array $fields = [], ?User $as = null)
    {
        $this->mails++;

        return $this->actingAs($as ?? $this->admin, 'web')->post(route('admin.regions.root', $region), $fields + ['name' => 'Root Rakesh', 'phone' => '9811100001', 'email' => 'root'.$this->mails.'@example.com']);
    }

    private int $mails = 0;

    private function signUp(array $fields, array $linkQuery = [])
    {
        $this->app['auth']->guard('member')->logout();
        $this->app['auth']->guard('web')->logout();
        $this->flushSession();
        $this->get(route('register', $linkQuery));

        return $this->post(route('register.send'), $fields + ['name' => 'Stranger']);
    }

    // ------------------------------------------------------------------------------------------ the public cannot start a region

    public function test_nobody_can_become_a_root_through_the_public_region_link(): void
    {
        $this->signUp(['phone' => '9777700001', 'region' => 'GJ'], ['region' => 'GJ'])->assertSessionHasErrors('region');

        $this->assertStringContainsString('not been opened', session('errors')->first('region'));
        $this->assertSame(0, Member::count());
        $this->assertNull($this->gj->fresh()->root());
    }

    public function test_switching_the_sponsor_rule_off_or_on_makes_no_difference_to_that(): void
    {
        foreach ([0, 1] as $required) {
            Setting::set('require_sponsor', $required);
            $this->signUp(['phone' => '9777700002', 'region' => 'GJ'], ['region' => 'GJ'])->assertSessionHasErrors('region');
        }
        $this->assertSame(0, Member::count());
    }

    public function test_a_sponsor_code_cannot_be_used_to_slip_into_a_region_with_no_root(): void
    {
        $this->signUp(['phone' => '9777700003', 'sponsor_code' => 'DOESNOTEXIST', 'region' => 'GJ'], ['region' => 'GJ'])->assertSessionHasErrors('sponsor_code');

        $this->assertSame(0, Member::count());
    }

    public function test_the_register_page_says_the_region_is_not_open_yet_and_offers_no_form(): void
    {
        $this->get(route('register', ['region' => 'GJ']))->assertOk()
            ->assertSee('not been opened yet')->assertDontSee('name="phone"', false)->assertDontSee('Send OTP');
    }

    public function test_a_region_without_a_root_is_not_offered_in_the_choice_of_regions(): void
    {
        $this->createRoot($this->main)->assertSessionHasNoErrors();   // Main is started, Gujarat is not

        $this->get(route('register'))->assertOk()->assertDontSee('Gujarat')->assertSee('name="phone"', false);
    }

    public function test_when_no_region_is_open_at_all_the_page_explains_instead_of_showing_a_form(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('not been opened yet')->assertDontSee('Send OTP');
    }

    public function test_a_stale_pending_registration_cannot_create_a_root_either(): void
    {
        // Someone who got past the first step while a region had a root is held in the session. It must not create a root.
        $this->withSession(['pending_registration' => ['name' => 'Sneaky', 'phone' => '9777700004', 'region_id' => $this->gj->id, 'sponsor_code' => null, 'side' => null, 'plan_id' => null]]);
        $this->post(route('register.send'), ['name' => 'x', 'phone' => '9777700004', 'region' => 'GJ']);   // refused at step one
        $issue = app(\App\Services\OtpService::class)->issue('9777700004', 'register');
        $this->withSession(['pending_registration' => ['name' => 'Sneaky', 'phone' => '9777700004', 'region_id' => $this->gj->id, 'sponsor_code' => null, 'side' => null, 'plan_id' => null]])
            ->post(route('register.verify.submit'), ['otp' => $issue['debug_code']]);

        $this->assertSame(0, Member::count(), 'Verifying never creates a root');
    }

    // ------------------------------------------------------------------------------------------ the admin starts the region

    public function test_the_admin_creates_the_root_and_it_is_the_top_of_the_tree(): void
    {
        $this->createRoot($this->gj)->assertSessionHasNoErrors()->assertSessionHas('status');

        $root = Member::firstOrFail();
        $this->assertSame([$this->gj->id, null, null, null, 'Root Rakesh', '9811100001', 'root1@example.com', 'inactive'], [$root->region_id, $root->sponsor_id, $root->placement_id, $root->path, $root->name, $root->phone, $root->email, $root->status]);
        $this->assertMatchesRegularExpression('/^BS[A-Z0-9]{6}$/', $root->member_code);
        $this->assertTrue($this->gj->fresh()->root()->is($root));
    }

    public function test_a_root_with_an_email_is_welcomed_and_one_without_is_simply_not_emailed(): void
    {
        $this->createRoot($this->main, ['phone' => '9811100050', 'email' => 'welcome@example.com'])->assertSessionHasNoErrors();
        $this->createRoot($this->gj, ['phone' => '9811100051', 'email' => ''])->assertSessionHasNoErrors();

        $welcomes = Mail::sent(\App\Mail\BrandedMail::class)->filter(fn ($m) => str_contains($m->mailSubject, 'Welcome'));
        $this->assertCount(1, $welcomes);
        $this->assertTrue($welcomes->first()->hasTo('welcome@example.com'));
    }

    public function test_the_email_is_optional(): void
    {
        $this->createRoot($this->gj, ['email' => ''])->assertSessionHasNoErrors();

        $this->assertNull(Member::firstOrFail()->email);
    }

    public function test_a_region_can_only_have_one_root_a_second_attempt_is_refused(): void
    {
        $this->createRoot($this->gj)->assertSessionHasNoErrors();
        $this->createRoot($this->gj, ['name' => 'Second', 'phone' => '9811100002'])->assertSessionHasErrors('root');
        $this->createRoot($this->gj, ['name' => 'Third', 'phone' => '9811100003'])->assertSessionHasErrors('root');

        $this->assertSame(1, Member::whereNull('placement_id')->where('region_id', $this->gj->id)->count());
        $this->assertSame(1, Member::count());
    }

    public function test_a_double_click_makes_only_one_root(): void
    {
        $service = app(MemberService::class);
        $service->createRoot($this->gj, 'Root Rakesh', '9811100001', null);

        try {
            $service->createRoot($this->gj, 'Root Rakesh', '9811100001', null);
            $this->fail('A second root must be refused');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('already has a root', $e->getMessage());
        }
        $this->assertSame(1, Member::count());
    }

    public function test_a_root_in_one_region_does_not_affect_another(): void
    {
        $this->createRoot($this->main, ['phone' => '9811100010'])->assertSessionHasNoErrors();
        $this->createRoot($this->gj, ['phone' => '9811100011', 'name' => 'Gujarat Root'])->assertSessionHasNoErrors();

        $this->assertSame(2, Member::whereNull('placement_id')->count());
        $this->assertSame('Gujarat Root', $this->gj->fresh()->root()->name);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function badRoots(): array
    {
        return [
            'no name' => [['name' => ''], 'name'],
            'name too long' => [['name' => str_repeat('a', 300)], 'name'],
            'no phone' => [['phone' => ''], 'phone'],
            'short phone' => [['phone' => '98111'], 'phone'],
            'phone with letters' => [['phone' => '98111abcde'], 'phone'],
            'phone starting with 5' => [['phone' => '5811100001'], 'phone'],
            'bad email' => [['email' => 'not-an-email'], 'email'],
            'array as name' => [['name' => ['x']], 'name'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badRoots')]
    public function test_bad_input_is_refused_and_nothing_is_created(array $fields, string $field): void
    {
        $this->createRoot($this->gj, $fields)->assertSessionHasErrors($field);

        $this->assertSame(0, Member::count());
    }

    public function test_a_phone_number_that_already_belongs_to_a_member_is_refused_naming_the_region(): void
    {
        $this->createRoot($this->main, ['phone' => '9811100020', 'name' => 'Main Root'])->assertSessionHasNoErrors();

        $this->createRoot($this->gj, ['phone' => '9811100020'])->assertSessionHasErrors('phone');

        $this->assertStringContainsString('Main', session('errors')->first('phone'));
        $this->assertSame(1, Member::count());
    }

    public function test_an_email_already_in_use_is_refused(): void
    {
        $this->createRoot($this->main, ['phone' => '9811100030', 'email' => 'same@example.com'])->assertSessionHasNoErrors();

        $this->createRoot($this->gj, ['phone' => '9811100031', 'email' => 'same@example.com'])->assertSessionHasErrors('email');
    }

    public function test_only_staff_with_regions_access_can_create_a_root(): void
    {
        $this->createRoot($this->gj, [], $this->sub(['members']))->assertForbidden();
        $this->assertSame(0, Member::count());

        $this->createRoot($this->gj, [], $this->sub(['regions']))->assertSessionHasNoErrors();
        $this->assertSame(1, Member::count());
    }

    public function test_a_region_limited_sub_admin_can_start_only_their_own_regions(): void
    {
        $limited = $this->sub(['regions'], [$this->gj->id]);

        $this->createRoot($this->main, [], $limited)->assertNotFound();
        $this->assertSame(0, Member::count());

        $this->createRoot($this->gj, [], $limited)->assertSessionHasNoErrors();
        $this->assertSame(1, Member::count());
    }

    public function test_signed_out_visitors_and_members_cannot_use_it(): void
    {
        $this->app['auth']->guard('web')->logout();
        $this->post(route('admin.regions.root', $this->gj), ['name' => 'X', 'phone' => '9811100040'])->assertRedirect(route('admin.login'));

        $member = Member::create(['member_code' => 'M1', 'name' => 'M', 'phone' => '9811100041', 'status' => 'active', 'region_id' => $this->main->id]);
        $this->actingAs($member, 'member')->post(route('admin.regions.root', $this->gj), ['name' => 'X', 'phone' => '9811100042'])->assertRedirect();
        $this->assertSame(1, Member::count());
    }

    public function test_creating_a_root_is_recorded_in_the_audit_log(): void
    {
        $this->createRoot($this->gj)->assertSessionHasNoErrors();

        $this->assertSame('members', AuditLog::where('action', 'admin.regions.root')->firstOrFail()->category);
        $this->assertSame('Region #'.$this->gj->id, AuditLog::where('action', 'admin.regions.root')->first()->subject);
        $this->assertStringContainsString('region_id', AuditLog::where('action', 'change.member.created')->firstOrFail()->details);
    }

    // ------------------------------------------------------------------------------------------ after the root exists

    public function test_once_the_region_is_started_people_can_join_and_land_under_the_root(): void
    {
        $this->createRoot($this->gj)->assertSessionHasNoErrors();
        $root = Member::firstOrFail();

        $this->signUp(['phone' => '9777700020', 'region' => 'GJ'], ['region' => 'GJ'])->assertSessionHasNoErrors();
        $this->post(route('register.verify.submit'), ['otp' => session('debug_code')]);

        $joined = Member::where('phone', '9777700020')->firstOrFail();
        $this->assertSame($root->id, $joined->placement_id);
        $this->assertSame($root->id, $joined->sponsor_id);
        $this->assertSame(1, Member::whereNull('placement_id')->count(), 'Still only one root');
    }

    public function test_the_root_signs_in_with_their_mobile_number_and_lands_on_the_plans(): void
    {
        $this->createRoot($this->gj)->assertSessionHasNoErrors();
        $this->app['auth']->guard('web')->logout();
        $this->flushSession();

        $this->post(route('login.send'), ['phone' => '9811100001']);
        $this->post(route('login.verify.submit'), ['otp' => session('debug_code')])->assertRedirect(route('member.plans.index'));
    }

    // ------------------------------------------------------------------------------------------ what the admin sees

    public function test_the_regions_page_offers_to_create_the_root_instead_of_a_public_link(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.regions.index'))->assertOk()
            ->assertSee('Create the root member')->assertSee(route('admin.regions.root', $this->gj), false)
            ->assertDontSee('First-member link')->assertDontSee($this->gj->registerUrl(), false);
    }

    public function test_after_the_root_exists_the_form_is_gone_and_the_root_is_shown(): void
    {
        $this->createRoot($this->gj)->assertSessionHasNoErrors();

        $this->actingAs($this->admin, 'web')->get(route('admin.regions.index'))->assertOk()
            ->assertSee('Root Rakesh')->assertDontSee(route('admin.regions.root', $this->gj), false);
    }

    public function test_the_sponsor_setting_no_longer_promises_a_public_way_to_start_a_region(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.settings.edit'))->assertOk()
            ->assertDontSee('so a new region can always be started')->assertSee('starts a region');
    }
}
