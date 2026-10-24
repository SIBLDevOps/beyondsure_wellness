<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

/**
 * Everything on the public website that the admin can change without a deploy: the brand (name, tagline, logos,
 * favicon), the homepage copy and buttons, contact details, social links, the footer and the search-engine text.
 */
class SiteContentController extends Controller
{
    /** group => [title, icon, hint, fields]; field = key => [label, default, 'text'|'textarea'] */
    public const GROUPS = [
        'brand' => ['Brand', 'bi-stars', 'The name and tagline shown in the header, sign-in pages and the member and admin areas.', [
            'site_name' => ['Brand name', 'BeyondSure+', 'text'],
            'site_tagline' => ['Tagline', 'Wellness & Protection Plans', 'text'],
        ]],
        'hero' => ['Homepage top section', 'bi-window-desktop', 'The big banner visitors see first.', [
            'site_hero_badge' => ['Small badge text', 'Wellness & Protection, Together', 'text'],
            'site_hero_title' => ['Headline', 'Everyday wellness plans built for your whole life', 'text'],
            'site_hero_subtitle' => ['Sub-text', 'Curated wellness products, complimentary health benefits, and a partner community that grows with you — all in one simple plan.', 'textarea'],
            'site_hero_cta_primary' => ['Main button label', 'Explore plans', 'text'],
            'site_hero_cta_secondary' => ['Second button label', 'Get started free', 'text'],
        ]],
        'about' => ['About, vision and promise', 'bi-info-circle', 'The text sections further down the homepage.', [
            'site_about_text_1' => ['About — paragraph 1', 'BeyondSure+ brings together everyday wellness products, complimentary health services, and protection cover in a single membership. Every plan is built around what real households actually use — immunity support, pain relief, detox nutrition — bundled with digital health access and emergency assistance you can lean on.', 'textarea'],
            'site_about_text_2' => ['About — paragraph 2', "We believe wellness shouldn't be complicated or expensive. That's why every plan bundle is transparent about what's inside, what it costs, and what free benefits come attached — no fine print, no surprises.", 'textarea'],
            'site_vision_text' => ['Vision statement', 'We envision a world where preventive wellness is a habit, not an afterthought — where every family has affordable access to the health products and protection they need, without ever feeling like a burden.', 'textarea'],
            'site_mission_text' => ['Mission statement', 'Deliver honest, high-quality wellness bundles and complimentary care benefits that fit real life — and reward the community that helps us grow.', 'textarea'],
            'site_promise_text' => ['Promise statement', 'Every plan, every payout, every benefit — tracked transparently, dispatched on schedule, and always explainable.', 'textarea'],
        ]],
        'plans' => ['Plans section heading', 'bi-layers', 'The heading above the plan cards. The plans themselves are managed under Plans.', [
            'site_plans_eyebrow' => ['Small label', 'Plans', 'text'],
            'site_plans_title' => ['Heading', 'A bundle for every household', 'text'],
            'site_plans_subtitle' => ['Sub-text', 'Transparent pricing, real wellness products, complimentary benefits included.', 'text'],
        ]],
        'contact' => ['Contact details', 'bi-telephone', 'Shown in the Contact section and the footer.', [
            'site_contact_address' => ['Address', 'Wellness Tower, MG Road, Bengaluru, Karnataka 560001', 'textarea'],
            'site_contact_phone' => ['Phone', '+91 11 4000 1234', 'text'],
            'site_contact_email' => ['Email', 'support@beyondsure.example', 'text'],
            'site_contact_hours' => ['Opening hours', 'Mon–Sat, 9:00 AM – 7:00 PM IST', 'text'],
        ]],
        'social' => ['Social links', 'bi-share', 'Full web addresses. A network with no address is not shown in the footer.', [
            'site_facebook_url' => ['Facebook', '', 'text'],
            'site_instagram_url' => ['Instagram', '', 'text'],
            'site_twitter_url' => ['Twitter / X', '', 'text'],
        ]],
        'footer' => ['Footer', 'bi-layout-text-window-reverse', 'The text at the bottom of every public page.', [
            'site_footer_about' => ['Short description', 'Wellness and protection plans built around everyday health — complimentary services, curated wellness products, and a rewarding partner community.', 'textarea'],
            'site_footer_text' => ['Copyright line', '© {year} BeyondSure Wellness Pvt. Ltd. All rights reserved.', 'text'],
        ]],
        'seo' => ['Search engines (Google)', 'bi-search', 'How the homepage appears in search results and browser tabs. Leave blank to use the brand name and tagline.', [
            'site_meta_title' => ['Page title', '', 'text'],
            'site_meta_description' => ['Description', 'BeyondSure+ wellness and protection plans — complimentary health benefits, curated wellness products, and a rewarding partner community.', 'textarea'],
        ]],
    ];

