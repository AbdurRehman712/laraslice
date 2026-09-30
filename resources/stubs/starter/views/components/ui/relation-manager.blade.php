@props([
    'title',
    'description' => 'Associated records',
    'createUrl' => null,
    'createButtonText' => 'Create New',
    'columns' => [], // [['key' => 'id', 'label' => 'ID'], ...]
    'records' => [],
    'emptyMessage' => 'No related records found.',
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'space-y-4 pt-6 border-t border-border/60 ' . $class]) }}>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-base font-bold tracking-tight text-foreground flex items-center gap-2">
                <span>{{ $title }}</span>
                <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-primary/10 text-primary">
                    {{ is_countable($records) ? count($records) : 0 }}
                </span>
            </h3>
            <p class="text-xs text-muted-foreground mt-0.5">{{ $description }}</p>
        </div>
        @if ($createUrl)
            <div>
                <a href="{{ $createUrl }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-primary-foreground text-xs font-semibold rounded-lg shadow-xs hover:bg-primary/90 transition cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>{{ $createButtonText }}</span>
                </a>
            </div>
        @endif
    </div>

    <!-- Sub-Table Card -->
    <div class="border border-border/60 rounded-xl overflow-hidden shadow-xs bg-card">
        <table class="w-full text-left border-collapse">
            <thead class="bg-muted/40 border-b border-border/60">
                <tr>
                    @foreach ($columns as $col)
                        <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            {{ $col['label'] ?? ucfirst($col['key']) }}
                        </th>
                    @endforeach
                    <th class="px-4 py-2.5 text-xs font-semibold text-muted-foreground uppercase tracking-wider text-right">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border/40">
                @forelse ($records as $record)
                    <tr class="hover:bg-muted/20 transition">
                        @foreach ($columns as $col)
                            @php
                                $key = $col['key'];
                                $val = is_object($record) ? ($record->{$key} ?? '') : ($record[$key] ?? '');
                            @endphp
                            <td class="px-4 py-3 text-xs text-foreground font-medium">
                                {{ $val }}
                            </td>
                        @endforeach
                        <td class="px-4 py-3 text-xs text-right whitespace-nowrap">
                            @if (isset($record->id) || isset($record['id']))
                                @php $recId = $record->id ?? $record['id']; @endphp
                                <a href="?edit_child={{ $recId }}" class="text-primary hover:underline font-semibold mr-3">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" class="px-4 py-8 text-center text-xs text-muted-foreground">
                            {{ $emptyMessage }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>