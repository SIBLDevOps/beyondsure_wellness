<?php

namespace App\Services;

use App\Models\Cycle;
use App\Models\User;

class CycleService
{
    public function __construct(private readonly CompensationEngine $engine) {}

    public function create(string $periodStart, string $periodEnd): Cycle
    {
        return Cycle::create([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'draft',
        ]);
    }

    /**
     * Admin fully controls when this runs — nothing here happens on a timer. This is the
     * moment earned income is posted to the ledger (paying it OUT is a separate, later
     * dispatch step — see DispatchService).
     */
    public function approve(Cycle $cycle, User $admin): void
    {
        if ($cycle->isApproved()) {
            throw new \RuntimeException('This cycle has already been approved.');
        }

        $this->engine->run($cycle);
        $cycle->update(['approved_by' => $admin->id]);
    }
}
