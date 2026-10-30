<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\CycleSimulator;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The live compensation rules, and a no-risk "what would the next cycle pay?" run. Company-wide, so never for region-limited staff. */
class CycleSimulationController extends Controller
{
    /** The rules exactly as the engine will read them right now. */
    private function rules(): array
    {
        $levels = Setting::getLevelPercentages();

        return [
            'self' => (float) Setting::get('self_pct', 10),
            'sponsor' => (float) Setting::get('sponsor_pct', 20),
            'levels' => array_slice($levels, 0, max(1, (int) Setting::get('cascade_depth', 7)), true),
            'depth' => (int) Setting::get('cascade_depth', 7),
            'rank_pool' => (float) Setting::get('rank_pool_pct', 3),
            'split' => ['cost' => (float) Setting::get('split_cost_pct', 40), 'pool' => (float) Setting::get('split_pool_pct', 40), 'company' => (float) Setting::get('split_company_pct', 20)],
            'breaker' => (float) Setting::get('circuit_breaker_pct', 60),
            'cap_cycle' => (float) Setting::get('match_cap_per_cycle', 600000),
            'cap_week' => (float) Setting::get('match_cap_per_week', 150000),
            'eligibility' => Setting::getEligibilityMinVolumes(),
        ];
    }

    private function guard(Request $request): void
    {
        abort_if($request->user()->isRegionRestricted(), 403, 'A cycle covers every region, so only staff with access to all regions can simulate one.');
    }

    public function show(Request $request): View
    {
        $this->guard($request);

        return view('admin.cycles.simulate', ['rules' => $this->rules(), 'result' => null]);
    }

    public function run(Request $request, CycleSimulator $simulator): View
    {
        $this->guard($request);

        return view('admin.cycles.simulate', ['rules' => $this->rules(), 'result' => $simulator->run()]);
    }
}
