---
name: laraslice
description: Architectural standards, CLI commands, Declarative Schemas, multi-table aggregate patterns, cross-slice communication, and AI workflows for LaraSlice applications integrated with Laravel Boost.
---

# LaraSlice Framework & AI Assistant Skill

This skill provides comprehensive instructions for AI agents and developers working on **LaraSlice** applications. It covers the Three-Layer Packaging Model (ADR-001), Vertical Slice Architecture, Pure BlatUI tokens, Declarative Schema Builder, multi-table aggregate patterns, cross-slice communication, and deep integration with **Laravel Boost**.

---

## 1. Architectural Principles & Packaging (ADR-001)

1. **Three-Layer Packaging Model**:
   - **Layer 1: Core Engine (`vendor/hereafter/laraslice`)**: Shared lean engine containing base classes, slice discovery & caching (`slice:cache`), audit engine, RBAC gate bridge, declarative schema, and CLI generators. Zero vendor lock-in, zero runtime network/phone-home calls.
   - **Layer 2: Starter Template (`laraslice-starter`)**: Application shell, BlatUI components, theme tokens, and default starter slices (`Auth`, `Users`, `Roles`, `Settings`, `AuditViewer`). Cloned once per project; owned by the team after clone.
   - **Layer 3: Project Slices (`app/Slices/{SliceName}/`)**: Client domain features (e.g. `Invoices`, `HR`, `Procurement`). Generated using the generation gap pattern: generated base classes are safe to regenerate, while hand-written subclasses, contracts, and business logic are never overwritten.

2. **Vertical Slice Architecture (VSA)**:
   - Slices are **Domain Bounded Contexts**, NOT database tables. A single slice can own multiple aggregate tables.
   - Each slice owns its own `Models/`, `Migrations/`, `Controllers/`, `Services/`, `Contracts/` (Business Objects / DTOs), `Routes/` (`web.php`, `api.php`), `Resources/views/`, and `Schemas/`.
   - Manifest: `slice.yaml` is the version-controlled single source of truth for metadata, capabilities, navigation, and relations.

3. **No Livewire / No Flux / No Metronic**:
   - The stack is strictly **Pure Blade + Alpine.js + Tailwind CSS v4**.
   - Atomic UI elements use **BlatUI** (`<x-ui.card>`, `<x-ui.table>`, `<x-ui.input>`, `<x-ui.badge>`, `<x-ui.button>`, `<x-ui.label>`).
   - The application layout uses the pure BlatUI Dashboard-01 responsive shell (`layouts.app` / `<x-layouts.app>`). Pure Lucide icons (`<x-lucide-*>` and dynamic Lucide components). Zero Metronic dependencies (`ktui.min.js`, Keenicons, and `KTDrawer` are strictly prohibited).

4. **Design Tokens & Theme Consistency**:
   - Always use semantic CSS variables mapped to Tailwind utilities: `bg-background`, `bg-card`, `border-border`, `text-foreground`, `text-muted-foreground`, `text-primary`.
   - Never hardcode dark hex colors. Theme switches dynamically via Alpine.js `$store.theme` and `data-theme` tokens.

---

## 2. Declarative Schema Builder (`LaraSlice\Schema`)

Every slice defines a declarative schema in `app/Slices/{SliceName}/Schemas/{SliceName}Schema.php` extending `LaraSlice\Schema\SliceSchema`.

### Defining Form Fields & Table Columns
```php
namespace App\Slices\Products\Schemas;

use LaraSlice\Schema\SliceSchema;
use LaraSlice\Schema\Field;
use LaraSlice\Schema\Column;
use LaraSlice\Schema\Relation;

class ProductSchema extends SliceSchema
{
    public static function fields(): array
    {
        return [
            Field::text('title')->label('Product Title')->required()->placeholder('e.g. MacBook Pro'),
            Field::decimal('price')->label('Price ($)')->prefix('$')->placeholder('0.00'),
            Field::text('sku')->label('SKU / Barcode')->placeholder('e.g. MBP-001'),
            Field::number('stock')->label('Inventory Stock')->default(0),
            Field::textarea('description')->label('Description'),
            Field::select('status', [
                'draft'    => 'Draft',
                'active'   => 'Active',
                'archived' => 'Archived',
            ])->label('Status')->default('draft'),
        ];
    }

    public static function columns(): array
    {
        return [
            Column::text('id')->label('ID'),
            Column::text('title')->label('Title')->searchable()->sortable(),
            Column::badge('status')->label('Status'),
            Column::money('price', '$')->label('Price'),
            Column::text('sku')->label('SKU'),
            Column::text('stock')->label('Stock'),
        ];
    }

    public static function relations(): array
    {
        return [
            Relation::hasMany('variants', ProductVariantSchema::class)
                ->label('Product Variants')
                ->foreignKey('product_id')
                ->icon('layers'),
        ];
    }
}
```

### Exporting for Mobile & AI
Call `ProductSchema::toJson()` or `toArray()` to produce structured schema metadata. This is consumed by:
- Flutter mobile code generators for forms and state classes (`php artisan slice:export:flutter {Slice}`).
- AI agents to understand the aggregate structure and auto-generate views/migrations.

