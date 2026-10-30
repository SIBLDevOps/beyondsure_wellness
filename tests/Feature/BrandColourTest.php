<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandColourTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
    }

    private function save(string $colour, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin, 'web')->put(route('admin.site-content.update'), ['site_name' => 'Brand', 'site_primary_color' => $colour]);
    }

    public function test_without_a_saved_colour_every_area_uses_the_default_brand(): void
    {
        $member = Member::create(['member_code' => 'M1', 'name' => 'M', 'phone' => '9811100001', 'status' => 'active']);

        $this->get('/')->assertSee('--color-brand-600:#059669', false);
        $this->get(route('login'))->assertSee('--color-brand-600:#059669', false);
        $this->get(route('admin.login'))->assertSee('--brand-600:#4f46e5', false); // the admin has its own blue
        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertSee('--brand-600:#4f46e5', false);
        $this->actingAs($member, 'member')->get(route('member.dashboard'))->assertSee('--brand-600:#059669', false);
    }

    public function test_the_website_page_offers_a_colour_picker(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.site-content.edit'))->assertOk()
            ->assertSee('Brand colour')->assertSee('name="site_primary_color"', false)->assertSee('type="color"', false)->assertSee('Reset to default');
    }

    public function test_a_saved_colour_recolours_the_public_site_member_area_and_sign_in_pages(): void
    {
        $this->save('#2563eb')->assertSessionHasNoErrors();
        $member = Member::create(['member_code' => 'M2', 'name' => 'M', 'phone' => '9811100002', 'status' => 'active']);

        $this->assertSame('#2563eb', Theme::primary());
        $this->get('/')->assertSee('--color-brand-600:#2563eb', false)->assertDontSee('--color-brand-600:#059669', false);
        $this->get(route('login'))->assertSee('--color-brand-600:#2563eb', false);
        $this->app['auth']->guard('web')->logout(); // the save above signed the admin in; the login page is for visitors
        $this->get(route('admin.login'))->assertSee('--brand-600:#4f46e5', false)->assertDontSee('#2563eb', false); // the admin does not follow the brand colour
        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertSee('--brand-600:#4f46e5', false)->assertDontSee('--bs-primary:#2563eb', false);
        $this->actingAs($member, 'member')->get(route('member.dashboard'))->assertSee('--brand-600:#2563eb', false);
    }

    public function test_colours_that_could_break_the_page_or_the_styles_are_refused(): void
    {
        foreach (['red', '#12', '#fff;}</style><script>alert(1)</script>', 'url(javascript:alert(1))', '#gggggg'] as $bad) {
            $this->save($bad)->assertSessionHasErrors('site_primary_color');
        }

        $this->assertSame(Theme::DEFAULT, Theme::primary());
        $this->get('/')->assertDontSee('<script>alert(1)', false);
    }

    public function test_a_colour_too_light_for_white_text_is_refused_with_a_clear_reason(): void
    {
        $this->save('#ffff00')->assertSessionHasErrors('site_primary_color');
        $this->assertStringContainsString('too light', session('errors')->first('site_primary_color'));

        $this->assertSame(Theme::DEFAULT, Theme::primary());
    }

    public function test_leaving_it_empty_puts_the_default_brand_back(): void
    {
        $this->save('#2563eb');
        $this->assertSame('#2563eb', Theme::primary());

        $this->save('')->assertSessionHasNoErrors();

        $this->assertSame(Theme::DEFAULT, Theme::primary());
        $this->get('/')->assertSee('--color-brand-600:#059669', false);
    }

    public function test_only_people_with_the_website_permission_can_change_the_colour(): void
    {
        $sub = fn (array $perms) => User::create(['name' => 'S', 'email' => 's'.random_int(1, 9999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'is_active' => true]);

        $this->save('#2563eb', $sub(['orders']))->assertForbidden();
        $this->assertSame(Theme::DEFAULT, Theme::primary());

        $this->save('#2563eb', $sub(['content']))->assertSessionHasNoErrors();
        $this->assertSame('#2563eb', Theme::primary());
    }

    public function test_a_corrupted_stored_value_never_reaches_the_page(): void
    {
        Setting::set('site_primary_color', 'red;}</style><script>alert(1)</script>');

        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('--color-brand-600:#059669', $html);
    }
}
