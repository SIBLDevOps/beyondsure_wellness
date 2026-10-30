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
            'site_primary_color' => ['Brand colour', '#059669', 'color'],
            'site_admin_color' => ['Admin panel colour', '#4f46e5', 'color'],
            'site_soft_surface' => ['Page background', '0', 'surface'],
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

    /**
     * Where members send money for a manual (UPI / bank) payment. Kept apart from GROUPS so it is never read by the
     * public homepage, and only people with Finance access can see or change it: pointing members' money at another
     * account is exactly what a bad actor would try.
     */
    public const PAYMENT = ['Payment details', 'bi-bank', 'Shown to members on the checkout page when they pay by UPI or bank transfer. Leave a field empty to hide it.', [
        'pay_upi_id' => ['UPI ID', '', 'text'],
        'pay_account_name' => ['Account holder name', '', 'text'],
        'pay_bank_name' => ['Bank name', '', 'text'],
        'pay_account_number' => ['Account number', '', 'text'],
        'pay_ifsc' => ['IFSC code', '', 'text'],
        'pay_note' => ['Note for members', '', 'textarea'],
    ]];

    /**
     * The company's legal details printed on every tax invoice. Finance access only, like the payment details, and
     * kept out of GROUPS so the public homepage never reads them.
     */
    public const INVOICE = ['Invoice details', 'bi-receipt-cutoff', 'Printed on every invoice, next to your logo. Without a GSTIN the document is titled "Invoice" instead of "Tax Invoice".', [
        'inv_legal_name' => ['Legal company name', '', 'text'],
        'inv_gstin' => ['GSTIN', '', 'text'],
        'inv_pan' => ['PAN', '', 'text'],
        'inv_address' => ['Registered address', '', 'textarea'],
        'inv_state' => ['State', '', 'text'],
        'inv_tax_type' => ['GST shown as', 'cgst_sgst', 'taxtype'],
        'inv_prefix' => ['Invoice number prefix', 'INV', 'text'],
        'inv_terms' => ['Terms and conditions', '', 'textarea'],
    ]];

    /** The two colour settings: the website, sign-in pages and member area, and the admin panel. */
    public const COLOUR_KEYS = ['site_primary_color', 'site_admin_color'];

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

    /** key => [label, default] for the payment details. */
    public static function paymentFields(): array
    {
        return collect(self::PAYMENT[3])->map(fn ($f) => [$f[0], $f[1]])->all();
    }

    /** key => [label, default] for the invoice details. */
    public static function invoiceFields(): array
    {
        return collect(self::INVOICE[3])->map(fn ($f) => [$f[0], $f[1]])->all();
    }

    public function edit(): View
    {
        $groups = self::GROUPS;
        $fields = self::fields();

        if (request()->user()?->canSeeFinance()) {
            $groups['payment'] = self::PAYMENT;
            $groups['invoice'] = self::INVOICE;
            $fields += self::paymentFields() + self::invoiceFields();
        }

        $values = collect($fields)->mapWithKeys(fn ($f, $key) => [$key => (Setting::get($key) ?? $f[1])]);

        return view('admin.site-content.edit', [
            'groups' => $groups,
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

        // Payment details: only for people with Finance access. For everyone else these inputs are ignored entirely.
        $finance = (bool) $request->user()?->canSeeFinance();
        if ($finance) {
            $request->merge([
                'pay_ifsc' => strtoupper(trim((string) $request->input('pay_ifsc', ''))),
                'inv_gstin' => strtoupper(trim((string) $request->input('inv_gstin', ''))),
                'inv_pan' => strtoupper(trim((string) $request->input('inv_pan', ''))),
            ]);
            $rules += [
                'inv_legal_name' => ['nullable', 'string', 'max:150'],
                'inv_gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
                'inv_pan' => ['nullable', 'regex:/^[A-Z]{5}\d{4}[A-Z]$/'],
                'inv_address' => ['nullable', 'string', 'max:500'],
                'inv_state' => ['nullable', 'string', 'max:60'],
                'inv_tax_type' => ['nullable', 'in:cgst_sgst,igst'],
                'inv_prefix' => ['nullable', 'regex:/^[A-Za-z0-9\-]{1,10}$/'],
                'inv_terms' => ['nullable', 'string', 'max:1000'],
                'pay_upi_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]{2,64}@[A-Za-z0-9]{2,32}$/'],
                'pay_account_name' => ['nullable', 'string', 'max:120'],
                'pay_bank_name' => ['nullable', 'string', 'max:120'],
                'pay_account_number' => ['nullable', 'regex:/^\d{6,20}$/'],
                'pay_ifsc' => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
                'pay_note' => ['nullable', 'string', 'max:500'],
            ];
        }
        // Links go into href attributes on the public site, so only real web addresses are accepted
        // (a "javascript:" or "data:" address would run code when a visitor clicks it).
        // The brand colour is written into the stylesheet, so it must be a real hex value, and dark enough for white text.
        // The same applies to the admin panel colour. An empty box is allowed and means "back to the default".
        foreach (self::COLOUR_KEYS as $colourKey) {
            $rules[$colourKey] = ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                $hex = \App\Support\Theme::normalize((string) $value);
                if ($hex === null) {
                    $fail('Enter a colour like #059669 (a # followed by 3 or 6 letters or digits).');
                } elseif (! \App\Support\Theme::isUsable($hex)) {
                    $fail('That colour is too light. White text on it would be hard to read, so pick a darker one.');
                }
            }];
        }
        $rules['site_soft_surface'] = ['nullable', 'in:0,1'];
        foreach (['site_facebook_url', 'site_instagram_url', 'site_twitter_url'] as $urlKey) {
            $rules[$urlKey] = ['nullable', 'url:http,https', 'max:255'];
        }

        foreach (self::IMAGES as $key => [, , $types, $maxKb]) {
            $rules[$key] = ['nullable', 'file', 'image', 'mimes:'.$types, 'max:'.$maxKb];
        }

        $data = $request->validate($rules, [
            'site_name.required' => 'The brand name cannot be empty.',
            'inv_gstin.regex' => 'A GSTIN is 15 characters, for example 24ABCDE1234F1Z5.',
            'inv_pan.regex' => 'A PAN is 5 letters, 4 digits and 1 letter, for example ABCDE1234F.',
            'inv_tax_type.in' => 'Choose either CGST + SGST or IGST.',
            'inv_prefix.regex' => 'The prefix can have up to 10 letters, digits or dashes, and no spaces.',
            'pay_upi_id.regex' => 'Enter a UPI ID like name@bank.',
            'pay_account_number.regex' => 'The account number must be 6 to 20 digits, with no spaces.',
            'pay_ifsc.regex' => 'An IFSC code is 4 letters, a 0, then 6 letters or digits (for example HDFC0001234).',
            '*_url.url' => 'Enter a full web address starting with https:// (or leave it empty).',
            '*.mimes' => 'That file type is not allowed. Use a PNG, JPG, WEBP or GIF image.',
            '*.image' => 'The file must be an image.',
            '*.max' => 'That image is too large.',
        ]);

        // Store the colour in one clean form; an empty box puts the default brand colour back.
        foreach (self::COLOUR_KEYS as $colourKey) {
            $data[$colourKey] = \App\Support\Theme::normalize((string) ($data[$colourKey] ?? '')) ?? '';
        }

        foreach (array_keys(self::fields()) as $key) {
            Setting::set($key, $data[$key] ?? '');
        }

        if ($finance) {
            foreach (array_keys(self::paymentFields() + self::invoiceFields()) as $key) {
                Setting::set($key, $data[$key] ?? '');
            }
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
