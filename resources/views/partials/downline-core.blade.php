{{-- Shared by admin/members/network.blade.php and member/downline.blade.php per Phase 2 §3.3 --}}
@php
    $left = (float) ($legTotal->left_bv ?? 0);
    $right = (float) ($legTotal->right_bv ?? 0);
    $matched = (float) ($legTotal->matched_bv ?? 0);
    $max = max($left, $right, 1);
@endphp

<div class="bg-white rounded-xl border border-slate-200 p-5 mb-6">
    <h2 class="font-semibold text-slate-900 mb-3">Leg balance</h2>
    <div class="space-y-3 text-sm">
        <div>
            <div class="flex justify-between text-slate-500 mb-1"><span>Left leg BV</span><span>₹{{ number_format($left, 0) }}</span></div>
            <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-sky-500" style="width: {{ min(100, $left / $max * 100) }}%"></div></div>
        </div>
        <div>
            <div class="flex justify-between text-slate-500 mb-1"><span>Right leg BV</span><span>₹{{ number_format($right, 0) }}</span></div>
            <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ min(100, $right / $max * 100) }}%"></div></div>
        </div>
        <div class="flex justify-between pt-2 border-t border-slate-100">
            <span class="text-slate-500">Matched so far</span>
            <span class="font-semibold">₹{{ number_format($matched, 0) }}</span>
        </div>
        <div class="flex justify-between text-xs text-slate-400">
            <span>Unmatched carry-forward — left</span>
            <span>₹{{ number_format(max(0, $left - $matched), 0) }}</span>
        </div>
        <div class="flex justify-between text-xs text-slate-400">
            <span>Unmatched carry-forward — right</span>
            <span>₹{{ number_format(max(0, $right - $matched), 0) }}</span>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 p-5 mb-6">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-slate-900">Per-generation breakdown</h2>
        <form method="GET" class="flex items-center gap-2 text-xs text-slate-500">
            Depth
            <select name="depth" onchange="this.form.submit()" class="rounded border-slate-300 text-xs">
                @foreach ([2,3,4,5,6] as $d)
                    <option value="{{ $d }}" @selected($depth == $d)>{{ $d }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <table class="w-full text-sm">
        <thead class="text-slate-400 text-left text-xs">
            <tr><th class="py-1">Level</th><th class="py-1">Members</th><th class="py-1">Active this month</th><th class="py-1">BV contributed</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($levelBreakdown as $row)
                <tr>
                    <td class="py-1.5">Level {{ $row['level'] }}</td>
                    <td class="py-1.5">{{ $row['count'] }}</td>
                    <td class="py-1.5">{{ $row['active_count'] }}</td>
                    <td class="py-1.5">₹{{ number_format($row['bv'], 0) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-1.5 text-slate-400">No downline yet at this depth.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="text-xs text-slate-400 mt-3">
        Members below level 6 still add volume to your legs and help your own matching; only their personal
        match stops paying you.
    </p>
</div>

<div class="bg-white rounded-xl border border-slate-200 p-5">
    <h2 class="font-semibold text-slate-900 mb-3">Team tree</h2>
    <ul>
        @include('partials.tree-node', ['node' => $tree])
    </ul>
</div>
