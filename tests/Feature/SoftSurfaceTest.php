<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Page backgrounds: bright white is the default (the look the site always had). The soft, tinted off-white is an
 * optional choice in Website settings for anyone who finds pure white harsh.
 */
class SoftSurfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
    }

    private function save(array $extra)
    {
        return $this->actingAs($this->admin, 'web')->put(route('admin.site-content.update'), ['site_name' => 'B'] + $extra);
    }

    public function test_by_default_every_area_keeps_the_original_bright_white_backgrounds(): void
    {
        $member = Member::create(['member_code' => 'M1', 'name' => 'M', 'phone' => '9811100001', 'status' => 'active']);

        $this->assertFalse(Theme::soft());
        foreach (['--surface:', '--color-white:var(--surface-raised)', '--bs-body-bg:var(--surface)'] as $softOnly) {
            $this->get('/')->assertDontSee($softOnly, false);
            $this->get(route('login'))->assertDontSee($softOnly, false);
            $this->get(route('admin.login'))->assertDontSee($softOnly, false);
            $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertDontSee($softOnly, false);
            $this->actingAs($member, 'member')->get(route('member.dashboard'))->assertDontSee($softOnly, false);
        }
        // The brand colour system itself is unaffected.
        $this->get('/')->assertSee('--color-brand-600:#059669', false);
    }

    public function test_the_website_page_offers_the_choice_with_bright_white_selected_by_default(): void
    {
        $html = $this->actingAs($this->admin, 'web')->get(route('admin.site-content.edit'))->assertOk()
            ->assertSee('name="site_soft_surface"', false)->assertSee('Soft')->assertSee('Bright white')->getContent();

        $this->assertMatchesRegularExpression('/id="surface_0"\s+value="0"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="surface_1"\s+value="1"\s+checked/', $html);
    }

    public function test_choosing_soft_applies_it_everywhere_and_choosing_bright_removes_it(): void
    {
        $this->save(['site_soft_surface' => '1'])->assertSessionHasNoErrors();
        $this->app['auth']->guard('web')->logout();

        $this->assertTrue(Theme::soft());
        $this->get('/')->assertSee('--surface:'.Theme::surface(Theme::DEFAULT), false)->assertSee('--color-white:var(--surface-raised)', false);
        $this->get(route('admin.login'))->assertSee('--bs-body-bg:var(--surface)', false);

        $this->save(['site_soft_surface' => '0'])->assertSessionHasNoErrors();
        $this->app['auth']->guard('web')->logout();

        $this->assertFalse(Theme::soft());
        $this->get('/')->assertDontSee('--color-white:var(--surface-raised)', false);
    }

    public function test_the_soft_surfaces_follow_a_custom_brand_colour_when_chosen(): void
    {
        Setting::set('site_primary_color', '#2563eb');
        Setting::set('site_soft_surface', '1');

        $this->get('/')->assertSee('--surface:'.Theme::surface('#2563eb'), false)->assertDontSee('--surface:'.Theme::surface('#059669'), false);
    }

    public function test_an_old_default_remembered_by_the_settings_cache_does_not_override_the_current_default(): void
    {
        // An earlier version asked for these settings with a different default and the cache kept that default forever
        // (this is exactly what an existing server's cache table looks like after an upgrade).
        \Illuminate\Support\Facades\Cache::forever('setting:site_soft_surface', '1');
        \Illuminate\Support\Facades\Cache::forever('setting:site_tagline', 'An old tagline default');

        $this->assertFalse(Theme::soft(), 'The bright-white default must win over a stale remembered default');
        $this->assertSame('Wellness & Protection Plans', \App\Support\Site::tagline());
    }

    public function test_the_settings_cache_remembers_real_values_but_never_a_default(): void
    {
        $this->assertSame('fallback', Setting::get('never_saved', 'fallback'));
        $this->assertSame('other', Setting::get('never_saved', 'other'), 'A default is not remembered between calls');

        Setting::set('real_one', 'saved');
        $this->assertSame('saved', Setting::get('real_one', 'ignored'));
        \Illuminate\Support\Facades\DB::table('settings')->where('key', 'real_one')->delete();
        $this->assertSame('saved', Setting::get('real_one'), 'A saved value is cached');

        Setting::set('real_one', 'changed');
        $this->assertSame('changed', Setting::get('real_one'), 'Saving refreshes the cache');
        Setting::set('empty_one', '');
        $this->assertSame('', Setting::get('empty_one', 'default'), 'An intentionally empty value is a value, not a missing one');
    }

    public function test_only_one_or_zero_is_accepted(): void
    {
        $this->save(['site_soft_surface' => 'banana'])->assertSessionHasErrors('site_soft_surface');
    }

    public function test_an_empty_or_corrupted_stored_value_means_bright_white(): void
    {
        foreach (['', '<script>', 'yes', '2'] as $bad) {
            Setting::set('site_soft_surface', $bad);

            $this->assertFalse(Theme::soft(), 'Only an explicit "1" turns soft on');
            $this->get('/')->assertDontSee('<script>', false);
        }
    }
}
