@props([
    'tabs' => [],
    'defaultTab' => null,
    'class' => '',
])

@php
    $firstKey = !empty($tabs) ? array_key_first($tabs) : '';
    $initial = $defaultTab ?? $firstKey;
@endphp

<div x-data="{ activeTab: '{{ $initial }}' }" {{ $attributes->merge(['class' => 'w-full space-y-4 ' . $class]) }}>
    <!-- Tab Nav Header -->
    <div class="flex items-center gap-1 border-b border-border/60 pb-px overflow-x-auto no-scrollbar">
        @foreach ($tabs as $key => $tab)
            @php
                $tabId = is_numeric($key) ? ($tab['id'] ?? $tab['name']) : $key;
                $label = is_array($tab) ? ($tab['label'] ?? $tab['name']) : $tab;
                $icon = is_array($tab) ? ($tab['icon'] ?? null) : null;
                $badge = is_array($tab) ? ($tab['badge'] ?? null) : null;
            @endphp
            <button type="button"
                    @click="activeTab = '{{ $tabId }}'"
                    :class="activeTab === '{{ $tabId }}' 
                        ? 'border-primary text-primary font-semibold' 
                        : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border/80'"
                    class="group inline-flex items-center gap-2 px-4 py-2.5 text-xs border-b-2 font-medium transition-all cursor-pointer whitespace-nowrap -mb-px">
                @if ($icon)
                    <span class="text-sm opacity-80 group-hover:opacity-100">{!! $icon !!}</span>
                @endif
                <span>{{ $label }}</span>
                @if (!is_null($badge))
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary">
                        {{ $badge }}
                    </span>
                @endif
            </button>
        @endforeach
    </div>

    <!-- Tab Contents Slot -->
    <div class="tab-panels">
        {{ $slot }}
    </div>
</div>