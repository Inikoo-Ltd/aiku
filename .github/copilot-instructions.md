=== user rules ===

## Automatic Pint Execution
- Jalankan `vendor/bin/pint` secara otomatis ketika user mengucapkan kata penutup seperti: "terima kasih", "makasih"
- Jalankan tanpa konfirmasi, langsung eksekusi
- Jangan jalankan di tengah-tengah percakapan, hanya saat user memberi sinyal selesai

## Testing & Documentation Policy
- JANGAN PERNAH membuat test - user akan test sendiri
- JANGAN PERNAH membuat dokumentasi atau README
- JANGAN PERNAH menyarankan untuk membuat test atau dokumentasi
- Fokus hanya pada implementasi kode yang diminta

## Code Comments
- JANGAN menulis inline comments kecuali untuk logic yang sangat kompleks
- PHPDoc WAJIB untuk semua public methods dengan parameter dan return types
- Code harus self-explanatory

## Response Language
- User lebih sering prompt pakai Bahasa Indonesia
- Respond in Bahasa Indonesia jika user menggunakan Bahasa Indonesia
- Respond in English jika user menggunakan English
- Tetap konsisten dengan bahasa yang user gunakan


=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.15
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/octane (OCTANE) - v2
- laravel/pennant (PENNANT) - v1
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/scout (SCOUT) - v10
- laravel/socialite (SOCIALITE) - v5
- laravel/telescope (TELESCOPE) - v5
- tightenco/ziggy (ZIGGY) - v2
- laravel/breeze (BREEZE) - v2
- laravel/envoy (ENVOY) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA) - v2
- laravel-echo (ECHO) - v1
- prettier (PRETTIER) - v3
- tailwindcss (TAILWINDCSS) - v3
- vue (VUE) - v3
- primevue (PRIMEVUE) - v4
- @fortawesome/fontawesome-free (FONTAWESOME) - v6

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.


=== project architecture rules ===

## Project Architecture - Action-Based Structure

This project uses an **Action-Based Architecture**, NOT traditional Laravel Controller structure.

### CRITICAL: What User Will Request

User will NEVER ask to create:
- ❌ Controllers
- ❌ Services  
- ❌ Repositories

User will ALWAYS ask to create:
- ✅ Actions
- ✅ Hydrators
- ✅ UI (Index/Show/Create/Edit/Delete)
- ✅ Routes
- ✅ Models
- ✅ Migrations
- ✅ Resources (DTOs)

### Directory Structure
```
App/
├── Actions/
│   └── {Scope}/
│       └── {Module}/
│           ├── Hydrators/          # Background/async logic (dispatch)
│           │   └── {Module}Hydrate{Purpose}.php
│           ├── UI/                 # Display logic
│           │   ├── Index{Module}.php    # List/table data
│           │   ├── Show{Module}.php     # Single detail view
│           │   ├── Create{Module}.php   # Create form
│           │   ├── Edit{Module}.php     # Edit form
│           │   └── Delete{Module}.php   # Delete confirmation
│           ├── Store{Module}.php   # Main store logic
│           ├── Update{Module}.php  # Main update logic
│           └── Delete{Module}.php  # Main delete logic
│
└── Http/
    └── Resources/
        └── {Scope}/
            └── {Module}Resource.php  # DTO/Normalization
```

### Naming Conventions

#### Actions (Root Level - Main Logic)
- `Store{Module}.php` - Create new record
- `Update{Module}.php` - Update existing record
- `Delete{Module}.php` - Delete record

#### Hydrators (Background/Async)
- `{Module}Hydrate{Purpose}.php` - Background processing
- Run via dispatch for smooth async operation

#### UI Actions (Display/Forms)
- `Index{Module}.php` - Display list/table (many records)
- `Show{Module}.php` - Display single record detail
- `Create{Module}.php` or `Add{Module}.php` - Show create form
- `Edit{Module}.php` - Show edit form
- `Delete{Module}.php` - Show delete confirmation

#### Resources (DTOs)
- Always ends with `Resource.php`
- Used for data normalization before sending to frontend
- Purpose: Transform raw model/array data into normalized structure

### File Reading Strategy for Actions

When user requests to create/modify an Action, ALWAYS:

1. **Check existing similar actions first:**
```bash
# User: "Buat action untuk store product"
view App/Actions  # See available scopes
view App/Actions/Catalogue  # If Catalogue scope exists
view App/Actions/Catalogue/Product  # Check existing Product actions
```

