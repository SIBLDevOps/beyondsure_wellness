<?php

namespace Tests\Unit;

use App\Support\Theme;
use PHPUnit\Framework\TestCase;

class ThemeTest extends TestCase
{
    public function test_hex_colours_are_validated_and_normalised(): void
    {
        $this->assertSame('#aabbcc', Theme::normalize('#ABC'));
        $this->assertSame('#2563eb', Theme::normalize('#2563EB'));
        $this->assertSame('#2563eb', Theme::normalize('  #2563eb '));

        foreach ([null, '', 'red', '2563eb', '#12345', '#1234567', '#gggggg', 'rgb(1,2,3)', '#fff;}</style><script>alert(1)</script>', 'url(x)', '#ff ff', '#ffff00 !important'] as $bad) {
            $this->assertNull(Theme::normalize($bad), 'Rejected: '.var_export($bad, true));
        }
    }

    public function test_the_default_brand_keeps_the_existing_emerald_scale_exactly(): void
    {
        $scale = Theme::scale(Theme::DEFAULT);

        $this->assertSame('#ecfdf5', $scale[50]);
        $this->assertSame('#059669', $scale[600]);
        $this->assertSame('#064e3b', $scale[900]);
        $this->assertSame('#022c22', $scale[950]);
    }

    public function test_any_colour_gets_an_eleven_step_scale_with_the_base_as_600_getting_steadily_darker(): void
    {
        foreach (['#2563eb', '#7c3aed', '#dc2626', '#0f766e', '#c2410c', '#475569'] as $base) {
            $scale = Theme::scale($base);

            $this->assertSame([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950], array_keys($scale));
            $this->assertSame($base, $scale[600], 'The chosen colour is shade 600');

            $light = array_values(array_map(fn ($hex) => Theme::lightness($hex), $scale));
            $sorted = $light;
            rsort($sorted);
            $this->assertSame($sorted, $light, "Shades must go from light to dark for {$base}");
            $this->assertCount(11, array_unique($scale), 'No two shades are identical');
        }
    }

    public function test_contrast_follows_the_wcag_formula(): void
    {
        $this->assertEqualsWithDelta(21.0, Theme::contrast('#ffffff', '#000000'), 0.01);
        $this->assertEqualsWithDelta(1.0, Theme::contrast('#336699', '#336699'), 0.01);
        $this->assertEqualsWithDelta(3.77, Theme::contrast('#059669', '#ffffff'), 0.05);
    }

    public function test_text_on_a_colour_is_white_unless_the_colour_is_too_light_for_it(): void
    {
        $this->assertSame('#ffffff', Theme::onColor('#059669'));
        $this->assertSame('#ffffff', Theme::onColor('#2563eb'));
        $this->assertSame('#0f172a', Theme::onColor('#fde047'));
    }

    public function test_a_colour_must_be_dark_enough_for_white_text(): void
    {
        $this->assertTrue(Theme::isUsable('#059669'));
        $this->assertTrue(Theme::isUsable('#2563eb'));
        $this->assertFalse(Theme::isUsable('#ffff00'), 'Yellow cannot carry white text');
        $this->assertFalse(Theme::isUsable('#ffffff'));
    }

    public function test_the_generated_css_defines_the_brand_for_tailwind_and_bootstrap(): void
    {
        $css = Theme::css('#2563eb');

        foreach (['--color-brand-600:#2563eb', '--brand-600:#2563eb', '--bs-primary:#2563eb', '--bs-primary-rgb:37,99,235'] as $needle) {
            $this->assertStringContainsString($needle, $css);
        }
        $this->assertStringNotContainsString('</style', strtolower($css));
        $this->assertStringContainsString('.btn-primary', $css);
        // The main action buttons in this app are btn-success; they follow the brand too (status badges stay green).
        $this->assertMatchesRegularExpression('/\.btn-primary,\.btn-success\{/', $css);
        $this->assertMatchesRegularExpression('/\.btn-outline-primary,\.btn-outline-success\{/', $css);
        $this->assertStringNotContainsString('.badge.bg-success', $css);
        $this->assertStringNotContainsString('.text-success{', $css);
    }

