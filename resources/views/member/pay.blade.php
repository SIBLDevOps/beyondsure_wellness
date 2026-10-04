@extends('layouts.member')

@section('title', 'Checkout — ' . $order->plan->name)

@section('content')
    <div class="mb-3">
        <a href="{{ route('member.orders.index') }}" class="small text-secondary text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>Back to My Orders
        </a>
    </div>

    <div class="row g-4">
        {{-- Plan / order summary --}}
        <div class="col-lg-5">
            <div class="card border">
                <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-receipt me-2 text-success"></i>Order Summary</h2>
                        <div class="text-body-tertiary small">{{ $order->plan->name }} Plan Bundle</div>
                    </div>
                    <span class="badge bg-light text-dark border font-monospace">{{ $order->order_code }}</span>
                </div>
                <div class="card-body pb-2">
                    <p class="text-secondary small mb-0">{{ $order->plan->description }}</p>
                </div>
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
                                    <td class="ps-3"><i class="bi bi-check2-circle text-success me-1"></i>{{ $product->name }}</td>
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
            @if ($order->payment)
                <div class="card border">
                    <div class="card-header bg-light-subtle border-bottom py-3">
                        <h3 class="h6 mb-0 fw-semibold"><i class="bi bi-check2-circle me-2 text-success"></i>Payment Submission Status</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning py-2 mb-3">
                            Payment already submitted — status: <strong class="text-capitalize">{{ $order->payment->status }}</strong>.
                            @if ($order->payment->status === 'pending')
                                Our team will verify it shortly.
                            @endif
                        </div>
                        <a href="{{ route('member.orders.index') }}" class="btn btn-sm btn-outline-success">View my orders &rarr;</a>
                    </div>
                </div>
            @else
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

                                <div class="small text-secondary mb-3 p-2.5 bg-white rounded-2 border">
                                    <ol class="mb-0 ps-3">
                                        <li class="mb-1">Transfer ₹{{ number_format($order->amount, 0) }} to the company bank account or via UPI.</li>
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
                        theme: { color: '#059669' },
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
