# Fitness Studio — New Module Addition Guide

## Project Context

Laravel fitness studio app with **multi-tenant module system**. Each gym can enable/disable modules. Super admin controls which modules are on/off per gym.

## How Module System Works

- Modules defined in `config/modules.php`
- Gym model stores enabled modules as JSON array in `gyms.modules` column
- `CheckModuleAccess` middleware blocks routes if module disabled
- Sidebar in `resources/views/layouts/app.blade.php` uses `$canSee('module_key')` to show/hide nav links
- Admin toggles modules at `/gyms/{gym}/modules` — UI auto-reads from config

## Adding a New Module — 4 Steps

### Step 1: `config/modules.php`
Add to `available` array:
```php
'MODULE_KEY' => [
    'label'       => 'Module Label',
    'description' => 'Short description.',
    'icon'        => '<svg>...</svg>',
],
```

### Step 2: `routes/web.php`
Wrap routes with module middleware:
```php
Route::middleware('module:MODULE_KEY')->group(function () {
    Route::get('/your-path', [YourController::class, 'index'])->name('your.index');
});
```

### Step 3: `resources/views/layouts/app.blade.php`
Add sidebar link inside the `@if(($isAdmin || $isOwner) && $hasGymContext)` block:
```blade
@if($canSee('MODULE_KEY'))
<a href="{{ route('your.index') }}" class="nav-item">Module Label</a>
@endif
```

### Step 4: Create Controller + Views
Normal Laravel controller and blade views. No special module code needed inside them.

## What NOT to Do

- No new DB migration needed — `modules` JSON column already exists
- No new middleware needed — `CheckModuleAccess` already handles everything
- No changes to admin UI — adding to config auto-shows toggle in gym modules page

## Key Files Reference

| Purpose | File |
|---|---|
| Module definitions | `config/modules.php` |
| Enable/disable logic | `app/Models/Gym.php` → `hasModule()` |
| Route protection | `app/Http/Middleware/CheckModuleAccess.php` |
| Sidebar visibility | `resources/views/layouts/app.blade.php` |
| Admin toggle UI | `resources/views/gyms/modules.blade.php` |
| Update controller | `app/Http/Controllers/Web/GymWebController.php` |
