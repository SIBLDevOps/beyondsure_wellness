<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The one brand colour. The admin picks a single colour in Website settings and everything is derived from it: an
 * 11-step light-to-dark scale used by the public site (Tailwind) and by the admin and member areas (Bootstrap), the
 * button and link colours, and the dark side menus. Only a validated hex value ever reaches the page, so a stored value
 * can never inject anything into the stylesheet.
 */
class Theme
{
    /** Emerald, the colour the site has always used. Shade 600. */
    public const DEFAULT = '#059669';

    /** Blue (indigo), the colour the admin area has always had. Shade 600. */
    public const ADMIN_DEFAULT = '#4f46e5';

    /** The admin default scale: Tailwind indigo, with the two darkest steps set to the original navy of the side menu. */
    private const ADMIN_SCALE = [
        50 => '#eef2ff', 100 => '#e0e7ff', 200 => '#c7d2fe', 300 => '#a5b4fc', 400 => '#818cf8', 500 => '#6366f1',
        600 => '#4f46e5', 700 => '#4338ca', 800 => '#3730a3', 900 => '#1e1b4b', 950 => '#14123a',
    ];

    /** The default scale is kept exactly as the public site already uses it. */
    private const DEFAULT_SCALE = [
        50 => '#ecfdf5', 100 => '#d1fae5', 200 => '#a7f3d0', 300 => '#6ee7b7', 400 => '#34d399', 500 => '#10b981',
        600 => '#059669', 700 => '#047857', 800 => '#065f46', 900 => '#064e3b', 950 => '#022c22',
    ];

    /** Muted (secondary) text colour used with the soft surfaces; AA contrast (4.5:1) on both the page and the cards. */
    public const MUTED = '#55657a';

    /** Text on the brand colour must reach at least this contrast against white (WCAG large-text level). */
    private const MIN_WHITE_CONTRAST = 3.0;

    private const DARK_TEXT = '#0f172a';

