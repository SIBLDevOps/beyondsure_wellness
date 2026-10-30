<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\LedgerEntry;
use App\Models\Member;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * "What would the next cycle pay?" It runs the real compensation engine on today's paid orders and today's settings,
 * reads what it would have posted, and then throws all of it away: the transaction is rolled back, so no cycle, no
 * ledger line, no order change, no leg total and no rank is left behind.
 */
class CycleSimulator
{
    public function __construct(private readonly CycleService $cycles) {}

    /**
     * @return array{orders: int, bv: float, pool: float, total_paid: float, totals: array<string, float>, by_level: array<int, float>, earners: list<array{name: string, code: string, total: float}>}
     */
    public function run(): array
    {
        $waiting = Order::withoutGlobalScopes()->where('status', 'paid')->whereNull('cycle_id');
        $result = ['orders' => 0, 'bv' => (float) (clone $waiting)->sum('bv'), 'pool' => 0.0, 'total_paid' => 0.0,
            'totals' => ['self' => 0.0, 'sponsor' => 0.0, 'matching' => 0.0, 'rank' => 0.0], 'by_level' => [], 'earners' => []];

        if ((clone $waiting)->count() === 0) {
            return $result;
        }

        DB::beginTransaction();

        try {
            $cycle = $this->cycles->create(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
            app(CompensationEngine::class)->run($cycle);
            $cycle->refresh();

            $entries = LedgerEntry::withoutGlobalScopes()->where('cycle_id', $cycle->id)->where('amount', '>', 0)->get();

            $result['orders'] = Order::withoutGlobalScopes()->where('cycle_id', $cycle->id)->count();
            $result['pool'] = (float) $cycle->pool_bv;
            $result['total_paid'] = (float) $cycle->total_paid;

            foreach ($entries->groupBy('type') as $type => $rows) {
                if (isset($result['totals'][$type])) {
                    $result['totals'][$type] = round((float) $rows->sum('amount'), 2);
                }
            }

            foreach ($entries->where('type', 'matching') as $e) {
                if (preg_match('/Level (\d+)\)/', (string) $e->description, $m)) {
                    $result['by_level'][(int) $m[1]] = round(($result['by_level'][(int) $m[1]] ?? 0) + (float) $e->amount, 2);
                }
            }
            ksort($result['by_level']);

            $top = $entries->groupBy('member_id')->map(fn ($rows) => round((float) $rows->sum('amount'), 2))->sortDesc()->take(10);
            $members = Member::withoutGlobalScopes()->whereIn('id', $top->keys())->get()->keyBy('id');
            foreach ($top as $id => $total) {
                $result['earners'][] = ['name' => $members[$id]->name, 'code' => $members[$id]->member_code, 'total' => $total];
            }
        } finally {
            DB::rollBack();   // nothing the engine did is kept
        }

        return $result;
    }
}
