<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Region;
use App\Support\Reminder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Pending activation": everyone who registered but whose payment has not been approved yet, by the step they are at,
 * so it is clear who to chase, who to verify and who needs nothing. Built for hundreds of people: the step counts
 * always cover everybody, while the list below is filtered, searched and paged in the database.
 * Region limits apply automatically.
 */
class ActivationController extends Controller
{
    public const STAGES = ['registered', 'order_placed', 'payment_submitted'];

    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $pending = fn () => Member::where('status', '!=', 'active');

        // Every pending person is at exactly one step, so these always add up to the total.
        $counts = [];
        foreach (self::STAGES as $stage) {
            $counts[$stage] = $pending()->atStage($stage)->count();
        }
        $counts['total'] = array_sum($counts);

        $stage = is_string($request->query('stage')) && in_array($request->query('stage'), self::STAGES, true) ? $request->query('stage') : 'all';
        $region = ctype_digit((string) $request->query('region', '')) ? (int) $request->query('region') : null;
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $sort = $request->query('sort') === 'newest' ? 'newest' : 'oldest';

        $people = $pending()
            ->when($stage !== 'all', fn ($q) => $q->atStage($stage))
            ->when($region, fn ($q) => $q->where('region_id', $region))
            ->when($search !== '', function ($q) use ($search) {
                // % and _ are searched as plain characters, not as wildcards.
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $q->where(fn ($w) => $w
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("member_code LIKE ? ESCAPE '!'", [$like]));
            })
            ->with(['sponsor', 'region', 'interestedPlan', 'orders.payment', 'orders.plan'])
            ->orderBy('created_at', $sort === 'newest' ? 'desc' : 'asc')
            ->orderBy('id', $sort === 'newest' ? 'desc' : 'asc')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $brand = \App\Support\Site::name();
        $people->getCollection()->each(function (Member $m) use ($brand) {
            $m->stage = $m->activationStage();
            $plan = $m->orders->where('status', 'pending_payment')->first()?->plan ?? $m->interestedPlan;
            $m->planName = $plan?->name;
            $m->reminderUrl = Reminder::whatsappUrl($m, $plan?->name, $brand);
        });

        return view('admin.activation.index', [
            'people' => $people,
            'counts' => $counts,
            'stage' => $stage,
            'search' => $search,
            'region' => $region,
            'sort' => $sort,
            'regions' => Region::orderBy('sort_order')->get(),
        ]);
    }
}
