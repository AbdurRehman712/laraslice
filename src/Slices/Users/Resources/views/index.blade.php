@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header with Breadcrumbs & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-border/40">
        <div>
            <div class="flex items-center gap-2 text-xs text-muted-foreground mb-1">
                <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}" class="hover:text-primary transition-colors">Dashboard</a>
                <span>/</span>
                <span class="text-foreground font-medium">User Management</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-3">
                User Management
                <x-ui.badge variant="secondary" class="font-mono text-xs">v1.0.0</x-ui.badge>
            </h1>
            <p class="text-sm text-muted-foreground mt-0.5">Manage enterprise accounts, roles, access status, and avatars</p>
        </div>
        <div class="flex items-center gap-3">
            <x-ui.button href="{{ route('roles.index') }}" as="a" variant="outline" class="gap-1.5 shadow-xs">
                <x-lucide-shield class="size-4" />
                <span>Manage Roles</span>
            </x-ui.button>
            <x-ui.button href="{{ route('users.create') }}" as="a" class="gap-1.5 shadow-xs">
                <x-lucide-plus class="size-4" />
                <span>Add New User</span>
            </x-ui.button>
        </div>
    </div>

    @if (session('success'))
        <div class="p-4 rounded-xl border border-success/30 bg-success/10 text-success text-sm font-medium flex items-center gap-2 shadow-xs">
            <x-lucide-check-circle class="size-4 shrink-0" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Filter & Search Toolbar -->
    <x-ui.card class="bg-card border border-border shadow-xs">
        <x-ui.card-content class="p-4">
            <form method="GET" action="{{ route('users.index') }}" class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
                <div class="flex-1 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    <div class="relative flex-1">
                        <x-ui.input type="text" name="search" value="{{ $filter->search ?? '' }}" placeholder="Search by name or email..." class="w-full" />
                    </div>
                    <div class="w-full sm:w-48">
                        <select name="status" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            <option value="">All Statuses</option>
                            <option value="active" {{ ($filter->status ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="suspended" {{ ($filter->status ?? '') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                            <option value="pending" {{ ($filter->status ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.button type="submit" variant="secondary" size="sm">
                        <x-lucide-filter class="size-3.5 mr-1" />
                        <span>Filter</span>
                    </x-ui.button>
                    @if (!empty($filter->search) || !empty($filter->status))
                        <x-ui.button href="{{ route('users.index') }}" as="a" variant="ghost" size="sm">
                            Reset
                        </x-ui.button>
                    @endif
                </div>
            </form>
        </x-ui.card-content>
    </x-ui.card>

    <!-- Users Table Card -->
    <x-ui.card variant="sectioned" class="border shadow-xs bg-card">
        <x-ui.card-header class="border-b pb-4 px-6 pt-6">
            <div class="flex items-center justify-between">
                <div>
                    <x-ui.card-title class="text-base font-semibold">User Directory</x-ui.card-title>
                    <x-ui.card-description>All active enterprise identities and account states</x-ui.card-description>
                </div>
                <div class="text-xs text-muted-foreground font-medium">
                    Total: {{ count($pagedList->items) }} users
                </div>
            </div>
        </x-ui.card-header>

        <x-ui.card-content class="p-0">
            <x-ui.table>
                <x-ui.table-header class="bg-muted/40">
                    <x-ui.table-row>
                        <x-ui.table-head>User</x-ui.table-head>
                        <x-ui.table-head>Status</x-ui.table-head>
                        <x-ui.table-head>Assigned Roles</x-ui.table-head>
                        <x-ui.table-head>Created Date</x-ui.table-head>
                        <x-ui.table-head>CNIC</x-ui.table-head>
                        <x-ui.table-head class="text-right">Actions</x-ui.table-head>
                    </x-ui.table-row>
                </x-ui.table-header>
                <x-ui.table-body>
                    @forelse ($pagedList->items as $user)
                        <x-ui.table-row class="hover:bg-muted/30 transition">
                            <x-ui.table-cell>
                                <div class="flex items-center gap-3">
                                    <div class="size-9 rounded-full bg-primary/10 text-primary font-bold flex items-center justify-center text-xs shrink-0 border border-primary/20">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div class="flex flex-col">
                                        <a href="{{ route('users.edit', $user->id) }}" class="font-semibold text-foreground hover:text-primary transition">
                                            {{ $user->name }}
                                        </a>
                                        <span class="text-xs text-muted-foreground">{{ $user->email }}</span>
                                    </div>
                                </div>
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if ($user->status === 'active')
                                    <x-ui.badge variant="outline" class="text-emerald-600 border-emerald-500/30 bg-emerald-500/10">Active</x-ui.badge>
                                @elseif ($user->status === 'suspended')
                                    <x-ui.badge variant="outline" class="text-rose-600 border-rose-500/30 bg-rose-500/10">Suspended</x-ui.badge>
                                @else
                                    <x-ui.badge variant="outline" class="text-amber-600 border-amber-500/30 bg-amber-500/10">{{ ucfirst($user->status ?? 'pending') }}</x-ui.badge>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell>
                                @if (!empty($user->roles))
                                    @foreach ($user->roles as $roleName)
                                        <x-ui.badge variant="secondary" class="mr-1 text-[11px]">{{ $roleName }}</x-ui.badge>
                                    @endforeach
                                @else
                                    <span class="text-xs text-muted-foreground">Standard User</span>
                                @endif
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground font-mono">
                                {{ $user->createdAt ? date('M d, Y', strtotime($user->createdAt)) : '—' }}
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-xs text-muted-foreground font-mono">
                                {{ $user->cnic ?? '—' }}
                            </x-ui.table-cell>
                            <x-ui.table-cell class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <x-ui.button href="{{ route('users.edit', $user->id) }}" as="a" variant="ghost" size="sm" class="size-8 p-0">
                                        <x-lucide-pencil class="size-4 text-muted-foreground" />
                                    </x-ui.button>
                                    <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="inline" onsubmit="return confirm('Delete user account?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" variant="ghost" size="sm" class="size-8 p-0 hover:text-destructive">
                                            <x-lucide-trash-2 class="size-4" />
                                        </x-ui.button>
                                    </form>
                                </div>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @empty
                        <x-ui.table-row>
                            <x-ui.table-cell colspan="5" class="h-32 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <x-lucide-inbox class="size-8 text-muted-foreground/50" />
                                    <p class="text-sm font-medium">No users found matching your criteria</p>
                                    <x-ui.button href="{{ route('users.create') }}" as="a" variant="outline" size="sm">
                                        Create new user
                                    </x-ui.button>
                                </div>
                            </x-ui.table-cell>
                        </x-ui.table-row>
                    @endforelse
                </x-ui.table-body>
            </x-ui.table>
        </x-ui.card-content>
    </x-ui.card>
</div>
@endsection