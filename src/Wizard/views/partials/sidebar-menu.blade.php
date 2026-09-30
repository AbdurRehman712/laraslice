{{--
    LaraSlice Dynamic Sidebar Navigation
    
    Usage: @include('laraslice::partials.sidebar-menu')
    
    This partial automatically renders all active slices with their
    child entities (tables/routes) as a collapsible sidebar menu.
    
    The `$laraslice_nav` variable is automatically shared via the
    LaraSliceServiceProvider view composer.
--}}

@if (!empty($laraslice_nav))
<nav class="laraslice-sidebar-nav space-y-1">
    <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground/60">
        Slices
    </div>

    @foreach ($laraslice_nav as $slice)
        @if (!empty($slice['children']))
            {{-- Collapsible Slice Group with children --}}
            <div x-data="{ open: {{ $slice['active'] ? 'true' : 'false' }} }" class="group">
                <button type="button"
                    @click="open = !open"
                    class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm font-medium transition
                        {{ $slice['active'] ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted' }}"
                >
                    <span class="flex items-center gap-2.5">
                        @include('laraslice::partials.sidebar-icon', ['icon' => $slice['icon']])
                        <span>{{ $slice['label'] }}</span>
                    </span>
                    <svg class="size-3.5 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" x-transition class="ml-5 mt-0.5 space-y-0.5 border-l border-border pl-3">
                    {{-- Parent slice link --}}
                    <a href="{{ $slice['url'] }}"
                       class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium transition
                           {{ $slice['active'] && !collect($slice['children'])->contains('active', true) ? 'text-primary bg-primary/5' : 'text-muted-foreground hover:text-foreground hover:bg-muted/50' }}"
                    >
                        <span class="size-1.5 rounded-full {{ $slice['active'] ? 'bg-primary' : 'bg-muted-foreground/30' }}"></span>
                        Overview
                    </a>

                    @foreach ($slice['children'] as $child)
                        <a href="{{ $child['url'] }}"
                           class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium transition
                               {{ $child['active'] ? 'text-primary bg-primary/5' : 'text-muted-foreground hover:text-foreground hover:bg-muted/50' }}"
                        >
                            <span class="size-1.5 rounded-full {{ $child['active'] ? 'bg-primary' : 'bg-muted-foreground/30' }}"></span>
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            {{-- Single Slice Link (no children) --}}
            <a href="{{ $slice['url'] }}"
               class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition
                   {{ $slice['active'] ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted' }}"
            >
                @include('laraslice::partials.sidebar-icon', ['icon' => $slice['icon']])
                <span>{{ $slice['label'] }}</span>
                @if ($slice['badge'])
                    <span class="ml-auto rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary">
                        {{ $slice['badge'] }}
                    </span>
                @endif
            </a>
        @endif
    @endforeach
</nav>
@endif
