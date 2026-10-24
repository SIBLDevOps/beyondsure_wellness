<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WebsiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'A', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        File::deleteDirectory(public_path(Site::DIR));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path(Site::DIR));
        parent::tearDown();
    }

    private function save(array $extra = [])
    {
        return $this->actingAs($this->admin, 'web')->put(route('admin.site-content.update'), $extra + ['site_name' => 'Acme Health+']);
    }

    public function test_the_page_opens_with_every_section(): void
    {
        $this->actingAs($this->admin, 'web')->get(route('admin.site-content.edit'))->assertOk()
            ->assertSee('Logo &amp; icon', false)->assertSee('Brand name')->assertSee('Homepage top section')
            ->assertSee('Contact details')->assertSee('Social links')->assertSee('Footer')->assertSee('Search engines');
    }

    public function test_changing_the_brand_name_updates_public_pages_the_member_area_and_the_admin(): void
    {
        $this->save(['site_name' => 'Acme Health+', 'site_tagline' => 'Care for everyone'])->assertSessionHasNoErrors();

        $this->get('/')->assertOk()->assertSee('Acme Health')->assertSee('Care for everyone')
            ->assertSee('<title>Acme Health+ — Care for everyone</title>', false)->assertSee('About Acme Health+');
        $this->get(route('login'))->assertOk()->assertSee('Acme Health')->assertSee('Care for everyone');
        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertSee('Acme Health');
        $this->app['auth']->guard('web')->logout();
        $this->get(route('admin.login'))->assertSee('Acme Health');
    }

    public function test_uploading_a_logo_shows_it_everywhere_and_replacing_deletes_the_old_file(): void
    {
        $this->save(['site_logo' => UploadedFile::fake()->image('logo.png', 300, 100)])->assertSessionHasNoErrors();

        $first = Setting::get('site_logo');
        $this->assertFileExists(public_path(Site::DIR.'/'.$first));
        $this->get('/')->assertSee(Site::DIR.'/'.$first, false);
        $this->get(route('login'))->assertSee(Site::DIR.'/'.$first, false);

        $this->save(['site_logo' => UploadedFile::fake()->image('logo2.jpg', 300, 100)]);
        $second = Setting::get('site_logo');

        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist(public_path(Site::DIR.'/'.$first));
        $this->assertFileExists(public_path(Site::DIR.'/'.$second));
    }

    public function test_the_dark_logo_is_used_in_the_dark_menus_and_falls_back_to_the_main_logo(): void
    {
        $this->save(['site_logo' => UploadedFile::fake()->image('light.png')]);
        $light = Setting::get('site_logo');

        $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'))->assertSee(Site::DIR.'/'.$light, false);

        $this->save(['site_logo_dark' => UploadedFile::fake()->image('dark.png')]);
        $dark = Setting::get('site_logo_dark');

        $page = $this->actingAs($this->admin, 'web')->get(route('admin.dashboard'));
        $page->assertSee(Site::DIR.'/'.$dark, false);
        // Public site: the header (light background) keeps the main logo, the dark footer uses the dark one.
        $this->get('/')->assertSee(Site::DIR.'/'.$light, false)->assertSee(Site::DIR.'/'.$dark, false);
        // Sign-in pages have a light background, so they never use the dark logo.
        $this->get(route('login'))->assertSee(Site::DIR.'/'.$light, false)->assertDontSee(Site::DIR.'/'.$dark, false);
    }

    public function test_a_logo_can_be_removed_and_the_text_name_returns(): void
    {
        $this->save(['site_logo' => UploadedFile::fake()->image('logo.png')]);
        $file = Setting::get('site_logo');

        $this->save(['remove_site_logo' => 1])->assertSessionHasNoErrors();

        $this->assertFileDoesNotExist(public_path(Site::DIR.'/'.$file));
        $this->assertNull(Site::logoUrl());
        $this->get('/')->assertDontSee(Site::DIR.'/'.$file, false)->assertSee('Acme Health');
    }

    public function test_a_favicon_is_added_to_the_page_head(): void
    {
        $this->save(['site_favicon' => UploadedFile::fake()->image('fav.png', 64, 64)]);

        $this->get('/')->assertSee('rel="icon"', false)->assertSee(Site::DIR.'/'.Setting::get('site_favicon'), false);
    }

    public function test_only_images_are_accepted(): void
    {
        $this->save(['site_logo' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php')])->assertSessionHasErrors('site_logo');
        $this->save(['site_logo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertSessionHasErrors('site_logo');
        $this->save(['site_logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('site_logo');
        $this->save(['site_favicon' => UploadedFile::fake()->image('huge.png')->size(2000)])->assertSessionHasErrors('site_favicon');

        $this->assertNull(Site::logoUrl());
        $this->assertDirectoryDoesNotExist(public_path(Site::DIR));
    }

    public function test_homepage_text_buttons_plans_heading_seo_and_footer_are_editable(): void
    {
        $this->save([
            'site_hero_title' => 'Brand new headline',
            'site_hero_cta_primary' => 'See our plans',
            'site_hero_cta_secondary' => 'Join today',
            'site_plans_title' => 'Pick your pack',
            'site_meta_title' => 'Acme | Home',
            'site_meta_description' => 'The best care.',
            'site_footer_text' => '© {year} Acme Ltd.',
            'site_footer_about' => 'Care you can trust.',
            'site_facebook_url' => 'https://facebook.com/acme',
        ])->assertSessionHasNoErrors();

        $this->get('/')->assertOk()
            ->assertSee('Brand new headline')->assertSee('See our plans')->assertSee('Join today')->assertSee('Pick your pack')
            ->assertSee('<title>Acme | Home</title>', false)->assertSee('content="The best care."', false)
            ->assertSee('© '.date('Y').' Acme Ltd.')->assertSee('Care you can trust.')
            ->assertSee('https://facebook.com/acme', false);
    }

    public function test_networks_without_an_address_are_not_shown_in_the_footer(): void
    {
        $this->save(['site_facebook_url' => '', 'site_instagram_url' => '', 'site_twitter_url' => ''])->assertSessionHasNoErrors();

        $this->get('/')->assertDontSee('target="_blank" rel="noopener" class="w-8 h-8', false);
    }

    public function test_the_brand_name_is_required(): void
    {
        $this->actingAs($this->admin, 'web')->put(route('admin.site-content.update'), ['site_name' => ''])->assertSessionHasErrors('site_name');
    }

    public function test_only_people_with_the_website_permission_can_change_it(): void
    {
        $sub = fn (array $perms) => User::create(['name' => 'S', 'email' => 's'.random_int(1, 9999).'@t.com', 'password' => 'secret123', 'role' => 'sub_admin', 'permissions' => $perms, 'is_active' => true]);

        $this->actingAs($sub(['orders']), 'web')->put(route('admin.site-content.update'), ['site_name' => 'Hacked'])->assertForbidden();
        $this->actingAs($sub(['content']), 'web')->put(route('admin.site-content.update'), ['site_name' => 'Allowed Name'])->assertSessionHasNoErrors();

        $this->assertSame('Allowed Name', Site::name());
    }
}