    public function test_the_css_only_ever_contains_valid_hex_values_whatever_is_passed_in(): void
    {
        $css = Theme::css('#fff;}</style><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $css);
        $this->assertStringContainsString('--brand-600:#059669', $css, 'An invalid colour falls back to the default brand');
    }

    // ---------------------------------------------------------------------------------- the admin keeps its own blue

    public function test_the_admin_area_has_its_own_default_blue_with_the_original_navy_menu(): void
    {
        $this->assertSame('#4f46e5', Theme::ADMIN_DEFAULT);

        $scale = Theme::scale(Theme::ADMIN_DEFAULT);
        $this->assertSame('#4f46e5', $scale[600]);
        $this->assertSame('#eef2ff', $scale[50]);
        $this->assertSame('#1e1b4b', $scale[900], 'The side menu gradient starts at the original navy');
        $this->assertSame('#14123a', $scale[950], 'and ends at the original deep navy');
        $this->assertTrue(Theme::isUsable(Theme::ADMIN_DEFAULT));
    }

    public function test_the_admin_stylesheet_is_blue_and_keeps_green_action_buttons_as_before(): void
    {
        $admin = Theme::css(Theme::ADMIN_DEFAULT, false, 'admin');
        $site = Theme::css(Theme::DEFAULT, false, 'site');

        $this->assertStringContainsString('--brand-600:#4f46e5', $admin);
        $this->assertMatchesRegularExpression('/\.btn-primary\{/', $admin);
        $this->assertDoesNotMatchRegularExpression('/\.btn-success/', $admin, 'Save and New buttons stay green in the admin');
        $this->assertMatchesRegularExpression('/\.btn-primary,\.btn-success\{/', $site, 'On the customer side main buttons follow the brand');
    }

    // ---------------------------------------------------------------------------------- soft surfaces (easy on the eyes)

    public function test_page_and_card_surfaces_are_soft_tinted_off_whites_never_pure_white(): void
    {
        foreach (['#059669', '#2563eb', '#7c3aed', '#dc2626', '#475569'] as $base) {
            $page = Theme::surface($base);
            $card = Theme::surfaceRaised($base);

            $this->assertNotSame('#ffffff', $page);
            $this->assertNotSame('#ffffff', $card);
            $this->assertLessThan(97.0, Theme::lightness($page), "Page background must not glare ({$base})");
            $this->assertGreaterThan(88.0, Theme::lightness($page), 'but stay light');
            $this->assertLessThan(Theme::lightness($card), Theme::lightness($page), 'cards sit slightly lighter than the page');
            $this->assertLessThan(99.0, Theme::lightness($card));
        }
    }

    public function test_the_surfaces_take_a_hint_of_the_brand_hue(): void
    {
        $this->assertNotSame(Theme::surface('#059669'), Theme::surface('#2563eb'));
        [$r, $g, $b] = sscanf(Theme::surface('#2563eb'), '#%02x%02x%02x');
        $this->assertGreaterThan($r, $b, 'A blue brand gives a faintly blue page');
    }

    public function test_body_text_stays_highly_readable_on_the_soft_surfaces(): void
    {
        foreach (['#059669', '#2563eb', '#7c3aed', '#dc2626', '#475569', '#0f766e'] as $base) {
            $this->assertGreaterThanOrEqual(10.0, Theme::contrast('#1e293b', Theme::surface($base)), "Text on the page ({$base})");
            $this->assertGreaterThanOrEqual(11.0, Theme::contrast('#1e293b', Theme::surfaceRaised($base)), "Text on cards ({$base})");
            $this->assertGreaterThanOrEqual(4.5, Theme::contrast(Theme::MUTED, Theme::surfaceRaised($base)), "Muted text on cards ({$base})");
            $this->assertGreaterThanOrEqual(4.5, Theme::contrast(Theme::MUTED, Theme::surface($base)), "Muted text on the page ({$base})");
        }
    }

    public function test_soft_mode_replaces_white_in_both_tailwind_and_bootstrap(): void
    {
        $css = Theme::css('#059669', true);

        $this->assertStringContainsString('--surface:'.Theme::surface('#059669'), $css);
        $this->assertStringContainsString('--color-white:var(--surface-raised)', $css, 'Public site white utilities soften');
        $this->assertStringContainsString('--bs-body-bg:var(--surface)', $css);
        $this->assertMatchesRegularExpression('/\.card\{[^}]*--bs-card-bg:var\(--surface-raised\)/', $css);
        // bg-white takes the soft colour but must keep honouring bg-opacity-* (translucent badges on dark menus).
        $this->assertMatchesRegularExpression('/\.bg-white\{background-color:rgba\(var\(--bs-white-rgb\),var\(--bs-bg-opacity,1\)\)!important\}/', $css);
        $this->assertStringNotContainsString('.bg-white{background-color:var(--surface-raised)', $css);
        $this->assertMatchesRegularExpression('/\.form-control[^{]*\{[^}]*background-color:var\(--surface-raised\)/', $css);
    }

    public function test_bright_mode_leaves_white_alone(): void
    {
        $css = Theme::css('#059669', false);

        $this->assertStringNotContainsString('--color-white', $css);
        $this->assertStringNotContainsString('--bs-body-bg', $css);
        $this->assertStringNotContainsString('.bg-white{', $css);
        $this->assertStringContainsString('--brand-600:#059669', $css, 'The brand colour still applies');
    }
}
