<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ ['TAX INVOICE' => 'Tax invoice', 'CREDIT NOTE' => 'Credit note'][$title] ?? 'Invoice' }} {{ $number }} — {{ $seller['brand'] }}</title>
    @include('partials.favicon')
    @php
        // The colour band follows the brand colour: a dark shade of it, with white (or black) text that stays readable on it.
        $scale = \App\Support\Theme::scale(\App\Support\Theme::primary());
        $band = $scale[800] ?? \App\Support\Theme::primary();
        $onBand = \App\Support\Theme::onColor($band);
        $money = fn ($v) => '₹'.number_format((float) $v, 2);
        $methods = ['upi' => 'UPI', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'razorpay' => 'Online (Razorpay)'];
    @endphp
    <style>
        :root { --band: {{ $band }}; --on-band: {{ $onBand }}; --ink: #1f2937; --muted: #6b7280; --line: #e5e7eb; --soft: #f3f4f6; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef0f3; color: var(--ink); font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        .toolbar { max-width: 210mm; margin: 16px auto 0; padding: 0 12px; display: flex; gap: 8px; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .toolbar a, .toolbar button { font: inherit; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--line); background: #fff; color: var(--ink); text-decoration: none; cursor: pointer; }
        .toolbar button { background: var(--band); color: var(--on-band); border-color: var(--band); font-weight: 600; }
        .mailbox { position: relative; }
        .mailbox summary { list-style: none; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--line); background: #fff; cursor: pointer; }
        .mailbox summary::-webkit-details-marker { display: none; }
        .mailbox form { position: absolute; right: 0; top: 44px; z-index: 5; display: flex; gap: 6px; background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 10px; box-shadow: 0 6px 24px rgba(0,0,0,.12); }
        .mailbox input { font: inherit; padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px; width: 230px; }
        .note { max-width: 210mm; margin: 12px auto 0; padding: 10px 14px; border-radius: 8px; }
        .note.ok { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
        .warn { max-width: 210mm; margin: 12px auto 0; padding: 10px 14px; background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; border-radius: 8px; }
        .sheet { width: 210mm; max-width: 100%; min-height: 297mm; margin: 12px auto 32px; background: #fff; box-shadow: 0 2px 18px rgba(0, 0, 0, .08); overflow: hidden; border-radius: 6px; border: 2px solid var(--band); }
        .band { background: var(--band); color: var(--on-band); display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px 14mm; }
        .logo { background: #fff; border-radius: 10px; padding: 7px 12px; display: inline-flex; align-items: center; }
        .logo img { max-height: 52px; max-width: 220px; object-fit: contain; display: block; }
        .brand-name { font-size: 26px; font-weight: 800; letter-spacing: -.5px; }
        .doc { text-align: right; }
        .doc h1 { margin: 0; font-size: 22px; letter-spacing: 1.5px; }
        .doc .no { font-size: 15px; font-weight: 700; margin-top: 2px; }
        .doc .date { opacity: .85; font-size: 13px; }
        .body { padding: 8mm 14mm 12mm; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .box { background: var(--soft); border: 1.5px solid var(--band); border-radius: 12px; padding: 12px 16px; }
        .box h2 { margin: 0 0 4px; font-size: 11px; text-transform: uppercase; letter-spacing: .8px; color: var(--muted); font-weight: 700; }
        .box .who { font-weight: 700; font-size: 15px; }
        .box p { margin: 2px 0; }
        .muted { color: var(--muted); }
        .meta { table-layout: fixed; width: 100%; margin-top: 14px; border: 1.5px solid var(--band); border-radius: 12px; border-collapse: separate; border-spacing: 0; overflow: hidden; }
        .meta td { padding: 9px 14px; border-right: 1px solid var(--band); vertical-align: top; width: 25%; }
        .meta td:last-child { border-right: 0; }
        .meta td:nth-child(1) { width: 27%; } .meta td:nth-child(2) { width: 17%; } .meta td:nth-child(3) { width: 22%; } .meta td:nth-child(4) { width: 34%; }
        .meta td b, .meta td div { overflow-wrap: anywhere; }
        .meta small { display: block; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .6px; }
        .tablewrap { overflow-x: auto; margin-top: 18px; }
        table.items { min-width: 520px; width: 100%; border-collapse: collapse; border: 1.5px solid var(--band); }
        table.items th { color: var(--band); background: var(--soft); text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; padding: 8px 10px; border: 1px solid var(--band); border-bottom: 2px solid var(--band); }
        table.items th.r { text-align: right; }
        table.items th.c { text-align: center; }
        table.items td { padding: 10px; vertical-align: top; border: 1px solid #cbd5d1; }
        table.items tbody tr:nth-child(odd) td { background: #f9fafb; }
        .r { text-align: right; }
        .c { text-align: center; }
        .totals { margin: 16px 0 0 auto; width: 320px; max-width: 100%; border: 1.5px solid var(--band); border-radius: 12px; padding: 6px; }
        .totals div { display: flex; justify-content: space-between; padding: 5px 10px; }
        .totals .grand { background: var(--band); color: var(--on-band); border-radius: 10px; font-weight: 800; font-size: 16px; margin-top: 6px; padding: 10px 12px; }
        .words { margin-top: 16px; padding: 10px 14px; background: var(--soft); border: 1.5px solid var(--band); border-radius: 12px; }
        .words b { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: var(--muted); }
        .terms { margin-top: 16px; font-size: 12px; color: var(--muted); white-space: pre-line; }
        .terms b { display: block; color: var(--ink); font-size: 11px; text-transform: uppercase; letter-spacing: .6px; }
        footer { margin-top: 22px; padding-top: 10px; border-top: 1px solid var(--line); text-align: center; font-size: 11px; color: var(--muted); }
        @media (max-width: 640px) { .band { flex-direction: column; align-items: flex-start; padding: 16px; } .body { padding: 14px 16px; } .grid { grid-template-columns: 1fr; } .doc { text-align: left; } .meta td, .meta td:nth-child(n) { display: block; width: auto; border-right: 0; border-bottom: 1px solid var(--line); } table.items { font-size: 12px; } }
        @media print {
            @page { size: A4; margin: 0; }
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .toolbar, .no-print { display: none !important; }
            .sheet { box-shadow: none; margin: 0; width: auto; min-height: auto; border-radius: 0; }
            .sheet, .box, .meta, table.items, .totals, .words { break-inside: avoid; }
        }
    </style>
</head>
<body>
    @php $mine = ! ($shared ?? false) && ! $credit && auth('member')->id() === $order->member_id; @endphp
    <div class="toolbar">
        @if ($shared ?? false)<span></span>@else<a href="javascript:history.back()">&larr; Back</a>@endif
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            @if ($mine)
                <details class="mailbox">
                    <summary>Email invoice</summary>
                    <form method="POST" action="{{ route('member.orders.invoice.email', $order) }}">
                        @csrf
                        <input type="email" name="email" value="{{ old('email', $buyer['email']) }}" placeholder="name@company.com" required maxlength="255">
                        <button type="submit">Send</button>
                    </form>
                </details>
            @endif
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
        </div>
    </div>
    @if (session('status'))<div class="note ok no-print" role="status">{{ session('status') }}</div>@endif
    @if (isset($errors) && $errors->any())<div class="warn no-print" role="alert">{{ $errors->first() }}</div>@endif

    @unless ($number || $credit)
        <div class="warn no-print" role="alert">
            <strong>Invoice number missing.</strong> This invoice has not been numbered yet, so it should not be given to the customer.
            The database update for invoices has probably not been run. Run <code>php artisan migrate --force</code>, then open this page again.
        </div>
    @endunless

    <main class="sheet">
        <header class="band">
            <div>
                @if ($seller['logo'])
                    <span class="logo"><img src="{{ $seller['logo'] }}" alt="{{ $seller['brand'] }}"></span>
                @else
                    <div class="brand-name">{{ $seller['brand'] }}</div>
                @endif
            </div>
            <div class="doc">
                <h1>{{ $title }}</h1>
                <div class="no">{{ $number }}</div>
                <div class="date">Date: {{ $date->format('d M Y') }}</div>
            </div>
        </header>

        <div class="body">
            <div class="grid">
                <section class="box">
                    <h2>Sold by</h2>
                    <p class="who">{{ $seller['name'] }}</p>
                    @if ($seller['address'])<p>{!! nl2br(e($seller['address'])) !!}</p>@endif
                    @if ($seller['state'])<p>State: {{ $seller['state'] }}</p>@endif
                    @if ($seller['gstin'])<p><b>GSTIN:</b> {{ $seller['gstin'] }}</p>@endif
                    @if ($seller['pan'])<p><b>PAN:</b> {{ $seller['pan'] }}</p>@endif
                    @if ($seller['phone'])<p class="muted">{{ $seller['phone'] }}</p>@endif
                    @if ($seller['email'])<p class="muted">{{ $seller['email'] }}</p>@endif
                </section>
                <section class="box">
                    <h2>Billed to</h2>
                    <p class="who">{{ $buyer['name'] }}</p>
                    @if ($buyer['address'])<p>{!! nl2br(e($buyer['address'])) !!}</p>@endif
                    @if ($buyer['phone'])<p>Mobile: {{ $buyer['phone'] }}</p>@endif
                    @if ($buyer['email'])<p>{{ $buyer['email'] }}</p>@endif
                    <p class="muted">Member ID: {{ $buyer['code'] }}</p>
                </section>
            </div>

            <table class="meta">
                <tr>
                    <td><small>{{ $credit ? 'Credit note no.' : 'Invoice no.' }}</small><b>{{ $number }}</b></td>
                    <td><small>{{ $credit ? 'Issued on' : 'Invoice date' }}</small><b>{{ $date->format('d M Y') }}</b></td>
                    <td><small>Order</small><b>{{ $order->order_code }}</b></td>
                    <td>
                        @if ($credit)
                            <small>Against invoice</small>
                            <b>{{ $originalInvoice['number'] }}</b>
                            @if ($originalInvoice['date'])<div class="muted" style="font-size:12px;">dated {{ $originalInvoice['date']->format('d M Y') }}</div>@endif
                        @else
                            <small>Payment</small>
                            <b>{{ $methods[$payment?->method] ?? ucfirst(str_replace('_', ' ', (string) $payment?->method)) ?: 'Paid' }}</b>
                            @if ($payment?->reference)<div class="muted" style="font-size:12px; word-break: break-all;">Ref: {{ $payment->reference }}</div>@endif
                        @endif
                    </td>
                </tr>
            </table>

            @if ($credit)
                <div class="words" style="margin-top:14px;"><b>Reason</b>{{ $credit->reason }}</div>
                @if ($credit->refund_method)
                    <div class="words" style="margin-top:10px;"><b>Refunded by</b>{{ $credit->methodLabel() }}@if ($credit->refund_reference) · Ref: {{ $credit->refund_reference }}@endif @if ($credit->refunded_on) · {{ $credit->refunded_on->format('d M Y') }}@endif</div>
                @endif
            @endif

            <div class="tablewrap">
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:32px;">#</th>
                        <th>Description</th>
                        <th class="c">Qty</th>
                        <th class="r">Taxable value</th>
                        <th class="c">GST</th>
                        <th class="r">GST amount</th>
                        <th class="r">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $line)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                {{ $line['name'] }}
                                @if ($loop->first && $order->plan)<div class="muted" style="font-size:12px;">Part of the {{ $order->plan->name }} plan</div>@endif
                            </td>
                            <td class="c">{{ $line['qty'] }}</td>
                            <td class="r">{{ $money($line['taxable']) }}</td>
                            <td class="c">{{ rtrim(rtrim(number_format($line['rate'], 2), '0'), '.') }}%</td>
                            <td class="r">{{ $money($line['gst']) }}</td>
                            <td class="r">{{ $money($line['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div class="totals">
                <div><span>Taxable value</span><span>{{ $money($taxable) }}</span></div>
                @if ($igst)
                    <div><span>IGST</span><span>{{ $money($gst) }}</span></div>
                @else
                    <div><span>Total GST</span><span>{{ $money($gst) }}</span></div>
                    <div class="muted"><span>&nbsp;&nbsp;CGST</span><span>{{ $money($cgst) }}</span></div>
                    <div class="muted"><span>&nbsp;&nbsp;SGST</span><span>{{ $money($sgst) }}</span></div>
                @endif
                <div class="grand"><span>{{ $credit ? 'Total credited (incl. GST)' : 'Total (incl. GST)' }}</span><span>{{ $money($total) }}</span></div>
            </div>

            <div class="words"><b>Amount in words</b>{{ $words }}</div>

            @if ($terms)
                <div class="terms"><b>Terms and conditions</b>{{ $terms }}</div>
            @endif

            <footer>
                This is a computer-generated invoice and does not need a signature.
                @if ($seller['phone'] || $seller['email'])<br>Questions? {{ trim($seller['phone'].' '.($seller['phone'] && $seller['email'] ? '·' : '').' '.$seller['email']) }}@endif
            </footer>
        </div>
    </main>
</body>
</html>
