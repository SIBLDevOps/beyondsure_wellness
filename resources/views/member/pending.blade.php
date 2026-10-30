@extends('layouts.member')

@section('title', 'Waiting to activate')

@section('content')
    @php
        $stages = [
            'registered' => ['Registered, no order yet', 1, 'secondary', 'Signed up but has not chosen a plan to pay for.'],
            'order_placed' => ['Order placed, payment pending', 2, 'warning', 'Chose a plan and is about to pay. A gentle reminder can help.'],
            'payment_submitted' => ['Payment sent, waiting for approval', 3, 'info', 'They have paid. Our team is checking it, so no action needed.'],
        ];
    @endphp

    <div class="mb-3" style="max-width: 680px;">
        <h2 class="h5 fw-bold mb-1">Waiting to activate</h2>
        <p class="text-secondary small mb-0">
            People you invited who have <strong>not paid yet</strong>. They join your team and tree the moment their payment is approved.
        </p>
    </div>

    @if ($people->isEmpty())
        <div class="card border text-center py-5">
            <div class="card-body">
                <i class="bi bi-check2-circle fs-1 text-success"></i>
                <h3 class="h6 fw-bold mt-2">Nobody is waiting</h3>
                <p class="text-secondary small mb-3">Everyone you invited has paid, or you have not invited anyone yet.</p>
                <a href="{{ route('member.downline.index') }}" class="btn btn-sm btn-primary"><i class="bi bi-share me-1"></i>Get your invite links</a>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($people as $p)
                @php [$label, $step, $tone, $help] = $stages[$p->stage] ?? $stages['registered']; @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="card border h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div class="min-w-0">
                                    <div class="fw-bold text-truncate">{{ $p->name }}</div>
                                    <div class="text-secondary small"><i class="bi bi-telephone me-1"></i>{{ $p->phone }}</div>
                                </div>
                                <span class="badge bg-{{ $tone }}-subtle text-{{ $tone }}-emphasis border border-{{ $tone }}-subtle">{{ $label }}</span>
                            </div>

                            {{-- Progress: Registered, Order placed, Payment sent, Active --}}
                            <div class="d-flex align-items-center gap-1 my-3" aria-hidden="true">
                                @foreach ([1, 2, 3, 4] as $i)
                                    <span class="flex-fill rounded-pill" style="height: 6px; background: {{ $i <= $step ? 'var(--bs-'.$tone.')' : 'var(--bs-border-color)' }};"></span>
                                @endforeach
                            </div>
                            <div class="text-secondary small mb-2">{{ $help }}</div>

                            <div class="d-flex flex-wrap gap-2 small text-secondary mb-3">
                                @if ($p->planName)<span class="badge bg-light text-secondary border fw-normal"><i class="bi bi-box-seam me-1"></i>{{ $p->planName }}</span>@endif
                                <span><i class="bi bi-clock me-1"></i>Joined {{ $p->created_at->diffForHumans() }}</span>
                            </div>

                            <div class="d-flex gap-2">
                                @if ($p->stage !== 'payment_submitted')
                                    <a href="{{ $p->reminderUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-success flex-grow-1"><i class="bi bi-whatsapp me-1"></i>Send reminder</a>
                                @endif
                                <a href="tel:{{ $p->phone }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-telephone"></i> Call</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