    /** The upload slots: key => [label, hint, allowed types, max KB] */
    public const IMAGES = [
        'site_logo' => ['Logo', 'Shown on light backgrounds: the public header and the sign-in pages.', 'png,jpg,jpeg,webp,gif', 2048],
        'site_logo_dark' => ['Logo for dark backgrounds', 'Optional. Shown in the dark side menus of the member and admin areas. If empty, the main logo is used.', 'png,jpg,jpeg,webp,gif', 2048],
        'site_favicon' => ['Favicon', 'The small icon in the browser tab. Square works best (at least 64×64).', 'png,jpg,jpeg,webp,gif,ico', 512],
    ];

    /** Flat key => [label, default] for every text field (used by the homepage to read its content). */
    public static function fields(): array
    {
        $flat = [];
        foreach (self::GROUPS as [, , , $fields]) {
            foreach ($fields as $key => [$label, $default]) {
                $flat[$key] = [$label, $default];
            }
        }

        return $flat;
    }

    public function edit(): View
    {
        $values = collect(self::fields())->mapWithKeys(fn ($f, $key) => [$key => Setting::get($key, $f[1])]);

        return view('admin.site-content.edit', [
            'groups' => self::GROUPS,
            'images' => self::IMAGES,
            'values' => $values,
            'imageUrls' => [
                'site_logo' => Site::logoUrl(),
                'site_logo_dark' => $this->ownUrl('site_logo_dark'),
                'site_favicon' => Site::faviconUrl(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = collect(self::fields())->mapWithKeys(fn ($f, $key) => [$key => ['nullable', 'string', 'max:2000']])->all();
        $rules['site_name'] = ['required', 'string', 'max:60'];

        foreach (self::IMAGES as $key => [, , $types, $maxKb]) {
            $rules[$key] = ['nullable', 'file', 'image', 'mimes:'.$types, 'max:'.$maxKb];
        }

        $data = $request->validate($rules, [
            'site_name.required' => 'The brand name cannot be empty.',
            '*.mimes' => 'That file type is not allowed. Use a PNG, JPG, WEBP or GIF image.',
            '*.image' => 'The file must be an image.',
            '*.max' => 'That image is too large.',
        ]);

        foreach (array_keys(self::fields()) as $key) {
            Setting::set($key, $data[$key] ?? '');
        }

        foreach (array_keys(self::IMAGES) as $key) {
            if ($request->boolean('remove_'.$key)) {
                $this->deleteFile($key);
                Setting::set($key, '');
            }
            if ($request->hasFile($key)) {
                $this->deleteFile($key);
                Setting::set($key, $this->storeFile($request->file($key), $key));
            }
        }

        return back()->with('status', 'Website settings saved. They are live on the site now.');
    }

    private function storeFile(UploadedFile $file, string $key): string
    {
        $dir = public_path(Site::DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = str_replace('site_', '', $key).'-'.bin2hex(random_bytes(6)).'.'.strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $file->move($dir, $name);

        return $name;
    }

    private function deleteFile(string $key): void
    {
        $current = Setting::get($key);

        if ($current && is_file($path = public_path(Site::DIR.'/'.basename((string) $current)))) {
            @unlink($path);
        }
    }

    private function ownUrl(string $key): ?string
    {
        $file = Setting::get($key);

        return ($file && is_file(public_path(Site::DIR.'/'.$file))) ? asset(Site::DIR.'/'.$file) : null;
    }
}