---

## 3. Cross-Slice Communication Standards & Boundary Enforcement

When Slice A needs data from Slice B (e.g. `Invoices` needs `Users`):

1. **Strict Boundary Enforcement (Tested in CI)**:
   - Slices must **NEVER** directly import another slice's internal Eloquent models (`use App\Slices\Users\Models\User` is forbidden in `Invoices`).
   - This rule is automatically verified by automated architectural boundary tests (`tests/Architecture/SliceBoundaryTest.php`).

2. **Typed Contracts & Business Objects (DTOs)**:
   - Slices expose public readonly DTOs under `Contracts/` (e.g. `UserSummaryBusinessObject`).
   - Slices inject domain services (e.g., `UserSliceService`) rather than executing raw SQL or cross-slice model queries:
   ```php
   class InvoiceSliceService
   {
       public function __construct(
           private readonly UserSliceService $userService
       ) {}

       public function createInvoice(InvoiceFormBusinessObject $dto): Invoice
       {
           $customer = $this->userService->getPublicSummary($dto->customerId);
           ...
       }
   }
   ```

3. **Typed Domain Events (`afterCommit`)**:
   - Decoupled asynchronous side-effects use typed Laravel events dispatched after database transaction commits:
   ```php
   Event::dispatch(new InvoicePaidEvent($invoiceSummary));
   ```

4. **Manifest Dependencies**:
   - In `app/Slices/Invoices/slice.yaml`:
   ```yaml
   name: Invoices
   version: 1.0.0
   dependencies:
     - Users
     - Settings
   ```

---

## 4. Multi-Table Slices vs Domain Suites (Real-World Architecture)

### A. Domain Slices with Cross-Slice Relationships (e.g. CRM: Companies & Contacts)
In real-world enterprise applications (Salesforce, HubSpot, Stripe), entities like **Companies** and **Contacts** are **autonomous, first-class vertical slices** within a shared **Domain Group**:

```text
📁 app/Slices/Crm/
   ├── 📂 Companies/            <-- Independent Slice (/crm/companies)
   ├── 📂 Contacts/             <-- Independent Slice (/crm/contacts)
   └── 📂 Deals/                <-- Independent Slice (/crm/deals)
```

1. **Why Sibling Slices instead of Sub-Tables?**:
   - Contacts are people with their own lifecycles, emails, phone calls, and direct navigation.
   - Sales reps need global search across all contacts regardless of company.
   - **Single Canonical Edit URL**: Editing contact #1 is always `http://localhost:8000/crm/contacts/1/edit`.
   - **Anti-Pattern to Avoid**: Never generate duplicate conflicting route trees like `/crm/companies/1/contacts/1/edit` alongside `/crm/contacts/1/edit`. Duplicate routes cause developer confusion, broken breadcrumbs, and redirect ambiguity.

2. **Connecting Sibling Slices**:
   - In `Contacts` schema: `company_id` uses the `<x-ui.combobox-relationship>` with quick-add.
   - In `Companies` view: Render an "Associated Contacts" tab listing contacts where `company_id = company.id`, with an "+ Add Contact" button linking to `/crm/contacts/create?company_id={id}`.

### B. Composition Aggregates (e.g. `Order` -> `OrderItem` or `Invoice` -> `InvoiceItem`)
Use child sub-tables **only** when an entity has no independent business life without its parent:
- An `OrderItem` makes no sense outside an `Order`.
- It does **not** appear in the main navigation sidebar.
- It uses **Laravel Shallow Routing**:
  - `POST /orders/{id}/items` (Add line item to order)
  - `DELETE /order-items/{id}` (Delete line item by unique ID)

### C. Multi-Slice Domain Scaffolding via CLI & UI
Developers and AI agents can scaffold entire domain suites in one command:

```bash
# Scaffold an entire CRM suite under the CRM domain
php artisan slice:make Companies Contacts Deals --domain=CRM

# Scaffold an entire E-Commerce suite with Flutter clients
php artisan slice:make Products Orders Customers Categories --domain=E-Commerce --flutter
```

In the **Visual Studio** (`/laraslice/wizard`):
- Click **CRM Suite (3 Slices)**, **E-Commerce Suite (4 Slices)**, or **Billing Suite (3 Slices)**.
- Or enter comma-separated slice names: `Companies, Contacts, Deals` with Domain `CRM`.

---

## 5. Enterprise Transactional Integrity & Audit Logging

For mission-critical ERP, banking, and government applications, models and actions maintain regulatory compliance trails:

1. **Plug-and-Play Model Auditing (`AuditableSlice`)**:
   ```php
   namespace App\Slices\Products\Models;

   use Illuminate\Database\Eloquent\Model;
   use LaraSlice\Core\Audit\Traits\AuditableSlice;

   class Product extends Model
   {
       use AuditableSlice;

       // Sensitive attributes (passwords, tokens) are automatically redacted
       protected array $auditExclude = ['internal_cost'];
   }
   ```
   - Automatically records `created`, `updated`, and `deleted` actions in `laraslice_audit_logs`.
   - Captures actor email, ID, IP address, user-agent, and before/after state diffs.
   - Inspectable live via the Slice Studio Audit & Activity Viewer.

