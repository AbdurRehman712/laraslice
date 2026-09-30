@extends('layouts.app')

@section('content')
<div class="w-full max-w-5xl mx-auto space-y-6">
    <div class="flex items-center gap-2 text-xs text-muted-foreground">
        <a href="{{ route('users.index') }}" class="hover:text-primary transition-colors">Users</a>
        <span>/</span>
        <span class="text-foreground font-medium">{{ $isNew ? 'Create User' : 'Edit User #' . $form->id }}</span>
    </div>

    @if (session('error'))
        <div class="p-4 rounded-xl border border-destructive/30 bg-destructive/10 text-destructive text-sm font-medium flex items-center gap-2 shadow-xs">
            <x-lucide-alert-circle class="size-4 shrink-0" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <x-ui.card variant="sectioned" class="shadow-sm bg-card border border-border">
        <x-ui.card-header class="border-b pb-4 px-6 pt-6">
            <x-ui.card-title class="text-lg font-bold text-foreground">{{ $isNew ? 'Create New User' : 'Edit User Profile' }}</x-ui.card-title>
            <x-ui.card-description>Configure user credentials, security status, and assigned roles</x-ui.card-description>
        </x-ui.card-header>

        <x-ui.card-content class="p-6">
            <form action="{{ $isNew ? route('users.store') : route('users.update', $form->id) }}" method="POST" class="space-y-6">
                @csrf
                @if(!$isNew) @method('PUT') @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <x-ui.label for="name">Full Name *</x-ui.label>
                        <x-ui.input id="name" name="name" value="{{ old('name', $form->name) }}" required placeholder="e.g. John Doe" />
                        @error('name')
                            <p class="text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <x-ui.label for="email">Email Address *</x-ui.label>
                        <x-ui.input id="email" name="email" type="email" value="{{ old('email', $form->email) }}" required placeholder="john@example.com" />
                        @error('email')
                            <p class="text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-1.5">
                        <x-ui.label for="password">{{ $isNew ? 'Password *' : 'Password (Leave blank to keep unchanged)' }}</x-ui.label>
                        <x-ui.input id="password" name="password" type="password" {{ $isNew ? 'required' : '' }} placeholder="••••••••" />
                        @error('password')
                            <p class="text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <x-ui.label for="status">Account Status</x-ui.label>
                        <select id="status" name="status" class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            <option value="active" {{ old('status', $form->status) === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="suspended" {{ old('status', $form->status) === 'suspended' ? 'selected' : '' }}>Suspended</option>
                            <option value="pending" {{ old('status', $form->status) === 'pending' ? 'selected' : '' }}>Pending</option>
                        </select>
                    </div>
                </div>

                <!-- Roles Checkboxes -->
                <div class="space-y-2 pt-2">
                    <x-ui.label>Assigned RBAC Roles</x-ui.label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-4 rounded-xl border border-border bg-muted/20">
                        @php
                            $availableRoles = \Illuminate\Support\Facades\DB::table('roles')->get();
                            $rawUserRoles = old('roles', $form->roles ?? ($form->roleIds ?? []));
                            $userRoleIds = is_array($rawUserRoles) ? array_map('intval', $rawUserRoles) : [];
                        @endphp
                        @forelse ($availableRoles as $r)
                            <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-muted/40 cursor-pointer transition-colors">
                                <input type="checkbox" name="roles[]" value="{{ $r->id }}" {{ in_array((int)$r->id, $userRoleIds, true) ? 'checked' : '' }} class="blat-checkbox">
                                <div>
                                    <span class="text-sm font-semibold text-foreground">{{ $r->name }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ $r->slug }}</span>
                                </div>
                            </label>
                        @empty
                            <p class="text-xs text-muted-foreground col-span-2">No roles configured. Standard user role will apply.</p>
                        @endforelse
                    </div>
                </div>

                
            <div class="space-y-1.5">
                <x-ui.label for="cnic">Cnic</x-ui.label>
                <x-ui.input id="cnic" type="text" name="cnic" value="{{ old('cnic', $form->cnic ?? '') }}" placeholder="Enter Cnic..." />
            </div>

            <div class="flex items-center justify-end gap-3 pt-5 border-t border-border/50">
                    <x-ui.button href="{{ route('users.index') }}" as="a" variant="outline">
                        Cancel
                    </x-ui.button>
                    <x-ui.button type="submit" name="action" value="save_continue" variant="secondary">
                        Save & Continue
                    </x-ui.button>
                    <x-ui.button type="submit" name="action" value="save_close">
                        {{ $isNew ? 'Create User' : 'Save Changes' }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card-content>
    </x-ui.card>
</div>
@endsection