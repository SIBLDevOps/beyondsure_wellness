<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Member;
use App\Models\MemberLegTotal;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDownlineViewTest extends TestCase
{
    use RefreshDatabase;

    private Member $root;

    private Member $leftChild;

    private Member $rightChild;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('self_pct', 10);
        Setting::set('sponsor_pct', 20);
        Setting::set('matching_pct', 10);
        Setting::set('cascade_depth', 7);

        $category = Category::create(['name' => 'Wellness', 'slug' => 'wellness', 'is_free' => false]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Immunity Booster',
            'sku' => 'IMM-1',
            'cost_price' => 500,
            'sell_price' => 1000,
            'is_active' => true,
        ]);
        $plan = Plan::create([
            'name' => 'Starter Pack',
            'slug' => 'starter-pack',
            'price' => 1000,
            'bv' => 400,
            'is_active' => true,
        ]);
        $plan->products()->attach($product->id);

        $this->root = Member::create([
            'name' => 'Root Leader',
            'phone' => '9876543210',
            'email' => 'root@example.com',
            'member_code' => 'BSROOT01',
            'status' => 'active',
            'path' => '',
        ]);

        MemberLegTotal::create([
            'member_id' => $this->root->id,
            'left_bv' => 1200,
            'right_bv' => 800,
            'matched_bv' => 800,
            'direct_bv' => 400,
        ]);

        $this->leftChild = Member::create([
            'name' => 'Left Partner',
            'phone' => '9876543211',
            'email' => 'left@example.com',
            'member_code' => 'BSLEFT01',
            'sponsor_id' => $this->root->id,
            'placement_id' => $this->root->id,
            'position' => 'left',
            'status' => 'active',
            'path' => $this->root->id.'.',
        ]);

        $this->rightChild = Member::create([
            'name' => 'Right Partner',
            'phone' => '9876543212',
            'email' => 'right@example.com',
            'member_code' => 'BSRIGHT01',
            'sponsor_id' => $this->root->id,
            'placement_id' => $this->root->id,
            'position' => 'right',
            'status' => 'active',
            'path' => $this->root->id.'.',
        ]);
    }

    public function test_guest_is_redirected_to_login_when_visiting_downline(): void
    {
        $response = $this->get('/downline');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_member_can_view_downline_tree_and_kpis(): void
    {
        $response = $this->actingAs($this->root, 'member')->get('/downline');

        $response->assertOk();
        $response->assertSee('Binary Genealogy Tree');
        $response->assertSee('Team Binary Genealogy Tree');
        $response->assertSee('LEFT LEG');
        $response->assertSee('RIGHT LEG');
        $response->assertSee('1:1 MATCH');
        $response->assertSee('Root Leader');
        $response->assertSee('Left Partner');
        $response->assertSee('Right Partner');
        $response->assertSee('₹1,200');
        $response->assertSee('₹800');
        $response->assertSee('Carry:');
        $response->assertSee('memberBtnCenter');
    }

    public function test_downline_supports_custom_depth_query(): void
    {
        $response = $this->actingAs($this->root, 'member')->get('/downline?depth=3');

        $response->assertOk();
        $response->assertSee('3 Levels');
    }

    public function test_network_subnav_tabs_render_cleanly_on_all_pages(): void
    {
        $downline = $this->actingAs($this->root, 'member')->get('/downline');
        $downline->assertOk();
        $downline->assertSee('Binary Genealogy Tree');

        $team = $this->actingAs($this->root, 'member')->get('/team');
        $team->assertOk();
        $team->assertSee('Direct Sponsored Team');

        $levels = $this->actingAs($this->root, 'member')->get('/levels');
        $levels->assertOk();
        $levels->assertSee('Level-Wise Income');
    }
}
