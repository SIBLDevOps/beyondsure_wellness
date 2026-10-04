<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DispatchBatch;
use App\Models\Withdrawal;
use App\Services\DispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DispatchController extends Controller
{
    public function __construct(private readonly DispatchService $dispatch) {}

    public function index(): View
    {
        return view('admin.dispatch.index', [
            'batches' => DispatchBatch::withCount('withdrawals')->orderByDesc('scheduled_for')->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scheduled_for' => ['required', 'date'],
        ], [
            'scheduled_for.required' => 'Scheduled payout date is required.',
            'scheduled_for.date' => 'Scheduled payout date must be a valid date.',
        ]);

        $batch = $this->dispatch->createBatch($data['scheduled_for'], Auth::user());

        foreach ($this->dispatch->availableWithdrawals() as $withdrawal) {
            $this->dispatch->addItem($batch, $withdrawal);
        }

        return redirect()->route('admin.dispatch.show', $batch)->with('status', 'Batch created with all available approved withdrawals.');
    }

    public function show(DispatchBatch $batch): View
    {
        return view('admin.dispatch.show', [
            'batch' => $batch->load('withdrawals.member'),
            'available' => $batch->isDraft() ? $this->dispatch->availableWithdrawals() : collect(),
        ]);
    }

    public function addItem(Request $request, DispatchBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'withdrawal_id' => ['required', 'integer', 'exists:withdrawals,id'],
        ], [
            'withdrawal_id.required' => 'Withdrawal selection is required.',
            'withdrawal_id.exists' => 'Selected withdrawal request does not exist.',
        ]);

        $withdrawal = Withdrawal::findOrFail($data['withdrawal_id']);
        $this->dispatch->addItem($batch, $withdrawal);

        return back()->with('status', 'Withdrawal added to batch.');
    }

    public function removeItem(DispatchBatch $batch, Withdrawal $withdrawal): RedirectResponse
    {
        $this->dispatch->removeItem($batch, $withdrawal);

        return back()->with('status', 'Withdrawal removed from batch.');
    }

    public function release(Request $request, DispatchBatch $batch): RedirectResponse
    {
        $request->validate([
            'reference' => ['required', 'array', 'min:1'],
            'reference.*' => ['required', 'string', 'max:255'],
        ], [
            'reference.required' => 'Payout reference numbers are required.',
            'reference.*.required' => 'Please enter a UTR / reference number for all withdrawals in the batch.',
        ]);

        $references = $request->input('reference', []);

        try {
            $this->dispatch->release($batch, Auth::user(), $references);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['batch' => $e->getMessage()]);
        }

        return redirect()->route('admin.dispatch.show', $batch)->with('status', 'Batch released — all withdrawals marked paid.');
    }
}
