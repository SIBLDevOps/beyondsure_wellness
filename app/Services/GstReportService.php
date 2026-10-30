<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Order;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The monthly GST sales register: every invoice issued in the month, and every credit note issued in the month as a
 * negative line, with the tax split the way the invoice shows it (CGST + SGST, or IGST).
 */
class GstReportService
{
    /** "2026-10" becomes that month's start; anything else becomes the current month. */
    public static function month(mixed $value): Carbon
    {
        if (is_string($value) && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m)) {
            return Carbon::create((int) $m[1], (int) $m[2], 1)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, float|int>}
     */
    public function build(Carbon $month, ?int $regionId = null): array
    {
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();
        $igst = Setting::get('inv_tax_type', 'cgst_sgst') === 'igst';

        $split = function (float $gst, int $sign) use ($igst) {
            $half = round($gst / 2, 2);

            return $igst ? [0.0, 0.0, $sign * $gst] : [$sign * $half, $sign * round($gst - $half, 2), 0.0];
        };

        $inRegion = fn ($q) => $regionId ? $q->whereHas('member', fn ($m) => $m->withoutGlobalScopes()->where('region_id', $regionId)) : $q;

        $invoices = $inRegion(Order::query()->whereNotNull('invoice_number')->whereBetween('invoice_date', [$from, $to]))
            ->with(['member' => fn ($q) => $q->withoutGlobalScopes()->with('region')])->orderBy('invoice_date')->orderBy('invoice_number')->get();

        // Until the database update that creates credit notes has been run there are simply none.
        $notes = ! \Illuminate\Support\Facades\Schema::hasTable('credit_notes') ? collect() : CreditNote::query()
            ->whereBetween('issued_on', [$from, $to])
            ->when($regionId, fn ($q) => $q->whereHas('order', fn ($o) => $o->withoutGlobalScopes()->whereHas('member', fn ($m) => $m->withoutGlobalScopes()->where('region_id', $regionId))))
            ->whereHas('order')
            ->with(['order' => fn ($q) => $q->withoutGlobalScopes()->with(['member' => fn ($m) => $m->withoutGlobalScopes()->with('region')])])
            ->orderBy('issued_on')->orderBy('number')->get();

        $rows = collect();

        foreach ($invoices as $o) {
            [$cgst, $sgst, $ig] = $split($o->gstAmount(), 1);
            $rows->push([
                'type' => 'Invoice', 'number' => $o->invoice_number, 'date' => $o->invoice_date->toDateString(), 'order' => $o->order_code,
                'customer' => $o->billing_name ?: $o->member->name, 'mobile' => $o->billing_phone ?: $o->member->phone, 'region' => $o->member->region?->name ?? '',
                'taxable' => round($o->netOfGst(), 2), 'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $ig, 'total' => round((float) $o->amount, 2), 'against' => '',
            ]);
        }

        foreach ($notes as $n) {
            $o = $n->order;
            [$cgst, $sgst, $ig] = $split((float) $n->gst_amount, -1);
            $rows->push([
                'type' => 'Credit note', 'number' => $n->number, 'date' => $n->issued_on->toDateString(), 'order' => $o->order_code,
                'customer' => $o->billing_name ?: $o->member->name, 'mobile' => $o->billing_phone ?: $o->member->phone, 'region' => $o->member->region?->name ?? '',
                'taxable' => -round((float) $n->taxable_amount, 2), 'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $ig, 'total' => -round((float) $n->amount, 2), 'against' => (string) $o->invoice_number,
            ]);
        }

        $inv = $rows->where('type', 'Invoice');
        $cr = $rows->where('type', 'Credit note');
        $sum = fn (Collection $c, string $k) => round((float) $c->sum($k), 2);

        $totals = [
            'invoices' => $inv->count(),
            'credit_notes' => $cr->count(),
            'taxable' => $sum($inv, 'taxable'),
            'gst' => round($sum($inv, 'total') - $sum($inv, 'taxable'), 2),
            'total' => $sum($inv, 'total'),
            'credit_taxable' => $sum($cr, 'taxable'),
            'credit_gst' => round($sum($cr, 'total') - $sum($cr, 'taxable'), 2),
            'credit_total' => $sum($cr, 'total'),
            'net_taxable' => $sum($rows, 'taxable'),
            'net_cgst' => $sum($rows, 'cgst'),
            'net_sgst' => $sum($rows, 'sgst'),
            'net_igst' => $sum($rows, 'igst'),
            'net_gst' => round($sum($rows, 'total') - $sum($rows, 'taxable'), 2),
            'net_total' => $sum($rows, 'total'),
        ];

        return ['rows' => $rows, 'totals' => $totals];
    }
}
