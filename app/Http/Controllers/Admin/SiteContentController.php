<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteContentController extends Controller
{
    /** key => [label, default] */
    private const FIELDS = [
        'site_hero_badge' => ['Hero badge text', 'Wellness & Protection, Together'],
        'site_hero_title' => ['Hero headline', 'Everyday wellness plans built for your whole life'],
        'site_hero_subtitle' => ['Hero subtext', 'Curated wellness products, complimentary health benefits, and a partner community that grows with you — all in one simple plan.'],
        'site_about_text_1' => ['About — paragraph 1', 'BeyondSure+ brings together everyday wellness products, complimentary health services, and protection cover in a single membership. Every plan is built around what real households actually use — immunity support, pain relief, detox nutrition — bundled with digital health access and emergency assistance you can lean on.'],
        'site_about_text_2' => ['About — paragraph 2', "We believe wellness shouldn't be complicated or expensive. That's why every plan bundle is transparent about what's inside, what it costs, and what free benefits come attached — no fine print, no surprises."],
        'site_vision_text' => ['Vision statement', 'We envision a world where preventive wellness is a habit, not an afterthought — where every family has affordable access to the health products and protection they need, without ever feeling like a burden.'],
        'site_mission_text' => ['Mission statement', 'Deliver honest, high-quality wellness bundles and complimentary care benefits that fit real life — and reward the community that helps us grow.'],
        'site_promise_text' => ['Promise statement', 'Every plan, every payout, every benefit — tracked transparently, dispatched on schedule, and always explainable.'],
        'site_contact_address' => ['Contact — address', 'Wellness Tower, MG Road, Bengaluru, Karnataka 560001'],
        'site_contact_phone' => ['Contact — phone', '+91 11 4000 1234'],
        'site_contact_email' => ['Contact — email', 'support@beyondsure.example'],
        'site_contact_hours' => ['Contact — hours', 'Mon–Sat, 9:00 AM – 7:00 PM IST'],
        'site_facebook_url' => ['Facebook URL', ''],
        'site_instagram_url' => ['Instagram URL', ''],
        'site_twitter_url' => ['Twitter / X URL', ''],
    ];

    public function edit(): View
    {
        $values = collect(self::FIELDS)->mapWithKeys(fn ($f, $key) => [$key => Setting::get($key, $f[1])]);

        return view('admin.site-content.edit', [
            'fields' => self::FIELDS,
            'values' => $values,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = collect(self::FIELDS)->mapWithKeys(fn ($f, $key) => [$key => ['nullable', 'string', 'max:2000']])->all();
        $data = $request->validate($rules);

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('status', 'Homepage content updated.');
    }
}
