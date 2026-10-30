@extends('layouts.member')

@section('title', 'Checkout — ' . $order->plan->name)

@section('content')
    @if (session('welcome_new_member'))
        <div class="alert alert-success d-flex gap-3 align-items-start mb-4" role="status">
            <i class="bi bi-stars fs-4"></i>
            <div>
                <div class="fw-semibold">Welcome, {{ $order->member->name }}! You are one step away.</div>
                <div class="small">
                    Check your plan below and complete the payment.
                    @if ($order->member->sponsor)
                        Once it is approved you become an active member and join <strong>{{ $order->member->sponsor->name }}</strong>&rsquo;s team.
                    @else
                        Once it is approved you become an active member.
                    @endif
                </div>
            </div>
        </div>
    @endif

    @php
        $payment = $order->payment;
        $finished = in_array($order->status, ['paid', 'settled'], true);
        $sent = $finished || ($payment && in_array($payment->status, ['pending', 'verified'], true));
        $rejected = ! $sent && $payment?->status === 'rejected';
        $steps = [
            ['plan', 'Choose plan', 'bi-layers', 'done'],
            ['pay', 'Pay', 'bi-credit-card', $sent ? 'done' : 'current'],
            ['approval', 'Approval', 'bi-patch-check', $finished ? 'done' : ($sent ? 'current' : 'todo')],
            ['active', 'Active', 'bi-rocket-takeoff', $finished ? 'done' : 'todo'],
        ];
    @endphp

    {{-- Where am I? Four steps, the current one highlighted. --}}
    <ol class="list-unstyled d-flex align-items-start justify-content-between gap-2 mb-4 position-relative" aria-label="Progress" style="max-width: 720px;">
        @foreach ($steps as $i => [$key, $label, $icon, $state])
            <li class="text-center flex-fill position-relative" data-step="{{ $key }}" data-state="{{ $state }}" @if ($state === 'current') aria-current="step" @endif>
                @if (! $loop->first)
                    <span class="position-absolute top-0 end-50 w-100 border-top border-2 {{ $state === 'todo' ? 'border-secondary-subtle' : 'border-success' }}" style="margin-top: 17px; z-index: 0;"></span>
                @endif
                <span class="position-relative d-inline-flex align-items-center justify-content-center rounded-circle fw-semibold
                    {{ $state === 'done' ? 'bg-success text-white' : ($state === 'current' ? 'bg-white text-success border border-success border-3' : 'bg-white text-secondary border border-2') }}"
                    style="width: 36px; height: 36px; z-index: 1;">
                    @if ($state === 'done')<i class="bi bi-check-lg"></i>@else<i class="bi {{ $icon }}"></i>@endif
                </span>
                <div class="small mt-1 {{ $state === 'current' ? 'fw-bold text-success' : ($state === 'done' ? 'fw-semibold' : 'text-secondary') }}">{{ $label }}</div>
            </li>
        @endforeach
    </ol>

    <div class="row g-4">
        {{-- Plan / order summary --}}
        <div class="col-lg-5">
            <style>@media (min-width: 992px) { .pay-summary { top: 84px; } }</style>
            <div class="card border sticky-lg-top pay-summary">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-receipt me-2 text-success"></i>Order Summary</h2>
                        <div class="text-body-tertiary small">{{ $order->plan->name }} Plan Bundle</div>
                    </div>
                    <span class="badge bg-light text-dark border font-monospace">{{ $order->order_code }}</span>
                </div>
                {{-- The one number that matters, big. --}}
                <div class="px-3 py-3 bg-success-subtle border-bottom d-flex align-items-end justify-content-between">
                    <div>
                        <div class="small text-success-emphasis">Amount to pay</div>
                        <div class="fs-2 fw-bold lh-1 text-success-emphasis">₹{{ number_format($order->amount, 0) }}</div>
                    </div>
                    <div class="small text-success-emphasis text-end">incl. GST<br>of ₹{{ number_format($order->gstAmount(), 0) }}</div>
                </div>
                @if ($order->plan->description)
                    <div class="card-body pb-2">
                        <div class="text-secondary small rich-text">{!! \App\Support\RichText::render($order->plan->description) !!}</div>
                    </div>
                @endif
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light text-secondary small">
                            <tr>
                                <th class="ps-3">Included Product</th>
                                <th class="text-end pe-3">Qty</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @foreach ($order->plan->products as $product)
                                <tr>
                                    <td class="ps-3">
                                        <i class="bi bi-check2-circle text-success me-1"></i>{{ $product->name }}
                                        @if ($product->description)
                                            <div class="text-secondary" style="font-size:.75rem;">{{ \App\Support\RichText::snippet($product->description, 140) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3 fw-semibold">{{ $product->pivot->qty }}×</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light small">
                            <tr>
                                <td class="ps-3 text-secondary">Taxable Value</td>
                                <td class="text-end pe-3">₹{{ number_format($order->netOfGst(), 2) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-secondary">GST ({{ rtrim(rtrim(number_format($order->gstPct(), 2), '0'), '.') }}%)</td>
                                <td class="text-end pe-3">₹{{ number_format($order->gstAmount(), 2) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-3 text-secondary">Business Volume (BV)</td>
                                <td class="text-end pe-3 text-success fw-medium">₹{{ number_format($order->bv, 2) }}</td>
                            </tr>
                            <tr>
                                <th class="ps-3 py-2">Amount Due (incl. GST)</th>
                                <th class="text-end pe-3 py-2 fs-6 text-dark">₹{{ number_format($order->amount, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Billing + payment --}}
        <div class="col-lg-7">
            @if ($sent)
                <div class="card border">
                    <div class="card-body p-4">
                        <div class="d-flex gap-3 align-items-start">
                            <span class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;"><i class="bi bi-hourglass-split fs-4"></i></span>
                            <div>
                                <h3 class="h5 fw-bold mb-1">{{ $finished ? 'Payment approved' : 'We have your payment' }}</h3>
                                <p class="text-secondary mb-0">
                                    @if ($finished)
                                        You are an active member now.
                                    @else
                                        Our team is checking it against the bank. This usually takes a short while; there is nothing more for you to do.
                                    @endif
                                </p>
                            </div>
                        </div>

                        <dl class="row small mt-4 mb-3">
                            <dt class="col-5 col-sm-4 text-secondary fw-normal">Order</dt><dd class="col-7 col-sm-8 font-monospace">{{ $order->order_code }}</dd>
                            @if ($payment)
                                <dt class="col-5 col-sm-4 text-secondary fw-normal">Method</dt><dd class="col-7 col-sm-8 text-capitalize">{{ str_replace('_', ' ', $payment->method) }}</dd>
                                @if ($payment->reference)
                                    <dt class="col-5 col-sm-4 text-secondary fw-normal">Reference</dt><dd class="col-7 col-sm-8 font-monospace">{{ $payment->reference }}</dd>
                                @endif
                            @endif
                        </dl>

                        @unless ($finished)
                            <div class="border rounded-3 p-3 bg-light-subtle small">
                                <div class="fw-semibold mb-1">What happens next</div>
                                <ol class="mb-0 ps-3 text-secondary">
                                    <li>We confirm your payment.</li>
                                    <li>Your account becomes active.</li>
                                    <li>Your wallet, team and earnings open up.</li>
                                </ol>
                            </div>
                        @endunless
                        @if ($finished)
                            <a href="{{ route('member.orders.invoice', $order) }}" target="_blank" rel="noopener" class="btn btn-success mt-3 me-2"><i class="bi bi-receipt me-1"></i>Download invoice</a>
                        @endif
                        <a href="{{ route('member.orders.index') }}" class="btn btn-sm btn-outline-success mt-3">View my orders &rarr;</a>
                    </div>
                </div>
            @else
                @if ($rejected)
                    <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
                        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                        <div>
                            <strong>Your last payment could not be verified.</strong>
                            Please check the reference number against your bank receipt and send it again below.
                        </div>
                    </div>
                @endif
                <div class="card border">
                    <div class="card-header bg-light-subtle border-bottom py-3">
                        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-credit-card me-2 text-success"></i>Billing &amp; Payment Details</h3>
                        <div class="text-body-tertiary small">Confirm your billing details and submit your payment reference</div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('member.orders.submit-payment', $order) }}" id="billing-form">
                            @csrf
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Full Name <span class="text-danger">*</span></label>
                                    <input id="billing_name" name="billing_name" value="{{ old('billing_name', $billingName) }}" required
                                        class="form-control @error('billing_name') is-invalid @enderror">
                                    @error('billing_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium small">Phone Number <span class="text-danger">*</span></label>
                                    <input id="billing_phone" name="billing_phone" value="{{ old('billing_phone', $billingPhone) }}" required
                                        pattern="[6-9][0-9]{9}" maxlength="10" inputmode="numeric"
                                        class="form-control @error('billing_phone') is-invalid @enderror">
                                    @error('billing_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-medium small">Email <span class="text-body-tertiary fw-normal">(optional)</span></label>
                                <input id="billing_email" name="billing_email" type="email" value="{{ old('billing_email', $billingEmail) }}"
                                    class="form-control @error('billing_email') is-invalid @enderror">
                                @error('billing_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-medium small">Billing Address <span class="text-body-tertiary fw-normal">(optional)</span></label>
                                <textarea id="billing_address" name="billing_address" rows="2"
                                    class="form-control @error('billing_address') is-invalid @enderror">{{ old('billing_address') }}</textarea>
                                @error('billing_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @if ($razorpayConfigured)
                                <div class="mb-2">
                                    <button type="button" id="razorpay-btn" class="btn btn-success w-100 py-2">
                                        <i class="bi bi-shield-lock-fill me-1"></i> Pay ₹{{ number_format($order->amount, 2) }} securely
                                    </button>
                                    <p id="razorpay-error" class="text-danger small mt-2 d-none mb-0"></p>
                                </div>

                                <div class="position-relative text-center my-3">
                                    <hr>
                                    <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-body-tertiary small">or pay manually</span>
                                </div>
                            @endif

                            <div class="border rounded-3 p-3 bg-light-subtle mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="small fw-semibold"><i class="bi bi-bank me-1 text-success"></i>Manual Transfer / UPI Steps</div>
                                    <span class="badge bg-success-subtle text-success">Due: ₹{{ number_format($order->amount, 2) }}</span>
                                </div>

                                {{-- Who to pay: set by the company under Admin -> Website -> Payment details. --}}
                                @if ($payDetails)
                                    @php
                                        $rows = [
                                            'pay_upi_id' => 'UPI ID', 'pay_account_name' => 'Account name', 'pay_bank_name' => 'Bank',
                                            'pay_account_number' => 'Account number', 'pay_ifsc' => 'IFSC',
                                        ];
                                    @endphp
                                    <div class="bg-white border rounded-3 mb-3">
                                        <div class="px-3 py-2 border-bottom small fw-semibold"><i class="bi bi-send-check me-1 text-success"></i>Pay to</div>
                                        @foreach ($rows as $key => $label)
                                            @if (isset($payDetails[$key]))
                                                <div class="d-flex align-items-center justify-content-between gap-2 px-3 py-2 border-bottom small">
                                                    <span class="text-secondary">{{ $label }}</span>
                                                    <span class="d-flex align-items-center gap-2 text-end">
                                                        <span class="fw-semibold font-monospace text-break">{{ $payDetails[$key] }}</span>
                                                        @if (in_array($key, ['pay_upi_id', 'pay_account_number', 'pay_ifsc'], true))
                                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" data-copy-text="{{ $payDetails[$key] }}" title="Copy"><i class="bi bi-copy"></i></button>
                                                        @endif
                                                    </span>
                                                </div>
                                            @endif
                                        @endforeach
                                        @if (isset($payDetails['pay_note']))
                                            <div class="px-3 py-2 small text-secondary"><i class="bi bi-info-circle me-1"></i>{{ $payDetails['pay_note'] }}</div>
                                        @endif
                                    </div>
                                @else
                                    <div class="alert alert-warning small py-2">
                                        <i class="bi bi-info-circle me-1"></i>The company's payment details are not set up yet. Please contact us on {{ \App\Support\Site::text('site_contact_phone') }} and we will share them.
                                    </div>
                                @endif

                                <div class="small text-secondary mb-3 p-2.5 bg-white rounded-2 border">
                                    <ol class="mb-0 ps-3">
                                        <li class="mb-1">Transfer ₹{{ number_format($order->amount, 0) }} to the account above or via UPI.</li>
                                        <li class="mb-1">Locate the <strong>12-digit UTR / Reference ID</strong> on your transaction receipt.</li>
                                        <li>Select your payment mode and paste the reference number below to submit for verification.</li>
                                    </ol>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-medium">Payment Method <span class="text-danger">*</span></label>
                                        <select name="method" class="form-select @error('method') is-invalid @enderror">
                                            <option value="upi" @selected(old('method') === 'upi')>UPI (Google Pay / PhonePe / Paytm)</option>
                                            <option value="bank_transfer" @selected(old('method') === 'bank_transfer')>Bank Transfer (IMPS / NEFT / RTGS)</option>
                                            <option value="cash" @selected(old('method') === 'cash')>Cash Deposit / Counter</option>
                                        </select>
                                        @error('method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-medium">Reference / UTR Number <span class="text-danger">*</span></label>
                                        <input name="reference" value="{{ old('reference') }}" class="form-control font-monospace @error('reference') is-invalid @enderror" placeholder="e.g. 429381029482">
                                        @error('reference') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn {{ $razorpayConfigured ? 'btn-outline-secondary' : 'btn-success' }} w-100">
                                <i class="bi bi-check2-circle me-1"></i>Submit payment for verification
                            </button>

                            @if (! $razorpayConfigured)
                                <p class="text-body-tertiary small text-center mt-2 mb-0">
                                    Online card/UPI checkout is being set up. Pay via bank transfer or UPI and submit the reference above — our team will verify it.
                                </p>
                            @endif
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($razorpayConfigured && ! $order->payment)
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            document.getElementById('razorpay-btn').addEventListener('click', async function () {
                const btn = this;
                const errorEl = document.getElementById('razorpay-error');
                errorEl.classList.add('d-none');

                const name = document.getElementById('billing_name').value.trim();
                const phone = document.getElementById('billing_phone').value.trim();

                if (! name || ! phone) {
                    errorEl.textContent = 'Please fill in your name and phone number first.';
                    errorEl.classList.remove('d-none');
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = 'Preparing payment…';

                try {
                    const res = await fetch('{{ route('member.orders.razorpay-order', $order) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            billing_name: name,
                            billing_email: document.getElementById('billing_email').value.trim(),
                            billing_phone: phone,
                            billing_address: document.getElementById('billing_address').value.trim(),
                        }),
                    });

                    const data = await res.json();

                    if (! res.ok) {
                        throw new Error(data.message || 'Could not start payment. Please try the manual option below.');
                    }

                    const rzp = new Razorpay({
                        key: data.key,
                        amount: data.amount,
                        currency: data.currency,
                        name: data.name,
                        description: data.description,
                        order_id: data.razorpay_order_id,
                        prefill: data.prefill,
                        theme: { color: 'var(--brand-600, #059669)' },
                        handler: function (response) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '{{ route('member.orders.razorpay-callback', $order) }}';

                            const fields = {
                                _token: '{{ csrf_token() }}',
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_signature: response.razorpay_signature,
                            };

                            for (const key in fields) {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = key;
                                input.value = fields[key];
                                form.appendChild(input);
                            }

                            document.body.appendChild(form);
                            form.submit();
                        },
                        modal: {
                            ondismiss: function () {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="bi bi-shield-lock-fill me-1"></i> Pay ₹{{ number_format($order->amount, 2) }} securely';
                            },
                        },
                    });

                    rzp.open();
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-shield-lock-fill me-1"></i> Pay ₹{{ number_format($order->amount, 2) }} securely';
                } catch (err) {
                    errorEl.textContent = err.message;
                    errorEl.classList.remove('d-none');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-shield-lock-fill me-1"></i> Pay ₹{{ number_format($order->amount, 2) }} securely';
                }
            });
        </script>
    @endif
@endsection
