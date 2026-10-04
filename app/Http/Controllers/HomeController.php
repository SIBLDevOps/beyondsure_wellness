<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $plans = Plan::where('is_active', true)->with('products')->orderBy('price')->get();

        $benefitGroups = Product::whereNotNull('benefit_group')
            ->select('benefit_group')
            ->distinct()
            ->orderBy('benefit_group')
            ->pluck('benefit_group');

        $testimonials = Testimonial::where('is_active', true)->orderBy('sort_order')->get();

        $content = collect([
            'site_hero_badge' => 'Wellness & Protection, Together',
            'site_hero_title' => 'Everyday wellness plans built for your whole life',
            'site_hero_subtitle' => 'Curated wellness products, complimentary health benefits, and a partner community that grows with you — all in one simple plan.',
            'site_about_text_1' => 'BeyondSure+ brings together everyday wellness products, complimentary health services, and protection cover in a single membership. Every plan is built around what real households actually use — immunity support, pain relief, detox nutrition — bundled with digital health access and emergency assistance you can lean on.',
            'site_about_text_2' => "We believe wellness shouldn't be complicated or expensive. That's why every plan bundle is transparent about what's inside, what it costs, and what free benefits come attached — no fine print, no surprises.",
            'site_vision_text' => 'We envision a world where preventive wellness is a habit, not an afterthought — where every family has affordable access to the health products and protection they need, without ever feeling like a burden.',
            'site_mission_text' => 'Deliver honest, high-quality wellness bundles and complimentary care benefits that fit real life — and reward the community that helps us grow.',
            'site_promise_text' => 'Every plan, every payout, every benefit — tracked transparently, dispatched on schedule, and always explainable.',
            'site_contact_address' => 'Wellness Tower, MG Road, Bengaluru, Karnataka 560001',
            'site_contact_phone' => '+91 11 4000 1234',
            'site_contact_email' => 'support@beyondsure.example',
            'site_contact_hours' => 'Mon–Sat, 9:00 AM – 7:00 PM IST',
            'site_facebook_url' => '',
            'site_instagram_url' => '',
            'site_twitter_url' => '',
        ])->mapWithKeys(fn ($default, $key) => [$key => Setting::get($key, $default)]);

        return view('home', [
            'plans' => $plans,
            'benefitGroups' => $benefitGroups,
            'testimonials' => $testimonials,
            'content' => $content,
            'memberCount' => Member::count(),
            'planCount' => $plans->count(),
        ]);
    }
}
