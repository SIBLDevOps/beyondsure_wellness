@extends('layouts.admin')

@section('title', 'GST sales')

@section('content')
    @include('partials.reports-nav')
    @php
        $money = fn ($v) => ($v < 0 ? '−' : '').'₹'.number_format(abs($v), 2);
        $q = array_filter(['month' => $month->format('Y-m'), 'region' => request()->integer('region') ?: null]);
    @endphp

    <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">GST sales register</h2>
            <p class="text-secondary small mb-0" style="max-width: 640px;">
                Every invoice issued in the month, with each credit note issued in the month taken off. Hand the CSV to your accountant for the GST return.
                Orders paid before invoices were numbered are not included.
            </p>
        </div>
        <form method="GET" class="d-flex align-items-end gap-2">
            @if (request()->integer('region'))<input type="hidden" name="region" value="{{ request()->integer('region') }}">@endif
            <div>
                <label class="form-label small mb-1" for="month">Month</label>
                <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm">
            </div>
            <button class="btn btn-sm btn-primary">Show</button>
            <a href="{{ route('admin.reports.gst.csv', $q) }}" class="btn btn-sm btn-success"><i class="bi bi-download me-1"></i>Download CSV</a>
        </form>
    </div>

    <h3 class="h6 text-secondary mb-2">{{ $month->format('F Y') }}</h3>
    <div class="row g-3 mb-4">
        @foreach ([
            ['Invoices', $totals['invoices'].' · '.$money($totals['taxable']).' taxable', $money($totals['gst']).' GST'],
            ['Credit notes', $totals['credit_notes'].' · '.$money($totals['credit_taxable']).' taxable', $money($totals['credit_gst']).' GST'],
            ['Net taxable value', $money($totals['net_taxable']), 'after credit notes'],
            ['Net GST', $money($totals['net_gst']), ($igst ? 'IGST '.$money($totals['net_igst']) : 'CGST '.$money($totals['net_cgst']).' · SGST '.$money($totals['net_sgst']))],
        ] as [$label, $big, $small])
            <div class="col-6 col-xl-3">
                <div class="border rounded-3 p-3 h-100 bg-body">
                    <div class="small text-secondary">{{ $label }}</div>
                    <div class="fw-bold fs-5">{{ $big }}</div>
                    <div class="small text-secondary">{{ $small }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light small text-secondary">
                    <tr>
                        <th class="ps-3">Type</th><th>Number</th><th>Date</th><th>Customer</th>
                        <th class="text-end">Taxable</th>
                        @if ($igst)<th class="text-end">IGST</th>@else<th class="text-end">CGST</th><th class="text-end">SGST</th>@endif
                        <th class="text-end pe-3">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows->take(500) as $r)
                        <tr class="{{ $r['type'] === 'Credit note' ? 'table-warning' : '' }}">
                            <td class="ps-3 small">{{ $r['type'] }}</td>
                            <td class="small font-monospace">{{ $r['number'] }}@if ($r['against'])<div class="text-secondary">for {{ $r['against'] }}</div>@endif</td>
                            <td class="small">{{ \Carbon\Carbon::parse($r['date'])->format('d M Y') }}</td>
                            <td class="small">{{ $r['customer'] }}<div class="text-secondary">{{ $r['mobile'] }}</div></td>
                            <td class="text-end">{{ $money($r['taxable']) }}</td>
                            @if ($igst)<td class="text-end">{{ $money($r['igst']) }}</td>@else<td class="text-end">{{ $money($r['cgst']) }}</td><td class="text-end">{{ $money($r['sgst']) }}</td>@endif
                            <td class="text-end pe-3 fw-semibold">{{ $money($r['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $igst ? 7 : 8 }}" class="text-center text-body-tertiary py-5">No invoices or credit notes in {{ $month->format('F Y') }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->count() > 500)<div class="card-footer small text-secondary">Showing the first 500 of {{ $rows->count() }} lines. The CSV has all of them.</div>@endif
    </div>
@endsection
