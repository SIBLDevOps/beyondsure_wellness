@php
    $shareCode = $member->member_code;
    $shareLinks = [
        'left' => ['Left leg link', route('register', ['sponsor' => $shareCode, 'side' => 'left'])],
        'right' => ['Right leg link', route('register', ['sponsor' => $shareCode, 'side' => 'right'])],
    ];
@endphp
<div class="card border-success mb-4" id="share-links">
    <div class="card-body">
        <div class="fw-bold"><i class="bi bi-share me-1 text-success"></i>Share your link now</div>
        <div class="small text-secondary mt-1 mb-3" style="max-width: 70ch;">
            You can invite people before you activate. <strong>Activate by 23:59 today</strong> and you earn sponsor income on everything your team buys today.
            If you activate on a later day, you earn from that day on; purchases made before it are not paid to you.
        </div>
        <div class="row g-2">
            @foreach ($shareLinks as $side => [$label, $url])
                <div class="col-12 col-md-6">
                    <label class="small text-secondary mb-1" for="share_{{ $side }}">{{ $label }}</label>
                    <div class="input-group input-group-sm">
                        <input id="share_{{ $side }}" type="text" readonly value="{{ $url }}" class="form-control font-monospace bg-light text-truncate">
                        <button type="button" class="btn btn-success" data-copy-text="{{ $url }}"><i class="bi bi-clipboard me-1"></i>Copy</button>
                        <a class="btn btn-outline-success" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode('Join me on '.\App\Support\Site::name().': '.$url) }}"><i class="bi bi-whatsapp"></i></a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
