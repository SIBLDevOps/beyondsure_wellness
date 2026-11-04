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

    /** After this many days a person who still has not paid counts as "waiting a long time". */
    public const STALE_DAYS = 3;

    public function index(Request $request): View
    {
        $pending = fn () => Member::where('status', '!=', 'active');

        // Every pending person is at exactly one step, so these always add up to the total.
        $counts = [];
        foreach (self::STAGES as $stage) {
            $counts[$stage] = $pending()->atStage($stage)->count();
        }
        $counts['total'] = array_sum($counts);
        // Who has been waiting a long time: the people most worth chasing. Not affected by the filters below.
        $counts['stale'] = $pending()->where('created_at', '<=', now()->subDays(self::STALE_DAYS))->count();

        $stale = $request->boolean('stale');
        $stage = is_string($request->query('stage')) && in_array($request->query('stage'), self::STAGES, true) ? $request->query('stage') : 'all';
        $region = ctype_digit((string) $request->query('region', '')) ? (int) $request->query('region') : null;
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $sort = $request->query('sort') === 'newest' ? 'newest' : 'oldest';

        $people = $pending()
            ->when($stage !== 'all', fn ($q) => $q->atStage($stage))
            ->when($stale, fn ($q) => $q->where('created_at', '<=', now()->subDays(self::STALE_DAYS)))
            ->when($region, fn ($q) => $q->where('region_id', $region))
            ->when($search !== '', function ($q) use ($search) {
                // % and _ are searched as plain characters, not as wildcards.
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $q->where(fn ($w) => $w
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("phone LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("member_code LIKE ? ESCAPE '!'", [$like]));
            })
            ->with(['sponsor', 'placementParent', 'region', 'interestedPlan.products', 'orders.payment', 'orders.plan'])
            ->orderBy('created_at', $sort === 'newest' ? 'desc' : 'asc')
            ->orderBy('id', $sort === 'newest' ? 'desc' : 'asc')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $brand = \App\Support\Site::name();
        // Which level and which leg of their own sponsor's tree each person was placed in.
        $where = app(\App\Services\NetworkService::class)->locateUnderSponsors($people->getCollection());
        $people->getCollection()->each(function (Member $m) use ($brand, $where) {
            $m->level = $where[$m->id]['level'] ?? null;
            $m->leg = ($where[$m->id]['leg'] ?? '') ?: null;
            $m->stage = $m->activationStage();
            $order = $m->orders->where('status', 'pending_payment')->first();
            $plan = $order?->plan ?? $m->interestedPlan;
            $m->planName = $plan?->name;
            // What they still have to pay: the open order's total, else the price of the plan they chose (GST included).
            $m->amount = $order ? (float) $order->amount : ($m->interestedPlan ? $m->interestedPlan->totalPayable() : null);
            // How long they have waited: fresh (under a day), aging (1 to 3 days), stale (3 days or more).
            $hours = $m->created_at->diffInHours(now());
            $m->age = $hours >= self::STALE_DAYS * 24 ? 'stale' : ($hours >= 24 ? 'aging' : 'fresh');
            $m->reminderUrl = Reminder::whatsappUrl($m, $plan?->name, $brand);
        });

        return view('admin.activation.index', [
            'people' => $people,
            'counts' => $counts,
            'stage' => $stage,
            'stale' => $stale,
            'search' => $search,
            'region' => $region,
            'sort' => $sort,
            'regions' => Region::orderBy('sort_order')->get(),
        ]);
    }
}
