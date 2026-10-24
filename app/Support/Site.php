<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Everything about the website's identity that the admin can change: brand name, tagline, logos and favicon.
 * Uploaded images live in public/uploads/branding (so no storage link is needed on the server).
 */
class Site
{
    public const DIR = 'uploads/branding';

    /** Setting::get already caches, so no extra memo (a stale one would also break tests that change settings). */
    private static function setting(string $key, ?string $default = null): ?string
    {
        // A page must still render with the built-in defaults if the settings table is not reachable
        // (for example right after a deploy, before the migrations have run).
        try {
            $value = Setting::get($key, $default);
        } catch (\Throwable) {
            $value = null;
        }

        return ($value === null || $value === '') ? $default : (string) $value;
    }

    /** Any editable text from Website settings, or its built-in default. {year} becomes the current year. */
    public static function text(string $key): string
    {
        $default = \App\Http\Controllers\Admin\SiteContentController::fields()[$key][1] ?? '';

        return str_replace('{year}', date('Y'), (string) self::setting($key, $default));
    }

    /** The browser-tab title and search-result title of the homepage. */
    public static function metaTitle(): string
    {
        return self::setting('site_meta_title') ?: self::name().' — '.self::tagline();
    }

    public static function name(): string
    {
        return self::setting('site_name', 'BeyondSure+');
    }

    public static function tagline(): string
    {
        return self::setting('site_tagline', 'Wellness & Protection Plans');
    }

    /** The brand name without a trailing "+", and whether it had one (the "+" is drawn in the accent colour). */
    public static function nameParts(): array
    {
        $name = self::name();

        return str_ends_with($name, '+') ? [rtrim($name, '+'), '+'] : [$name, ''];
    }

    /** Logo for light backgrounds (public site, sign-in pages). */
    public static function logoUrl(): ?string
    {
        return self::fileUrl('site_logo');
    }

    /** Logo for dark backgrounds (admin and member sidebars). Falls back to the main logo. */
    public static function logoDarkUrl(): ?string
    {
        return self::fileUrl('site_logo_dark') ?? self::logoUrl();
    }

    public static function faviconUrl(): ?string
    {
        return self::fileUrl('site_favicon');
    }

    private static function fileUrl(string $key): ?string
    {
        $file = self::setting($key);

        if (! $file || ! is_file(public_path(self::DIR.'/'.$file))) {
            return null;
        }

        return asset(self::DIR.'/'.$file);
    }
}