    /** Accepts #rgb or #rrggbb (any case, surrounding spaces ignored) and returns #rrggbb in lowercase, or null. */
    public static function normalize(?string $hex): ?string
    {
        if ($hex === null) {
            return null;
        }

        $hex = trim($hex);

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $hex, $m)) {
            $hex = '#'.$m[1][0].$m[1][0].$m[1][1].$m[1][1].$m[1][2].$m[1][2];
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/D', $hex) ? strtolower($hex) : null;
    }

    /** True when white text on this colour is readable. */
    public static function isUsable(string $hex): bool
    {
        return self::contrast($hex, '#ffffff') >= self::MIN_WHITE_CONTRAST;
    }

    /** The saved brand colour, or the default when none is saved or the saved value is not usable. */
    public static function primary(): string
    {
        try {
            $saved = self::normalize((string) Setting::get('site_primary_color'));
        } catch (\Throwable) {
            $saved = null;
        }

        return ($saved && self::isUsable($saved)) ? $saved : self::DEFAULT;
    }

    /** The colour of the admin area (its own setting, blue by default). */
    public static function adminPrimary(): string
    {
        try {
            $saved = self::normalize((string) Setting::get('site_admin_color'));
        } catch (\Throwable) {
            $saved = null;
        }

        return ($saved && self::isUsable($saved)) ? $saved : self::ADMIN_DEFAULT;
    }

    /** True only when the admin explicitly chose "Soft". Bright white is the default, and anything unexpected in the setting means bright white. */
    public static function soft(): bool
    {
        try {
            // No default is passed: Setting::get() would remember it, and an old default would outlive a code change.
            return (string) Setting::get('site_soft_surface') === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    /** The page background: a very light tint of the brand colour instead of glaring pure white. */
    public static function surface(string $base): string
    {
        return self::tint($base, 26.0, 92.8);
    }

    /** Cards, tables, inputs and menus: a touch lighter than the page so they still lift off it, but never pure white. */
    public static function surfaceRaised(string $base): string
    {
        return self::tint($base, 28.0, 96.4);
    }

    /** Header bars and wells: slightly deeper than the page. */
    public static function surfaceSunken(string $base): string
    {
        return self::tint($base, 24.0, 89.6);
    }

    /** A hairline border that suits the surfaces. */
    public static function border(string $base): string
    {
        return self::tint($base, 16.0, 83.5);
    }

    private static function tint(string $base, float $saturation, float $lightness): string
    {
        $base = self::normalize($base) ?? self::DEFAULT;
        [$h, $s] = self::toHsl($base);

        // A grey brand stays grey; a coloured one gives a faint hint of its hue.
        return self::fromHsl($h, $s < 5 ? 3.0 : $saturation, $lightness);
    }

    /** @return array<int, string> shade => hex, 50 (lightest) to 950 (darkest); the base colour is shade 600 */
    public static function scale(string $base): array
    {
        $base = self::normalize($base) ?? self::DEFAULT;

        if ($base === self::DEFAULT) {
            return self::DEFAULT_SCALE;
        }

        if ($base === self::ADMIN_DEFAULT) {
            return self::ADMIN_SCALE;
        }

        [$h, $s, $l] = self::toHsl($base);
        $dark = fn (float $by) => max(6.0, $l - $by);

        $lightness = [
            50 => 96.0, 100 => 91.0, 200 => 83.0, 300 => 72.0, 400 => 60.0,
            500 => min(58.0, $l + 7), 600 => $l,
            700 => $dark(8), 800 => $dark(16), 900 => $dark(24), 950 => $dark(32),
        ];

        // Keep the light end soft (less saturated) so tints do not look neon.
        $out = [];
        foreach ($lightness as $shade => $light) {
            $sat = $shade <= 200 ? $s * 0.85 : $s;
            $out[$shade] = $shade === 600 ? $base : self::fromHsl($h, $sat, $light);
        }

        // Guarantee strictly darker steps even for unusual colours.
        $prev = 100.0;
        foreach ($out as $shade => $hex) {
            $cur = self::lightness($hex);
            if ($cur >= $prev) {
                $out[$shade] = self::fromHsl($h, $s, max(3.0, $prev - 3.0));
                $cur = $prev - 3.0;
            }
            $prev = $cur;
        }

        return $out;
    }

    /** Text colour to use on top of the brand colour: white, unless the colour is too light for it. */
    public static function onColor(string $hex): string
    {
        return self::isUsable($hex) ? '#ffffff' : self::DARK_TEXT;
    }

    /** WCAG contrast ratio between two colours (1 to 21). */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Lightness 0-100 (HSL). */
    public static function lightness(string $hex): float
    {
        return self::toHsl($hex)[2];
    }

    /** The stylesheet block for the whole site. Built only from validated hex values. */
    /**
     * @param  string  $area  'site' (website, sign-in pages, member area) or 'admin'. In the admin the Save / New buttons
     *                        (btn-success) keep their green, as they always have; on the customer side they follow the brand.
     */
    public static function css(?string $base = null, ?bool $soft = null, string $area = 'site'): string
    {
        $primaryBtn = $area === 'admin' ? '.btn-primary' : '.btn-primary,.btn-success';
        $outlineBtn = $area === 'admin' ? '.btn-outline-primary' : '.btn-outline-primary,.btn-outline-success';
        $base = self::normalize($base) ?? self::DEFAULT;
        $soft ??= self::soft();
        $scale = self::scale($base);
        $on = self::onColor($base);
        [$r, $g, $b] = self::toRgb($base);
        [$lr, $lg, $lb] = self::toRgb($scale[700]);

        $vars = '';
        foreach ($scale as $shade => $hex) {
            $vars .= "--color-brand-{$shade}:{$hex};--brand-{$shade}:{$hex};";
        }

        return ":root{{$vars}--brand-on:{$on};"
            ."--bs-primary:{$base};--bs-primary-rgb:{$r},{$g},{$b};"
            ."--bs-primary-bg-subtle:{$scale[100]};--bs-primary-border-subtle:{$scale[200]};--bs-primary-text-emphasis:{$scale[800]};"
            ."--bs-link-color:{$scale[700]};--bs-link-color-rgb:{$lr},{$lg},{$lb};--bs-link-hover-color:{$scale[800]};}"
            .$primaryBtn.'{--bs-btn-bg:var(--brand-600);--bs-btn-border-color:var(--brand-600);--bs-btn-color:var(--brand-on);'
            .'--bs-btn-hover-bg:var(--brand-700);--bs-btn-hover-border-color:var(--brand-700);--bs-btn-hover-color:var(--brand-on);'
            .'--bs-btn-active-bg:var(--brand-800);--bs-btn-active-border-color:var(--brand-800);--bs-btn-active-color:var(--brand-on);'
            .'--bs-btn-disabled-bg:var(--brand-600);--bs-btn-disabled-border-color:var(--brand-600);--bs-btn-disabled-color:var(--brand-on);'
            .'--bs-btn-focus-shadow-rgb:'."{$r},{$g},{$b}".'}'
            .$outlineBtn.'{--bs-btn-color:var(--brand-700);--bs-btn-border-color:var(--brand-600);'
            .'--bs-btn-hover-bg:var(--brand-600);--bs-btn-hover-border-color:var(--brand-600);--bs-btn-hover-color:var(--brand-on);'
            .'--bs-btn-active-bg:var(--brand-700);--bs-btn-active-border-color:var(--brand-700);--bs-btn-active-color:var(--brand-on);'
            .'--bs-btn-focus-shadow-rgb:'."{$r},{$g},{$b}".'}'
            .'.text-primary{color:var(--brand-700)!important}.bg-primary{background-color:var(--brand-600)!important;color:var(--brand-on)!important}'
            .'.border-primary{border-color:var(--brand-600)!important}.link-primary{color:var(--brand-700)!important}'
            .'.nav-pills .nav-link.active,.nav-pills .show>.nav-link{background-color:var(--brand-600);color:var(--brand-on)}'
            .'.form-check-input:checked,.form-check-input[type=checkbox]:indeterminate{background-color:var(--brand-600);border-color:var(--brand-600)}'
            .'.form-control:focus,.form-select:focus{border-color:var(--brand-400);box-shadow:0 0 0 .25rem rgba(var(--bs-primary-rgb),.2)}'
            .'.progress{--bs-progress-bar-bg:var(--brand-600)}.page-item.active .page-link{background-color:var(--brand-600);border-color:var(--brand-600);color:var(--brand-on)}'
            .'.page-link{color:var(--brand-700)}.dropdown-item.active,.dropdown-item:active,.list-group-item.active{background-color:var(--brand-600);border-color:var(--brand-600);color:var(--brand-on)}'
            .'.accordion-button:not(.collapsed){color:var(--brand-800);background-color:var(--brand-50)}'
            .'.admin-sidebar,.member-sidebar{background:linear-gradient(180deg,var(--brand-900) 0%,var(--brand-950) 100%)}'
            .($soft ? self::softCss($base) : '');
    }

    /**
     * Replaces the pure white of the whole product with soft tinted surfaces: public site (Tailwind's white), page
     * background, cards, tables, inputs, menus and header bars (Bootstrap). Text colours are untouched except muted
     * text, which is nudged a little darker so it stays easy to read on the softer background.
     */
    private static function softCss(string $base): string
    {
        $page = self::surface($base);
        $raised = self::surfaceRaised($base);
        $sunken = self::surfaceSunken($base);
        $border = self::border($base);
        [$pr, $pg, $pb] = self::toRgb($page);
        [$rr, $rg, $rb] = self::toRgb($raised);

        return ":root{--surface:{$page};--surface-raised:{$raised};--surface-sunken:{$sunken};--surface-border:{$border};"
            .'--color-white:var(--surface-raised);'
            ."--bs-body-bg:var(--surface);--bs-body-bg-rgb:{$pr},{$pg},{$pb};--bs-white:var(--surface-raised);--bs-white-rgb:{$rr},{$rg},{$rb};"
            .'--bs-tertiary-bg:var(--surface-sunken);--bs-border-color:var(--surface-border);--bs-secondary-color:'.self::MUTED.'}'
            .'body{background-color:var(--surface)}'
            .'.card{--bs-card-bg:var(--surface-raised);--bs-card-cap-bg:var(--surface);--bs-card-border-color:var(--surface-border)}'
            .'.bg-white{background-color:rgba(var(--bs-white-rgb),var(--bs-bg-opacity,1))!important}'
            .'.bg-light,.bg-light-subtle{background-color:var(--surface)!important}'
            .'.table{--bs-table-bg:transparent;--bs-table-hover-bg:var(--surface)}.table-light{--bs-table-bg:var(--surface);--bs-table-color:inherit}'
            .'.form-control,.form-select,.input-group-text{background-color:var(--surface-raised)}'
            .'.dropdown-menu{--bs-dropdown-bg:var(--surface-raised)}.modal-content,.offcanvas:not(.admin-sidebar):not(.member-sidebar){--bs-modal-bg:var(--surface-raised)}'
            .'.list-group-item{background-color:var(--surface-raised)}.accordion-item{background-color:var(--surface-raised)}'
            .'.text-secondary{color:'.self::MUTED.'!important}';
    }

    // ------------------------------------------------------------------------------------------ colour maths

    private static function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function luminance(string $hex): float
    {
        $channel = function (int $v): float {
            $c = $v / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = self::toRgb($hex);

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    /** @return array{0: float, 1: float, 2: float} hue 0-360, saturation 0-100, lightness 0-100 */
    private static function toHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn ($v) => $v / 255, self::toRgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d == 0) {
            return [0.0, 0.0, $l * 100];
        }

        $s = $d / (1 - abs(2 * $l - 1));
        $h = match ($max) {
            $r => fmod((($g - $b) / $d), 6),
            $g => (($b - $r) / $d) + 2,
            default => (($r - $g) / $d) + 4,
        };
        $h = fmod($h * 60 + 360, 360);

        return [$h, $s * 100, $l * 100];
    }

    private static function fromHsl(float $h, float $s, float $l): string
    {
        $s = max(0, min(100, $s)) / 100;
        $l = max(0, min(100, $l)) / 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return sprintf('#%02x%02x%02x', round(($r + $m) * 255), round(($g + $m) * 255), round(($b + $m) * 255));
    }
}
