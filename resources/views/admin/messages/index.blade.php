@extends('layouts.admin')

@section('title', 'Contact messages')

@section('content')
    <p class="text-secondary mb-3">
        Submissions from the public homepage contact form. Unread messages are highlighted and can be marked as read.
    </p>

    <div class="card border">
        <div class="card-header bg-light-subtle border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-0 fw-semibold"><i class="bi bi-envelope-paper me-2 text-success"></i>Public Contact Inquiries</h2>
                <div class="text-body-tertiary small">Customer &amp; prospect messages submitted via the website</div>
            </div>
            <span class="badge bg-light text-secondary border">{{ $messages->total() }} total</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-secondary small">
                    <tr>
                        <th>Sender</th>
                        <th>Contact Details</th>
                        <th>Subject &amp; Message</th>
                        <th>Status</th>
                        <th>Received</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($messages as $message)
                        <tr class="{{ $message->read_at ? '' : 'table-success bg-opacity-10' }}">
                            <td>
                                <div class="fw-semibold">{{ $message->name }}</div>
                            </td>
                            <td class="small">
                                <div><i class="bi bi-envelope text-secondary me-1"></i>{{ $message->email }}</div>
                                @if ($message->phone)
                                    <div class="text-body-tertiary"><i class="bi bi-telephone me-1"></i>{{ $message->phone }}</div>
                                @endif
                            </td>
                            <td style="max-width: 420px;">
                                @if ($message->subject)
                                    <div class="fw-semibold small mb-1">{{ $message->subject }}</div>
                                @endif
                                <div class="small text-secondary" style="white-space: pre-line;">{{ $message->message }}</div>
                            </td>
                            <td>
                                @if ($message->read_at)
                                    <span class="badge rounded-pill bg-secondary-subtle text-secondary">Read</span>
                                @else
                                    <span class="badge rounded-pill bg-success-subtle text-success">Unread</span>
                                @endif
                            </td>
                            <td class="text-body-tertiary small text-nowrap">{{ $message->created_at->format('d M Y, H:i') }}</td>
                            <td class="text-end">
                                @if (!$message->read_at)
                                    <form method="POST" action="{{ route('admin.messages.read', $message) }}">
                                        @csrf @method('PUT')
                                        <button class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-check2-all me-1"></i>Mark read
                                        </button>
                                    </form>
                                @else
                                    <span class="text-body-tertiary small"><i class="bi bi-check2"></i> Done</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-tertiary py-4">No messages yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $messages->links('pagination::bootstrap-5') }}</div>
@endsection