2. **Read the most similar action as template:**
```bash
# If creating StoreProduct, read existing Store action
view App/Actions/Catalogue/Asset/StoreAsset.php
# Read its traits, parent class, dependencies
```

3. **Check for Hydrators if background processing needed:**
```bash
view App/Actions/Catalogue/Asset/Hydrators
view App/Actions/Catalogue/Asset/Hydrators/AssetHydrateTransactions.php
```

4. **Check Resources for DTO pattern:**
```bash
view App/Http/Resources/Catalogue/CollectionResource.php
```


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Temporary Scripts & Actions
- BOLEH membuat temporary PHP scripts atau artisan commands untuk debugging/testing
- WAJIB ditandai dengan comment: `// TEMPORARY - DELETE AFTER USE`
- WAJIB dihapus setelah task selesai
- Inform user bahwa script sudah dibuat dan harus dihapus setelah digunakan

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Documentation Search - MODIFIED
- JANGAN gunakan `search-docs` tool
- Lebih prioritaskan searching file dalam project menggunakan `view` tool
- Cari pattern dari file yang sudah ada (migrations, models, actions, resources, dll)
- Gunakan keyword searching untuk menemukan implementasi yang mirip


=== deep file analysis rules ===

## Deep File Analysis - CRITICALLY IMPORTANT

Sebelum memodifikasi atau membuat code, WAJIB membaca dan menganalisis:

### 1. Full Dependency Chain
Ketika membaca sebuah file, SELALU baca juga:
- **Traits** yang di-use
- **Interfaces** yang di-implement  
- **Parent classes** yang di-extends
- **Classes yang dipanggil** seperti:
  - `Model::run()` → baca file Model dan method run()
  - `Job::dispatch()` → baca file Job
  - `Action::run()` → baca file Action
  - `Hydrator::dispatch()` → baca file Hydrator
  - `Resource::make()` → baca file Resource
  - Dan semua static/dynamic method calls lainnya

### 2. Action-Specific Workflow
```
User: "Buat action untuk store product"

LANGKAH WAJIB:
1. view App/Actions → lihat struktur scope yang ada
2. view App/Actions/Catalogue → jika scope Catalogue ada
3. view App/Actions/Catalogue/Product → cek action yang sudah ada
4. Pilih action serupa sebagai reference (misal: StoreAsset.php)
5. Baca action tersebut LENGKAP:
   - Traits yang digunakan → BACA file trait-nya
   - Parent class → BACA parent class
   - Method yang dipanggil → BACA class/method tersebut
   - Hydrator yang di-dispatch → BACA file hydrator
   - Resource yang digunakan → BACA file resource
   - Form Request → BACA file validation
6. Baca Resource terkait:
   - view App/Http/Resources/Catalogue/Product/ProductResource.php
7. Baru buat action baru mengikuti pattern yang sama
```

### 3. Critical Rules
- JANGAN assume isi sebuah trait/class tanpa membacanya
- JANGAN skip membaca parent class
- JANGAN skip membaca method yang dipanggil dari class lain
- JANGAN skip membaca Hydrator yang di-dispatch
- JANGAN skip membaca Resource yang digunakan
- SELALU trace full flow dari request sampai response

### 4. Verification Checklist Before Implementation
Sebelum menulis code baru:
1. ✅ Sudah baca action-action serupa di scope/module yang sama?
2. ✅ Sudah baca semua traits yang digunakan?
3. ✅ Sudah baca parent class jika ada?
4. ✅ Sudah baca class/method yang dipanggil (Action, Hydrator, Resource)?
5. ✅ Sudah baca Vue component jika ada Inertia::render()?
6. ✅ Sudah baca Form Request untuk validation rules?
7. ✅ Sudah pahami full flow dari UI → Action → Hydrator → Response?

Jika ada yang belum → BACA DULU sebelum implement.


=== inertia analysis rules ===

## Inertia Frontend Analysis - CRITICALLY IMPORTANT

### Ketika menemukan Inertia::render() di Action, WAJIB:
```php
// Contoh di UI Action:
return Inertia::render(
    'Org/Catalogue/Shop',  // ← BACA FILE INI
    [
        // ...
    ]
);
```

**LANGKAH WAJIB:**
1. Konversi path ke file Vue: `Org/Catalogue/Shop` → `resources/js/Pages/Grp/Org/Catalogue/Shop.vue`
2. BACA file Vue tersebut menggunakan `view` tool
3. Analisis:
   - Props yang diterima (products, title, filters)
   - PrimeVue components yang digunakan (DataTable, Button, Dialog, dll)
   - Layout yang digunakan
   - Methods/composables yang dipanggil
   - Resource structure yang diharapkan
   - FontAwesome icons yang digunakan
