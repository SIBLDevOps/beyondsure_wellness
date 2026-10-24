{{--
    The brand mark: the uploaded logo if there is one, otherwise the brand name as text with the trailing "+" in the accent colour.
    Params: $accent (class for the "+"), $img (classes for the logo image), $dark (true on dark backgrounds, picks the dark-background logo).
--}}
@php
    $logo = ($dark ?? false) ? \App\Support\Site::logoDarkUrl() : \App\Support\Site::logoUrl();
    [$brandMain, $brandPlus] = \App\Support\Site::nameParts();
@endphp
@if ($logo)
    <img src="{{ $logo }}" alt="{{ \App\Support\Site::name() }}" class="{{ $img ?? 'h-9 w-auto' }}" style="max-width: 220px; object-fit: contain;">
@else
    {{ $brandMain }}@if ($brandPlus)<span class="{{ $accent ?? '' }}">+</span>@endif
@endif
