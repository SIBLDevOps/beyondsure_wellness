@php
    $scale = \App\Support\Theme::scale(\App\Support\Theme::primary());
    $band = $scale[800] ?? \App\Support\Theme::primary();
    $onBand = \App\Support\Theme::onColor($band);
    $button = $scale[600] ?? $band;
    $onButton = \App\Support\Theme::onColor($button);
    $logo = \App\Support\Site::logoUrl();
    $brand = \App\Support\Site::name();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject }}</title>
</head>
<body style="margin:0;padding:0;background:#eef0f3;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f3;padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:2px solid {{ $band }};border-radius:10px;overflow:hidden;">
            <tr>
                <td style="background:{{ $band }};color:{{ $onBand }};padding:18px 24px;">
                    @if ($logo)
                        <span style="display:inline-block;background:#ffffff;border-radius:8px;padding:6px 10px;"><img src="{{ $logo }}" alt="{{ $brand }}" height="40" style="display:block;max-height:40px;max-width:200px;"></span>
                    @else
                        <span style="font-size:22px;font-weight:800;color:{{ $onBand }};">{{ $brand }}</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding:24px;">
                    <h1 style="margin:0 0 12px;font-size:20px;line-height:1.3;color:{{ $band }};">{{ $heading }}</h1>
                    @if ($greeting)<p style="margin:0 0 12px;font-size:15px;line-height:1.6;">{{ $greeting }}</p>@endif
                    @foreach ($paragraphs as $paragraph)
                        <p style="margin:0 0 12px;font-size:15px;line-height:1.6;">{{ $paragraph }}</p>
                    @endforeach

                    @if ($htmlBody)
                        <div style="margin:0 0 12px;font-size:15px;line-height:1.6;">{!! \App\Support\RichText::render($htmlBody) !!}</div>
                    @endif

                    @if ($facts)
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;border:1px solid #d1d5db;border-radius:8px;border-collapse:separate;">
                            @foreach ($facts as $label => $value)
                                <tr>
                                    <td style="padding:8px 12px;font-size:13px;color:#6b7280;border-bottom:1px solid #e5e7eb;width:40%;">{{ $label }}</td>
                                    <td style="padding:8px 12px;font-size:14px;font-weight:600;border-bottom:1px solid #e5e7eb;">{{ $value }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif

                    @if ($ctaUrl && $ctaLabel)
                        <p style="margin:20px 0 4px;">
                            <a href="{{ $ctaUrl }}" style="display:inline-block;background:{{ $button }};color:{{ $onButton }};text-decoration:none;font-weight:600;font-size:15px;padding:12px 22px;border-radius:8px;">{{ $ctaLabel }}</a>
                        </p>
                        <p style="margin:8px 0 0;font-size:12px;color:#6b7280;word-break:break-all;">If the button does not work, open this address: {{ $ctaUrl }}</p>
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding:14px 24px;background:#f3f4f6;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;text-align:center;">
                    {{ $brand }}
                    @if ($unsubscribeUrl)
                        <br>You get this because you are a member. <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">Unsubscribe from news and offers</a>. Emails about your own orders and payments will still be sent.
                    @endif
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
