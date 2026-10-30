{{--
    The colours for this page, built only from validated hex values (see App\Support\Theme). Include after the stylesheets.
    $area = 'admin' for the admin area and its login page (own colour, blue by default); otherwise the website, sign-in
    pages and member area use the brand colour (green by default).
--}}
@php
    $themeArea = ($area ?? 'site') === 'admin' ? 'admin' : 'site';
    $themeBase = $themeArea === 'admin' ? \App\Support\Theme::adminPrimary() : \App\Support\Theme::primary();
@endphp
<style id="brand-theme">{!! \App\Support\Theme::css($themeBase, null, $themeArea) !!}</style>