4. Jika ada component lain yang dipanggil → BACA component tersebut juga
5. Verify bahwa Resource yang dikirim match dengan yang Vue component butuhkan

### Example Analysis Flow
```
Action: ShowShop → Inertia::render('Org/Catalogue/Shop', [...])
↓
WAJIB BACA: resources/js/Pages/Grp/Org/Catalogue/Shop.vue
↓
Pahami FULL FLOW: Action → Resource → Vue Component → PrimeVue Components
```

### Critical Rules for Inertia
- JANGAN modify UI Action tanpa membaca Vue component-nya
- JANGAN modify Resource tanpa tahu Vue component butuh field apa
- JANGAN assume struktur props tanpa membaca Vue file
- JANGAN skip membaca nested components
- SELALU verify props yang dikirim (dari Resource) match dengan yang diterima di Vue
- SELALU trace Resource → Vue Props untuk memastikan consistency
- SELALU perhatikan PrimeVue component props requirements
- SELALU ikuti pattern FontAwesome icon usage yang sudah ada


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## PHPDoc Blocks
- WAJIB untuk semua models

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files when appropriate.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input.

### Database
- Always use proper Eloquent relationship methods with return type hints.
- Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories if needed.
- JANGAN buat seeders atau tests - user akan handle sendiri.

### APIs & Resources
- This project uses Eloquent API Resources for DTO/normalization.
- Resources always end with `Resource.php` suffix.
- Place in `App/Http/Resources/{Scope}/{Module}/`.

### Form Requests & Validation
- Always create Form Request classes for validation rather than inline validation.
- Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues & Background Processing
- Use Hydrators (in `Actions/{Scope}/{Module}/Hydrators/`) for background processing.
- Hydrators should implement `ShouldQueue` interface.
- Dispatch Hydrators from main Actions for async operations.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files.
- Never use the `env()` function directly outside of config files.
- Always use `config('app.name')`, not `env('APP_NAME')`.


=== laravel/v12 rules ===

## Laravel 12

- This project uses Laravel 12 but keeps **Laravel 10 structure**.
- This is **perfectly fine** and recommended by Laravel.
- Follow the existing structure - do NOT migrate to new Laravel 12 structure unless explicitly requested.

### Laravel 10 Structure
- Middleware typically lives in `app/Http/Middleware/`.
- Service providers in `app/Providers/`.
- There is no `bootstrap/app.php` application configuration in Laravel 10 structure:
    - Middleware registration happens in `app/Http/Kernel.php`
    - Exception handling is in `app/Exceptions/Handler.php`
    - Console commands and schedule register in `app/Console/Kernel.php`

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively: `$query->latest()->limit(10);`.

### Models
- Casts can be set in a `casts()` method on a model rather than the `$casts` property.
- Follow existing conventions from other models.


=== inertia-laravel/core rules ===

## Inertia Core

- Inertia.js components should be placed in the `resources/js/Pages` directory.
- Use `Inertia::render()` in UI Actions to render views.
- Always read the Vue component when you see `Inertia::render()`.


=== inertia-laravel/v2 rules ===

## Inertia v2

- Make use of all Inertia features from v1 & v2.
- Check existing implementations before making changes.

### Inertia v2 New Features
- Polling
- Prefetching
- Deferred props
- Infinite scrolling using merging props and `WhenVisible`
- Lazy loading data on scroll

### Deferred Props & Empty States
- When using deferred props on the frontend, add skeleton/loading states.
- PrimeVue provides Skeleton component for this purpose.


=== inertia-vue/core rules ===

## Inertia + Vue

- Vue components must have a single root element.
- Use `router.visit()` or `<Link>` for navigation instead of traditional links.


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML.
- Check and use existing Tailwind conventions within the project.
- Offer to extract repeated patterns into components that match the project's conventions.
- PrimeVue components have their own styling system - use Tailwind for custom elements only.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v3 rules ===

## Tailwind 3

- Always use Tailwind CSS v3 - verify you're using only classes supported by this version.

## Remember
- SELALU baca file existing sebelum membuat yang baru
- SELALU trace dependencies (traits, parent class, called methods)
- SELALU baca Vue component jika ada Inertia::render()
- SELALU verify Resource match dengan Vue component needs
- SELALU check PrimeVue component usage dari existing files
- SELALU follow FontAwesome icon patterns yang sudah ada
- JANGAN assume PrimeVue component API - baca existing files first
- JANGAN skip any step - setiap step penting untuk consistency
