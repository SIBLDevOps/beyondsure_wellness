@extends('layouts.admin')

@section('title', '7th level cascade report')

@section('content')
    @include('partials.reports-nav')

    @php $money = fn ($n) => '₹'.number_format($n, 2); @endphp

    <div class="card border mb-4 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="stat-icon bg-primary text-white rounded-3 p-2 flex-shrink-0" style="width:42px;height:42px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-layers-fill fs-5"></i>
                    </div>
                    <div>
                        <h2 class="h6 mb-1 fw-bold">7th level cascade report</h2>
                        <p class="text-secondary small mb-0" style="max-width: 820px;">
                            Every time volume matches, seven people are paid, one for each level. This report shows, for each match, who held each of the seven slots and what they were paid.
                            When a match is deeper than the levels reach, the <strong>root</strong> takes the last slot and is marked <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Root slot</span>.
                        </p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.reports.cascade.pdf', request()->query()) }}" target="_blank" class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-file-earmark-pdf me-1"></i>Show as PDF</a>
                    <a href="{{ route('admin.reports.cascade.csv', request()->query()) }}" class="btn btn-sm btn-outline-success text-nowrap"><i class="bi bi-download me-1"></i>Export CSV</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card border h-100 shadow-sm"><div class="card-body p-3">
            <div class="text-secondary small fw-medium">Matching events</div>
            <div class="fs-4 fw-bold">{{ number_format($summary['events']) }}</div>
            <div class="text-body-tertiary small" style="font-size:.72rem;">A match at one member, in one cycle</div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border h-100 shadow-sm"><div class="card-body p-3">
            <div class="text-secondary small fw-medium">Total cascade paid</div>
            <div class="fs-4 fw-bold text-primary">{{ $money($summary['total']) }}</div>
            <div class="text-body-tertiary small" style="font-size:.72rem;">All seven levels together</div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border h-100 shadow-sm"><div class="card-body p-3">
            <div class="text-secondary small fw-medium">Paid to the root slot</div>
            <div class="fs-4 fw-bold text-warning-emphasis">{{ $money($summary['root_paid']) }}</div>
            <div class="text-body-tertiary small" style="font-size:.72rem;">Deep matches where the root held the last slot</div>
        </div></div></div>
        <div class="col-6 col-xl-3"><div class="card border h-100 shadow-sm"><div class="card-body p-3">
            <div class="text-secondary small fw-medium">Average per event</div>
            <div class="fs-4 fw-bold">{{ $money($summary['events'] > 0 ? $summary['total'] / $summary['events'] : 0) }}</div>
            <div class="text-body-tertiary small" style="font-size:.72rem;">Cascade paid for one match</div>
        </div></div></div>
    </div>

    <div class="card border mb-4 shadow-sm">
        <div class="card-header bg-light-subtle border-bottom py-3">
            <h2 class="h6 mb-0 fw-semibold">Paid at each of the seven levels</h2>
            <div class="text-body-tertiary small">{{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}</div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Level</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Payments</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Of which root slot</th>
                        <th class="text-end pe-3" style="min-width:140px;">Share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['levels'] as $l)
                        <tr class="{{ $l['payments'] === 0 ? 'text-body-tertiary' : '' }}">
                            <td class="ps-3 fw-semibold">Level {{ $l['level'] }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($l['rate'], 2), '0'), '.') }}%</td>
                            <td class="text-end">{{ number_format($l['payments']) }}</td>
                            <td class="text-end fw-semibold">{{ $money($l['paid']) }}</td>
                            <td class="text-end">{{ $l['root_payments'] ? $money($l['root_paid']).' ('.$l['root_payments'].')' : '—' }}</td>
                            <td class="text-end pe-3">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <div class="progress flex-grow-1" style="height:6px;min-width:60px;"><div class="progress-bar" style="width: {{ $l['share'] }}%; background:#6d28d9;"></div></div>
                                    <span class="small text-secondary">{{ $l['share'] }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @if ($summary['beyond']['payments'] > 0)
                        <tr class="table-warning">
                            <td class="ps-3 fw-semibold" colspan="2">Other levels (older settings)</td>
                            <td class="text-end">{{ number_format($summary['beyond']['payments']) }}</td>
                            <td class="text-end fw-semibold">{{ $money($summary['beyond']['paid']) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    @endif
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr><td class="ps-3" colspan="3">Total</td><td class="text-end">{{ $money($summary['total']) }}</td><td colspan="2"></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card border mb-4 shadow-sm">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold">Every match and its seven slots</h2>
                <div class="text-body-tertiary small">Newest first. An empty slot means the line above that member is shorter than seven.</div>
            </div>
            <div class="input-group input-group-sm" style="max-width: 240px;">
                <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
                <input class="form-control" placeholder="Filter by name or code…" data-table-filter="#cascadeTable">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="cascadeTable">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th class="ps-3">Matched at</th>
                        @for ($i = 1; $i <= \App\Services\ReportService::CASCADE_SLOTS; $i++)
                            <th style="min-width:130px;">Level {{ $i }}</th>
                        @endfor
                        <th class="text-end pe-3">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $e)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $e['source']->name }}</div>
                                <div class="font-monospace text-body-tertiary small">{{ $e['source']->member_code }}</div>
                                <div class="text-body-tertiary" style="font-size:.7rem;">{{ $e['date']?->format('d M Y') }}</div>
                            </td>
                            @foreach ($e['slots'] as $slot)
                                <td class="{{ $slot && $slot['root'] ? 'table-warning' : '' }}">
                                    @if ($slot)
                                        <div class="small fw-medium">{{ $slot['member']->name ?? '—' }}</div>
                                        <div class="font-monospace text-body-tertiary" style="font-size:.7rem;">{{ $slot['member']->member_code ?? '' }}</div>
                                        <div class="fw-semibold">{{ $money($slot['amount']) }}</div>
                                        @if ($slot['root'])<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Root slot</span>@endif
                                    @else
                                        <span class="text-body-tertiary">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-end pe-3 fw-bold">{{ $money($e['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-body-tertiary py-5">No cascade payments in this date range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($events instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $events->hasPages())
            <div class="card-footer bg-white border-top py-3">{{ $events->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endsection
