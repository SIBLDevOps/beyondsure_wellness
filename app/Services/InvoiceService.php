<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Support\AmountInWords;
use App\Support\Site;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tax invoices. A paid order gets a number that is unique and consecutive within its financial year (April to March),
 * written once and never changed. The amounts on an invoice are always the order's own snapshot (what was really
 * charged), only spread across the products in the plan for display.
 */
class InvoiceService
{
    /** Financial year label for a date: 1 Apr 2026 to 31 Mar 2027 is "2026-27". */
    public static function financialYear(Carbon $date): string
    {
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return $start.'-'.substr((string) ($start + 1), -2);
    }

    /** Short letters in front of every number. Anything but letters, digits and "-" falls back to INV. */
    public static function prefix(): string
    {
        $prefix = trim((string) Setting::get('inv_prefix', ''));

        return preg_match('/^[A-Za-z0-9\-]{1,10}$/', $prefix) ? $prefix : 'INV';
    }

    /**
     * Gives a paid order its invoice number and date, once. Safe to call again: an order that already has a number keeps it.
     * Never lets a billing problem break taking a payment, so it quietly does nothing until the invoice columns exist.
     */
    public function issue(Order $order): void
    {
        if (! in_array($order->status, ['paid', 'settled'], true) || ! self::ready()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $locked = Order::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || $locked->invoice_number) {
                $order->setRawAttributes($locked?->getAttributes() ?? $order->getAttributes(), true);

                return;
            }

            $date = ($locked->payment?->verified_at ?? $locked->updated_at ?? now())->copy()->startOfDay();
            $fy = self::financialYear($date);

            DB::table('invoice_sequences')->insertOrIgnore(['financial_year' => $fy, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = DB::table('invoice_sequences')->where('financial_year', $fy)->lockForUpdate()->first();
            $next = ((int) $sequence->last_number) + 1;
            DB::table('invoice_sequences')->where('financial_year', $fy)->update(['last_number' => $next, 'updated_at' => now()]);

            $locked->forceFill([
                'invoice_number' => self::prefix().'/'.$fy.'/'.str_pad((string) $next, 5, '0', STR_PAD_LEFT),
                'invoice_date' => $date->toDateString(),
            ])->save();

            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private static ?bool $ready = null;

    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasColumn('orders', 'invoice_number') && Schema::hasTable('invoice_sequences');
    }

    /**
     * One row per product in the plan: its share of the order's taxable value, and the GST on it. The shares follow retail
     * value (as the order's own GST does) and are scaled so the lines add up to the order to the paisa, whatever the
     * product rates are today.
     *
     * @return Collection<int, array{name: string, qty: int, taxable: float, rate: float, gst: float, total: float}>
     */
    public function lines(Order $order): Collection
    {
        $products = $order->plan?->products()->get() ?? collect();
        $taxable = $order->netOfGst();
        $gstTotal = $order->gstAmount();

        if ($products->isEmpty()) {
            return collect([[
                'name' => $order->plan?->name ?? 'Plan', 'qty' => 1, 'taxable' => round($taxable, 2),
                'rate' => $taxable > 0 ? round($gstTotal / $taxable * 100, 2) : 0.0, 'gst' => round($gstTotal, 2), 'total' => round($taxable + $gstTotal, 2),
            ]]);
        }

        $weights = $products->map(fn ($p) => max(0.01, (float) $p->sell_price) * max(1, (int) $p->pivot->qty));
        $weightSum = $weights->sum();

        // GST per product at the rate it carries now, only to know how the order's real GST is shared between them.
        $rawGst = $products->map(fn ($p, $i) => $taxable * ($weights[$i] / $weightSum) * $p->gstPct() / 100);
        $rawSum = $rawGst->sum();

        $rows = [];
        $takenTaxable = 0.0;
        $takenGst = 0.0;
        $last = $products->count() - 1;

        foreach ($products as $i => $product) {
            $lineTaxable = $i === $last ? round($taxable - $takenTaxable, 2) : round($taxable * $weights[$i] / $weightSum, 2);
            $share = $rawSum > 0 ? $rawGst[$i] / $rawSum : $weights[$i] / $weightSum;
            $lineGst = $i === $last ? round($gstTotal - $takenGst, 2) : round($gstTotal * $share, 2);

            $takenTaxable += $lineTaxable;
            $takenGst += $lineGst;

            $rows[] = [
                'name' => $product->name,
                'qty' => (int) $product->pivot->qty,
                'taxable' => $lineTaxable,
                'rate' => $lineTaxable > 0 ? round($lineGst / $lineTaxable * 100, 2) : 0.0,
                'gst' => $lineGst,
                'total' => round($lineTaxable + $lineGst, 2),
            ];
        }

        return collect($rows);
    }

    /** Everything the invoice page prints. */
    public function build(Order $order, ?\App\Models\CreditNote $credit = null): array
    {
        $order->loadMissing('member', 'plan', 'payment');
        $lines = $this->lines($order);
        $gst = round($lines->sum('gst'), 2);
        $igst = Setting::get('inv_tax_type', 'cgst_sgst') === 'igst';
        $half = round($gst / 2, 2);

        $text = fn (string $key, string $fallback = '') => trim((string) Setting::get($key, '')) ?: $fallback;
        $gstin = $text('inv_gstin');

        return [
            'title' => $credit ? 'CREDIT NOTE' : ($gstin !== '' ? 'TAX INVOICE' : 'INVOICE'),
            'credit' => $credit,
            'originalInvoice' => ['number' => $order->invoice_number, 'date' => $order->invoice_date],
            'order' => $order,
            'number' => $credit ? $credit->number : $order->invoice_number,
            'date' => $credit ? $credit->issued_on : ($order->invoice_date ?? $order->payment?->verified_at ?? $order->updated_at),
            'seller' => [
                'name' => $text('inv_legal_name', Site::name()),
                'brand' => Site::name(),
                'logo' => Site::logoUrl(),
                'gstin' => $gstin,
                'pan' => $text('inv_pan'),
                'address' => $text('inv_address', Site::text('site_contact_address')),
                'state' => $text('inv_state'),
                'phone' => Site::text('site_contact_phone'),
                'email' => Site::text('site_contact_email'),
            ],
            'buyer' => [
                'name' => $order->billing_name ?: $order->member->name,
                'phone' => $order->billing_phone ?: $order->member->phone,
                'email' => $order->billing_email ?: $order->member->email,
                'address' => $order->billing_address,
                'code' => $order->member->member_code,
            ],
            'lines' => $lines,
            'taxable' => round($lines->sum('taxable'), 2),
            'gst' => $gst,
            'igst' => $igst,
            // Half each; the second half takes any odd paisa so the two always add up to the GST.
            'cgst' => $igst ? 0.0 : $half,
            'sgst' => $igst ? 0.0 : round($gst - $half, 2),
            'total' => round($lines->sum('total'), 2),
            'words' => AmountInWords::rupees($lines->sum('total')),
            'terms' => $text('inv_terms'),
            'payment' => $order->payment,
        ];
    }
}
