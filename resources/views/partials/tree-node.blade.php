@if ($node)
    <li>
        <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-1.5 text-sm bg-white">
            <span class="w-2 h-2 rounded-full {{ $node['active_this_month'] ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
            <span class="font-medium">{{ $node['member']->name }}</span>
            <span class="text-xs text-slate-400 font-mono">{{ $node['member']->member_code }}</span>
        </div>

        @if ($node['left'] || $node['right'])
            <ul class="pl-6 mt-2 space-y-2 border-l border-slate-200 ml-2">
                @if ($node['left'])
                    <div class="text-xs text-slate-400 pl-2">Left</div>
                    @include('partials.tree-node', ['node' => $node['left']])
                @endif
                @if ($node['right'])
                    <div class="text-xs text-slate-400 pl-2">Right</div>
                    @include('partials.tree-node', ['node' => $node['right']])
                @endif
            </ul>
        @endif
    </li>
@endif
