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
        $plans = Plan::where('is_active', true)
            ->whereHas('region', fn ($q) => $q->where('is_active', true))
            ->with(['products', 'region'])->orderBy('region_id')->orderBy('price')->get();

        $benefitGroups = Product::whereNotNull('benefit_group')
            ->select('benefit_group')
            ->distinct()
            ->orderBy('benefit_group')
            ->pluck('benefit_group');

        $testimonials = Testimonial::where('is_active', true)->orderBy('sort_order')->get();

        // One source of truth for the editable text and its defaults: the admin Website settings page.
        $content = collect(\App\Http\Controllers\Admin\SiteContentController::fields())
            ->mapWithKeys(fn ($f, $key) => [$key => Setting::get($key, $f[1])]);

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
