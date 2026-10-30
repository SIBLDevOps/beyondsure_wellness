<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin area keeps its own blue; the website, sign-in pages and member area keep the green brand. Each has its own
 * colour setting.
 */
class AdminColourTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
    }

    private function save(array $extra, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin, 'web')->put(route('admin.site-content.update'), ['site_name' => 'B'] + $extra);
    }

    public function test_by_default_the_admin_is_blue_and_the_customer_side_is_green(): void
    {
        $member = Member::create(['member_code' => 'M1', 'name' => 'M', 'phone' => '9811100001', 'status' => 'active']);

        $this->get(route('admin.login'))->assertSee('--brand-600:#4f46e5', false)->assertSee('--color-brand-600:#4f46e5', false);
        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertSee('--brand-600:#4f46e5', false)->assertSee('--bs-primary:#4f46e5', false)->assertSee('--brand-900:#1e1b4b', false);

        $this->get('/')->assertSee('--color-brand-600:#059669', false);
        $this->get(route('login'))->assertSee('--color-brand-600:#059669', false);
        $this->actingAs($member, 'member')->get(route('member.dashboard'))->assertSee('--brand-600:#059669', false);
    }

    public function test_the_admin_area_does_not_turn_the_action_buttons_into_the_brand_colour(): void
    {
        $html = $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->getContent();

        $this->assertStringNotContainsString('.btn-success{--bs-btn-bg', $html);
        $this->assertStringNotContainsString('.btn-primary,.btn-success', $html);
    }

    public function test_the_website_page_offers_a_separate_admin_colour(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.site-content.edit'))->assertOk()
            ->assertSee('Admin panel colour')->assertSee('name="site_admin_color"', false)->assertSee('name="site_primary_color"', false);
    }

    public function test_changing_the_admin_colour_recolours_only_the_admin(): void
    {
        $this->save(['site_admin_color' => '#7c3aed'])->assertSessionHasNoErrors();
        $member = Member::create(['member_code' => 'M2', 'name' => 'M', 'phone' => '9811100002', 'status' => 'active']);

        $this->assertSame('#7c3aed', Theme::adminPrimary());
        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertSee('--brand-600:#7c3aed', false);
        $this->app['auth']->guard('web')->logout();
        $this->get(route('admin.login'))->assertSee('--brand-600:#7c3aed', false);

        $this->assertSame(Theme::DEFAULT, Theme::primary());
        $this->get('/')->assertSee('--color-brand-600:#059669', false)->assertDontSee('#7c3aed', false);
        $this->actingAs($member, 'member')->get(route('member.dashboard'))->assertSee('--brand-600:#059669', false)->assertDontSee('#7c3aed', false);
    }

    public function test_changing_the_brand_colour_leaves_the_admin_blue(): void
    {
        $this->save(['site_primary_color' => '#2563eb'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertSee('--brand-600:#4f46e5', false)->assertDontSee('#2563eb', false);
        $this->app['auth']->guard('web')->logout();
        $this->get('/')->assertSee('--color-brand-600:#2563eb', false);
    }

    public function test_the_admin_colour_is_validated_like_the_brand_colour(): void
    {
        foreach (['red', '#12', '#fff;}</style><script>alert(1)</script>', '#gggggg'] as $bad) {
            $this->save(['site_admin_color' => $bad])->assertSessionHasErrors('site_admin_color');
        }
        $this->save(['site_admin_color' => '#ffff00'])->assertSessionHasErrors('site_admin_color');
        $this->assertStringContainsString('too light', session('errors')->first('site_admin_color'));

        $this->assertSame(Theme::ADMIN_DEFAULT, Theme::adminPrimary());
    }

    public function test_an_empty_admin_colour_puts_the_default_blue_back(): void
    {
        $this->save(['site_admin_color' => '#7c3aed']);
        $this->assertSame('#7c3aed', Theme::adminPrimary());

        $this->save(['site_admin_color' => ''])->assertSessionHasNoErrors();

        $this->assertSame(Theme::ADMIN_DEFAULT, Theme::adminPrimary());
    }

    public function test_a_corrupted_stored_admin_colour_never_reaches_the_page(): void
    {
        Setting::set('site_admin_color', 'red;}</style><script>alert(1)</script>');

        $html = $this->get(route('admin.login'))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('--brand-600:#4f46e5', $html);
    }

    public function test_only_people_with_the_website_permission_can_change_the_admin_colour(): void
    {
        $sub = User::create(['name' => 'S', 'email' => 's@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => ['orders'], 'is_active' => true]);

        $this->save(['site_admin_color' => '#7c3aed'], $sub)->assertForbidden();
        $this->assertSame(Theme::ADMIN_DEFAULT, Theme::adminPrimary());
    }
}
