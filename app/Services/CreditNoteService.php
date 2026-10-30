<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reverses a paid order that was never settled into a cycle. No income has been posted for it yet, so cancelling it
 * and issuing a credit note undoes the sale completely. A settled order is refused: reversing posted income is a
 * separate decision for the company.
 */
class CreditNoteService
{
    public function issue(Order $order, string $reason, User $by, ?string $method = null, ?string $reference = null): CreditNote
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('credit_notes') || ! \Illuminate\Support\Facades\Schema::hasTable('credit_note_sequences')) {
            throw new \RuntimeException('Credit notes need a database update that has not been run yet. Run: php artisan migrate --force');
        }

        $note = DB::transaction(function () use ($order, $reason, $by, $method, $reference) {
            $locked = Order::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->creditNote()->exists()) {
                throw new \RuntimeException('This order already has a credit note.');
            }
            if ($locked->status === 'settled') {
                throw new \RuntimeException('This order was already settled into a cycle, so its income has been paid out. It cannot be reversed here.');
            }
            if ($locked->status !== 'paid') {
                throw new \RuntimeException('Only a paid order can have a credit note.');
            }

            app(InvoiceService::class)->issue($locked);   // an invoice is needed to reverse
            $date = now()->startOfDay();
            $fy = InvoiceService::financialYear($date);

            DB::table('credit_note_sequences')->insertOrIgnore(['financial_year' => $fy, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $next = ((int) DB::table('credit_note_sequences')->where('financial_year', $fy)->lockForUpdate()->value('last_number')) + 1;
            DB::table('credit_note_sequences')->where('financial_year', $fy)->update(['last_number' => $next, 'updated_at' => now()]);

            $note = CreditNote::create([
                'order_id' => $locked->id,
                'number' => 'CN/'.$fy.'/'.str_pad((string) $next, 5, '0', STR_PAD_LEFT),
                'issued_on' => $date->toDateString(),
                'reason' => $reason,
                'taxable_amount' => $locked->netOfGst(),
                'gst_amount' => $locked->gstAmount(),
                'amount' => $locked->amount,
                'refund_method' => $method,
                'refund_reference' => $reference ?: null,
                'refunded_on' => $method ? $date->toDateString() : null,
                'created_by' => $by->id,
            ]);

            $locked->update(['status' => 'cancelled']);

            // Without any other paid purchase the buyer is no longer an active member.
            $member = $locked->member()->withoutGlobalScopes()->first();
            if ($member && ! Order::withoutGlobalScopes()->where('member_id', $member->id)->whereIn('status', ['paid', 'settled'])->exists()) {
                $member->update(['status' => 'inactive']);
            }

            return $note;
        });

        app(MemberMailer::class)->creditNoteIssued($note);

        return $note;
    }
}