2. **Transactional Mutations (`TransactionalAction`)**:
   ```php
   namespace App\Slices\Payroll\Actions;

   use LaraSlice\Core\Actions\TransactionalAction;

   class ProcessSalaryAction extends TransactionalAction
   {
       protected string $slice = 'Payroll';

       protected function handle(SalaryDto $dto): SalaryRecord
       {
           // Automatically executed inside a DB::transaction
           return SalaryRecord::create($dto->toArray());
       }
   }
   ```

---

## 6. Layout Guidelines (`layouts.app`)

All administrative slice views extend `layouts.app` / `<x-layouts.app>` (Dashboard-01 BlatUI layout):
```blade
@extends('layouts.app')

@section('content')
<div class="max-w-[1600px] mx-auto space-y-6">
    <!-- Breadcrumbs -->
    <div class="flex items-center gap-2 text-xs text-muted-foreground">
        <a href="{{ route('dashboard') }}" class="hover:text-primary">Dashboard</a>
        <span>/</span>
        <span class="text-foreground font-semibold">Products</span>
    </div>

    <!-- UI Cards with BlatUI -->
    <x-ui.card class="bg-card border border-border p-6 rounded-2xl shadow-xs">
        <x-ui.table>
            ...
        </x-ui.table>
    </x-ui.card>
</div>
@endsection
```

- Layout shell uses fixed `lg:ps-[280px]` offset on desktop and Alpine.js slide-over drawer on mobile.
- Zero Metronic JavaScript. Modals and drawers are powered purely by Alpine.js.

---

## 7. Working with CLI Commands & Laravel Boost

LaraSlice provides standardized console commands in the `slice:*` namespace:
- `php artisan slice:list`: Display all registered slices, domains, versions, and active status.
- `php artisan slice:make <SliceName>`: Scaffold a new production-ready vertical slice (bounded context).
- `php artisan slice:publish <SliceName>`: Publish a packaged core slice into `app/Slices/{SliceName}` for local customization (use `--all` for all slices, `--force` to overwrite).
- `php artisan slice:sync <SliceName>`: Detect database schema drift and safely update `slice.yaml` without overwriting custom PHP.
- `php artisan slice:cache`: Cache discovered slice manifests for high-performance production boot.
- `php artisan slice:clear`: Clear the slice discovery cache.
- `php artisan slice:export:flutter <SliceName>`: Scaffold native Flutter models, client services, and CRUD UI views.

---

## 8. Declarative Blueprint Studio & Visual Designer

Every vertical slice maintains a version-controlled `app/Slices/{SliceName}/slice.yaml` single source of truth.
- **Blueprint Studio UI**: Available at `/laraslice/wizard/blueprint`.
- **Modes**:
  - `🎨 Visual`: Interactive entity modeler with Statamic field widths (`33%`, `50%`, `100%`).
  - `👁️ Preview`: Real-time interactive BlatUI component and data table mockup.
  - `⚡ Split`: Side-by-side bidirectional visual and YAML live editing.
  - `📝 YAML`: Syntax-highlighted declarative source.
  - `✨ Add from DB`: Database table introspection.

---

## 9. Core Customization & Upstream Updates Strategy

### How to Customize a Core Slice in Your Project
LaraSlice's discovery system is explicitly designed so that **any slice in `app/Slices/` overrides the core slice of the same name**.
To customize a core slice:
```bash
php artisan slice:publish Users
```
*(or `php artisan slice:publish --all`)*

This copies the core slice from the framework into `app/Slices/Users`. Once published, it becomes first-class code in your repository: you can edit models, controllers, DTO contracts, and views, and commit them to git. Files in `vendor/` are untouched, and Composer updates will **never** overwrite your custom code.

### How Developers Contribute to Core Slices

#### 1. Open Source Contributions to Framework Core
1. **Fork the Repository**: [github.com/AbdurRehman712/laraslice](https://github.com/AbdurRehman712/laraslice)
2. **Edit the Core Slice**: Make enhancements directly in `src/Slices/{SliceName}` (e.g., `src/Slices/Users`, `src/Slices/Roles`, `src/Slices/Settings`).
3. **Run the Test Suite**:
   ```bash
   ./vendor/bin/phpunit tests
   ```
4. **Open a Pull Request**: Submit your PR on GitHub against the `master` branch.

#### 2. Local Development / Package Development
To test framework edits in real time in a host Laravel test project:
In the test project's `composer.json`, add a local path repository:
```json
"repositories": [
    {
        "type": "path",
        "url": "../LaraSlice",
        "options": { "symlink": true }
    }
]
```
Then run `composer update hereafter/laraslice`. This creates a symlink/junction, allowing developers to test their framework edits in real time before pushing to GitHub.