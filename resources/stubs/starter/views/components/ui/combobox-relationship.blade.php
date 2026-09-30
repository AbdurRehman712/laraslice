@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => 'Select an option...',
    'quickAddUrl' => null,
    'quickAddTitle' => null,
    'required' => false,
    'class' => '',
])

@php
    $resolvedTitle = $quickAddTitle ?? ($label ? rtrim($label, ' *') : 'Record');
    $cleanTitle = trim(str_replace('+', '', $resolvedTitle));
    $currentVal = (string) old($name, $selected ?? '');
    $optionsArray = is_array($options) ? $options : (is_iterable($options) ? iterator_to_array($options) : []);
@endphp

<script>
if (!window.LaraSliceDrawer) {
    window.LaraSliceDrawer = {
        slugify: function(text) {
            return (text || '').toString().toLowerCase().trim()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\-+/g, '-');
        },
        async submit(url, payload, selectId, token, onSuccess, onError) {
            try {
                if (!payload.name || !payload.name.trim()) {
                    onError('Name is required.');
                    return;
                }
                if (!payload.slug && payload.name) {
                    payload.slug = this.slugify(payload.name);
                }
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (!response.ok) {
                    let msg = data.message;
                    if (!msg && data.errors) {
                        const firstKey = Object.keys(data.errors)[0];
                        msg = data.errors[firstKey][0];
                    }
                    onError(msg || 'Validation failed.');
                    return;
                }
                const newId = String(data.id || (data.item ? data.item.id : ''));
                const newLabel = data.label || data.name || data.title || (data.item ? (data.item.name || data.item.title) : ('#' + newId));
                
                const sel = document.getElementById(selectId);
                if (sel) {
                    const opt = new Option(newLabel, newId, true, true);
                    sel.add(opt);
                    sel.value = newId;
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                }
                if (onSuccess) onSuccess(newLabel, newId);
            } catch (err) {
                onError('Network error: ' + (err.message || 'Could not save record.'));
            }
        }
    };
}
</script>

