<?php

namespace Tests\Feature;

use App\Mail\BrandedMail;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Rich-text (Quill) descriptions for products, plans and announcements, in the admin forms, on the pages and in emails. */
class RichEditorTest extends TestCase
{
    use RefreshDatabase;

    private const DIRTY = '<p>Daily <strong>wellness</strong> blend</p><ul><li>Boosts immunity</li></ul><script>alert(1)</script><img src=x onerror=alert(2)><p onclick="alert(3)">Safe</p>';

    private const CLEAN = '<p>Daily <strong>wellness</strong> blend</p><ul><li>Boosts immunity</li></ul><p>Safe</p>';

    private User $admin;

    private Region $region;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->region = Region::where('code', 'MAIN')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@t.com', 'password' => 'secret123', 'role' => 'admin', 'is_active' => true]);
        $this->cat = Category::create(['name' => 'W', 'slug' => 'w', 'is_free' => false]);
    }

    private function product(array $extra = []): Product
    {
        return Product::create($extra + ['category_id' => $this->cat->id, 'name' => 'Syrup', 'sku' => 'S'.random_int(1, 999999), 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => true]);
    }

    private function plan(array $extra = []): Plan
    {
        $plan = Plan::create($extra + ['name' => 'Essential', 'slug' => 'e'.random_int(1, 999999), 'price' => 2000, 'bv' => 800, 'is_active' => true, 'region_id' => $this->region->id]);
        $plan->products()->attach($this->product()->id, ['qty' => 1]);

        return $plan;
    }

    private function member(string $status = 'active', ?string $email = null): Member
    {
        static $n = 0;
        $n++;

        return Member::create(['member_code' => 'RE'.$n, 'name' => 'Rich '.$n, 'phone' => '92'.str_pad((string) $n, 8, '0', STR_PAD_LEFT), 'email' => $email, 'status' => $status, 'region_id' => $this->region->id]);
    }

    private function productPayload(array $extra = []): array
    {
        return $extra + ['category_id' => $this->cat->id, 'name' => 'New Syrup', 'sku' => 'NEW1', 'description' => self::DIRTY, 'cost_price' => 100, 'sell_price' => 1000, 'gst_pct' => 12, 'is_active' => 1];
    }

    private function planPayload(array $extra = []): array
    {
        return $extra + ['region_id' => $this->region->id, 'name' => 'Festive', 'description' => self::DIRTY, 'price' => 3000, 'bv' => 1200, 'is_active' => 1, 'qty' => [Product::first()?->id ?? $this->product()->id => 1]];
    }

    // ------------------------------------------------------------------------------------------ the editor is on the forms

    public function test_every_description_box_in_the_admin_is_a_rich_editor(): void
    {
        $product = $this->product(['description' => '<p>Hi</p>']);
        $plan = $this->plan(['description' => '<p>Hi</p>']);

        $pages = [
            route('admin.products.create') => 'description',
            route('admin.products.edit', $product) => 'description',
            route('admin.plans.create') => 'description',
            route('admin.plans.edit', $plan) => 'description',
            route('admin.plans.create', ['clone' => $plan->id]) => 'description',
            route('admin.announcements.index') => 'body',
        ];

        foreach ($pages as $url => $field) {
            $this->actingAs($this->admin, 'web')->get($url)->assertOk()
                ->assertSee('data-rich-editor', false)
                ->assertSee('name="'.$field.'"', false)
                ->assertSee('quill@1.3.7/dist/quill.min.js', false)
                ->assertSee('quill@1.3.7/dist/quill.snow.css', false);
        }
    }

    public function test_the_editor_starts_with_the_stored_text_as_safe_html(): void
    {
        $product = $this->product(['description' => "Old plain text\nsecond line"]);
        $plan = $this->plan(['description' => '<p>Rich <strong>text</strong></p><script>alert(1)</script>']);

        $this->actingAs($this->admin, 'web')->get(route('admin.products.edit', $product))->assertOk()
            ->assertSee('value="'.e('<p>Old plain text<br />'."\n".'second line</p>').'"', false);
        $res = $this->actingAs($this->admin, 'web')->get(route('admin.plans.edit', $plan))->assertOk();
        $res->assertSee('value="'.e('<p>Rich <strong>text</strong></p>').'"', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_editor_is_built_once_per_page_and_scripts_are_loaded_once(): void
    {
        $html = $this->actingAs($this->admin, 'web')->get(route('admin.plans.create'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'quill@1.3.7/dist/quill.min.js'));
        $this->assertSame(1, substr_count($html, 'quill@1.3.7/dist/quill.snow.css'));
    }

    // ------------------------------------------------------------------------------------------ saving is cleaned on the server

    public function test_a_product_description_is_cleaned_when_saved(): void
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.products.store'), $this->productPayload())->assertSessionHasNoErrors();
        $this->assertSame(self::CLEAN, Product::where('sku', 'NEW1')->value('description'));

        $p = Product::where('sku', 'NEW1')->first();
        $this->actingAs($this->admin, 'web')->put(route('admin.products.update', $p), $this->productPayload(['description' => '<p>Updated <a href="javascript:alert(1)">link</a></p>']))->assertSessionHasNoErrors();
        $this->assertSame('<p>Updated link</p>', $p->fresh()->description);
    }

    public function test_a_plan_description_is_cleaned_when_saved_created_or_edited(): void
    {
        $this->product();
        $this->actingAs($this->admin, 'web')->post(route('admin.plans.store'), $this->planPayload())->assertSessionHasNoErrors();
        $plan = Plan::where('name', 'Festive')->firstOrFail();
        $this->assertSame(self::CLEAN, $plan->description);

        $this->actingAs($this->admin, 'web')->put(route('admin.plans.update', $plan), $this->planPayload(['description' => '<h1>Big</h1><iframe src="https://evil"></iframe>']))->assertSessionHasNoErrors();
        $this->assertSame('<h2>Big</h2>', $plan->fresh()->description);
    }

    public function test_an_announcement_is_cleaned_when_saved(): void
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.announcements.store'), ['title' => 'News', 'body' => self::DIRTY, 'audience' => 'all'])->assertSessionHasNoErrors();

        $this->assertSame(self::CLEAN, Announcement::firstOrFail()->body);
    }

    public function test_an_empty_editor_means_no_description_and_an_announcement_still_needs_words(): void
    {
        $this->actingAs($this->admin, 'web')->post(route('admin.products.store'), $this->productPayload(['description' => '<p><br></p>']))->assertSessionHasNoErrors();
        $this->assertNull(Product::where('sku', 'NEW1')->value('description'));

        $this->actingAs($this->admin, 'web')->post(route('admin.announcements.store'), ['title' => 'T', 'body' => '<p><br></p>', 'audience' => 'all'])->assertSessionHasErrors('body');
        $this->actingAs($this->admin, 'web')->post(route('admin.announcements.store'), ['title' => 'T', 'body' => '<script>x</script>', 'audience' => 'all'])->assertSessionHasErrors('body');
        $this->assertSame(0, Announcement::count());
    }

    public function test_length_limits_count_the_words_not_the_tags(): void
    {
        $long = '<p>'.str_repeat('a', 2001).'</p>';
        $heavy = str_repeat('<p><strong><em>a</em></strong></p>', 600);        // 600 letters wrapped in a lot of tags

        $this->actingAs($this->admin, 'web')->post(route('admin.products.store'), $this->productPayload(['description' => $long]))->assertSessionHasErrors('description');
        $this->actingAs($this->admin, 'web')->post(route('admin.products.store'), $this->productPayload(['description' => $heavy]))->assertSessionHasNoErrors();

        $this->product();
        $this->actingAs($this->admin, 'web')->post(route('admin.plans.store'), $this->planPayload(['description' => $long]))->assertSessionHasErrors('description');
        $this->actingAs($this->admin, 'web')->post(route('admin.announcements.store'), ['title' => 'T', 'body' => '<p>'.str_repeat('b', 3001).'</p>', 'audience' => 'all'])->assertSessionHasErrors('body');
    }

    // ------------------------------------------------------------------------------------------ shown safely everywhere

    public function test_plans_show_the_formatting_to_members_and_never_a_script_even_from_old_dirty_rows(): void
    {
        $this->plan(['description' => 'Plain old text']);
        $this->plan(['name' => 'Rich Plan', 'description' => '<p>With <strong>bold</strong> text</p>']);
        $dirty = $this->plan(['name' => 'Dirty Plan']);
        $dirty->forceFill(['description' => '<p>Hello</p><script>alert(1)</script><img src=x onerror=alert(2)>'])->save();   // written some other way
        $buyer = $this->member();

        $this->actingAs($buyer, 'member')->get(route('member.plans.index'))->assertOk()
            ->assertSee('<strong>bold</strong>', false)->assertSee('<p>Plain old text</p>', false)->assertSee('<p>Hello</p>', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('onerror', false);
    }

    public function test_the_homepage_and_lists_show_a_plain_short_version(): void
    {
        $this->plan(['name' => 'Rich Plan', 'description' => '<p>With <strong>bold</strong> text</p><ul><li>Item</li></ul>']);

        $this->get(route('home'))->assertOk()->assertSee('With bold text Item')->assertDontSee('<strong>bold</strong>', false);

        $this->product(['name' => 'Listed', 'description' => '<p>Product <strong>copy</strong></p>']);
        $this->actingAs($this->admin, 'web')->get(route('admin.products.index'))->assertOk()->assertSee('Product copy')->assertDontSee('<strong>copy</strong>', false);
        $this->actingAs($this->admin, 'web')->get(route('admin.plans.index'))->assertOk()->assertDontSee('<strong>bold</strong>', false);
    }

    public function test_the_checkout_page_shows_the_plan_formatting_and_a_plain_product_line(): void
    {
        $plan = $this->plan(['description' => '<p>Plan <strong>details</strong></p>']);
        $plan->products()->first()->update(['description' => '<p>Product <em>note</em></p><script>x</script>']);
        $m = $this->member('inactive');
        $order = app(\App\Services\OrderService::class)->place($m, $plan);

        $this->actingAs($m, 'member')->get(route('member.orders.pay', $order))->assertOk()
            ->assertSee('<strong>details</strong>', false)->assertSee('Product note')->assertDontSee('<script>x</script>', false);
    }

    public function test_announcements_show_on_the_dashboard_with_their_formatting_and_without_scripts(): void
    {
        Announcement::create(['title' => 'Offer', 'audience' => 'all', 'body' => '<p>Save <strong>20%</strong> today</p><ol><li>Buy</li></ol><script>alert(1)</script>']);
        Announcement::create(['title' => 'Old style', 'audience' => 'all', 'body' => "Plain line\n\nSecond paragraph"]);

        $this->actingAs($this->member(), 'member')->get(route('member.dashboard'))->assertOk()
            ->assertSee('<strong>20%</strong>', false)->assertSee('<ol><li>Buy</li></ol>', false)
            ->assertSee('<p>Plain line</p><p>Second paragraph</p>', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    // ------------------------------------------------------------------------------------------ emails

    private function mails(string $email)
    {
        return Mail::sent(BrandedMail::class)->filter(fn (BrandedMail $m) => $m->hasTo($email))->values();
    }

    public function test_an_announcement_email_carries_the_formatting_and_no_script(): void
    {
        $this->member('active', 'amit@example.com');

        $this->actingAs($this->admin, 'web')->post(route('admin.announcements.store'), ['title' => 'Diwali', 'body' => self::DIRTY, 'audience' => 'all', 'send_email' => 1]);

        $html = $this->mails('amit@example.com')->first()->render();
        $this->assertStringContainsString('<strong>wellness</strong>', $html);
        $this->assertStringContainsString('<li>Boosts immunity</li>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('/email/unsubscribe/', $html);
    }

    public function test_an_old_plain_announcement_still_emails_as_paragraphs(): void
    {
        $m = $this->member('active', 'amit@example.com');
        $a = Announcement::create(['title' => 'Plain', 'audience' => 'all', 'body' => "First para\n\nSecond para"]);

        app(\App\Services\MemberMailer::class)->announcement($a, $m);

        $html = $this->mails('amit@example.com')->first()->render();
        $this->assertStringContainsString('<p>First para</p>', $html);
        $this->assertStringContainsString('<p>Second para</p>', $html);
    }

    public function test_the_new_plan_email_shows_the_plan_description_formatted(): void
    {
        $this->member('active', 'amit@example.com');
        $this->product();

        $this->actingAs($this->admin, 'web')->post(route('admin.plans.store'), $this->planPayload(['notify_members' => 1]));

        $html = $this->mails('amit@example.com')->first(fn ($m) => str_contains($m->mailSubject, 'Festive'))->render();
        $this->assertStringContainsString('<strong>wellness</strong>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_email_text_that_people_type_elsewhere_is_still_escaped(): void
    {
        $m = $this->member('active', 'amit@example.com');
        $m->update(['name' => '<script>alert(9)</script>']);
        app(\App\Services\OrderService::class)->place($m->fresh(), $this->plan());

        $html = $this->mails('amit@example.com')->first()->render();
        $this->assertStringNotContainsString('<script>alert(9)</script>', $html);
    }

    // ------------------------------------------------------------------------------------------ cloning keeps it

    public function test_cloning_a_plan_keeps_its_formatted_description(): void
    {
        $plan = $this->plan(['description' => '<p>Keep <strong>this</strong></p>']);

        $this->actingAs($this->admin, 'web')->get(route('admin.plans.create', ['clone' => $plan->id]))->assertOk()
            ->assertSee('value="'.e('<p>Keep <strong>this</strong></p>').'"', false);
    }
}