<div x-data="{
    drawerOpen: false,
    isSubmitting: false,
    errorMessage: '',
    successToast: '',
    name: '',
    slug: '',
    phone: '',
    email: '',
    website: '',
    description: '',

    openDrawer() {
        this.name = '';
        this.slug = '';
        this.phone = '';
        this.email = '';
        this.website = '';
        this.description = '';
        this.errorMessage = '';
        this.drawerOpen = true;
    },

    closeDrawer() {
        this.drawerOpen = false;
        this.errorMessage = '';
    },

    submitDrawer() {
        const self = this;
        self.isSubmitting = true;
        self.errorMessage = '';
        
        const payload = {
            name: self.name.trim(),
            title: self.name.trim(),
        };
        if (self.slug && self.slug.trim()) payload.slug = self.slug.trim();
        if (self.phone && self.phone.trim()) payload.phone = self.phone.trim();
        if (self.email && self.email.trim()) payload.email = self.email.trim();
        if (self.website && self.website.trim()) payload.website = self.website.trim();
        if (self.description && self.description.trim()) payload.description = self.description.trim();

        window.LaraSliceDrawer.submit(
            '{{ $quickAddUrl }}',
            payload,
            '{{ $name }}',
            '{{ csrf_token() }}',
            function(label, id) {
                self.isSubmitting = false;
                self.drawerOpen = false;
                self.name = '';
                self.slug = '';
                self.phone = '';
                self.email = '';
                self.website = '';
                self.description = '';
                self.successToast = label + ' created and selected!';
                setTimeout(function() { self.successToast = ''; }, 4000);
            },
            function(err) {
                self.isSubmitting = false;
                self.errorMessage = err;
            }
        );
    }
}" class="space-y-1.5 relative {{ $class }}">

    <!-- Header with Label and Quick Add Trigger -->
    <div class="flex items-center justify-between">
        @if ($label)
            <label for="{{ $name }}" class="flex items-center gap-2 text-sm leading-none font-medium select-none">
                <span>{{ $label }}</span>
                @if ($required)
                    <span class="text-destructive">*</span>
                @endif
            </label>
        @else
            <div></div>
        @endif

        @if ($quickAddUrl)
            <button type="button"
                    @click="openDrawer()"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:text-primary/80 transition-colors cursor-pointer group">
                <svg class="w-3.5 h-3.5 transition-transform duration-150 group-hover:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                <span>New {{ $cleanTitle }}</span>
            </button>
        @endif
    </div>

    <!-- Clean Native Select Input -->
    <select id="{{ $name }}"
            name="{{ $name }}"
            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            {{ $required ? 'required' : '' }}>
        <option value="">{{ $placeholder }}</option>
        @foreach ($optionsArray as $optId => $optLabel)
            <option value="{{ $optId }}" @selected((string)$optId === (string)$currentVal)>{{ $optLabel }}</option>
        @endforeach
    </select>

    <!-- Slide-Over Drawer for Instant Quick-Add -->
    @if ($quickAddUrl)
        <div x-show="drawerOpen"
             x-cloak
             @keydown.window.escape="closeDrawer()"
             class="fixed inset-0 z-50 overflow-hidden"
             style="display: none;">
            
            <!-- Backdrop -->
            <div x-show="drawerOpen"
                 x-transition:enter="ease-in-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in-out duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="closeDrawer()"
                 class="fixed inset-0 bg-neutral-950/60 backdrop-blur-xs transition-opacity"></div>

            <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <div x-show="drawerOpen"
                     x-transition:enter="transform transition ease-in-out duration-300"
                     x-transition:enter-start="translate-x-full"
                     x-transition:enter-end="translate-x-0"
                     x-transition:leave="transform transition ease-in-out duration-300"
                     x-transition:leave-start="translate-x-0"
                     x-transition:leave-end="translate-x-full"
                     class="w-screen max-w-md bg-card border-l border-border shadow-2xl flex flex-col justify-between"
                     style="display: none;">
                    
                    <!-- Drawer Header -->
                    <div class="px-6 py-5 border-b border-border/80 bg-muted/30 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-foreground flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-primary inline-block"></span>
                                <span>Create New {{ $cleanTitle }}</span>
                            </h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Create and automatically select in dropdown.</p>
                        </div>
                        <button type="button"
                                @click="closeDrawer()"
                                class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 transition cursor-pointer">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Drawer Form Body -->
                    <div class="px-6 py-5 overflow-y-auto space-y-4 flex-1">
                        <!-- Validation error display -->
                        <div x-show="errorMessage"
                             x-transition
                             class="p-3 rounded-lg border border-destructive/30 bg-destructive/10 text-destructive text-xs font-medium flex items-start gap-2"
                             style="display: none;">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span x-text="errorMessage"></span>
                        </div>

                        <!-- Name / Title Field -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground flex items-center gap-1">
                                <span>{{ $cleanTitle }} Name / Title</span>
                                <span class="text-destructive">*</span>
                            </label>
                            <input type="text"
                                   x-model="name"
                                   @input="slug = window.LaraSliceDrawer.slugify(name)"
                                   placeholder="e.g. Acme Corporation..."
                                   autofocus
                                   required
                                   class="w-full px-3 py-2 text-xs bg-background border border-input rounded-lg shadow-2xs focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                        </div>

                        <!-- Optional Contact / Entity Details -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-foreground">
                                    Direct Phone
                                </label>
                                <input type="text"
                                       x-model="phone"
                                       placeholder="+1 (555) 000-0000"
                                       class="w-full px-3 py-2 text-xs bg-background border border-input rounded-lg shadow-2xs focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-semibold text-foreground">
                                    Email Address
                                </label>
                                <input type="email"
                                       x-model="email"
                                       placeholder="contact@example.com"
                                       class="w-full px-3 py-2 text-xs bg-background border border-input rounded-lg shadow-2xs focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">
                                Website URL
                            </label>
                            <input type="text"
                                   x-model="website"
                                   placeholder="https://example.com"
                                   class="w-full px-3 py-2 text-xs bg-background border border-input rounded-lg shadow-2xs focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary">
                        </div>

                        <!-- Description Field -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">
                                Description / Notes
                            </label>
                            <textarea x-model="description"
                                      rows="3"
                                      placeholder="Optional notes, industry, or details..."
                                      class="w-full px-3 py-2 text-xs bg-background border border-input rounded-lg shadow-2xs focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary resize-none"></textarea>
                        </div>
                    </div>

                    <!-- Drawer Footer -->
                    <div class="px-6 py-4 border-t border-border/60 bg-muted/20 flex items-center justify-end gap-3">
                        <button type="button"
                                @click="closeDrawer()"
                                class="px-4 py-2 text-xs font-medium rounded-lg border border-input bg-background hover:bg-muted text-foreground transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="button"
                                @click="submitDrawer()"
                                :disabled="isSubmitting"
                                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 transition shadow-sm cursor-pointer disabled:opacity-50">
                            <svg x-show="isSubmitting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24" style="display: none;">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span x-text="isSubmitting ? 'Creating...' : 'Create & Select'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Toast Notification Popup -->
    <div x-show="successToast"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         style="display: none;"
         class="fixed bottom-6 right-6 z-50 p-4 rounded-xl shadow-2xl border border-success/30 bg-card text-foreground flex items-center gap-3">
        <div class="p-1 rounded-full bg-success/15 text-success">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <div>
            <p class="text-xs font-semibold text-foreground">Record Created</p>
            <p class="text-xs text-muted-foreground" x-text="successToast"></p>
        </div>
    </div>
</div>