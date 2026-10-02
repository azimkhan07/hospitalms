# AGENTS.md

Project: Hospital Management System (HMS) — Laravel 12 + Livewire 3 + MySQL.

## Environment

- PHP: `D:\xampp\php\php.exe` (PHP 8.2) — use the full path from PowerShell.
- App URL: `http://127.0.0.1:8100` (already running for dev).
- DB: MySQL `hms`. Demo accounts: password `123456` (see README, admin `azim@hms.com`).
- Do NOT run `php artisan livewire:publish` / restore `public/vendor/livewire` — stale assets break wire bindings. Livewire serves from `livewire.js?id=...`.

## Commands

- Lint a PHP file: `& "D:\xampp\php\php.exe" -l path\to\file.php`
- Seed (idempotent): `& "D:\xampp\php\php.exe" artisan db:seed --force`
- Clear views: `& "D:\xampp\php\php.exe" artisan view:clear`
- Browser audit: `node C:\Users\azimk\AppData\Local\Temp\opencode\pp\audit.js` (expects 31/31 pass, 0 console errors, hOver=0).

## Design conventions (compact theme)

Keep the UI **small and consistent** across the whole site (public + admin).

Tokens (see `:root`/`.hms-landing` in `public/css/custom.css`):

- Primary `#0f7fd4`, dark `#0b3c66`, muted `#61748a`, line `#e3ebf3`.
- Body text 13–14px; headings 22px (section) / 30px (hero H1); brand logo width 148px.

Header (guest site):

- Fixed `header`, target height ~92px: slim top contact bar (~46px) + blue navbar (~46px).
- Top bar is a flex row (brand left, contact right). Bootstrap `.container::before/::after` must stay `display:none` there so flex spacing is correct.
- Nav links: 13px, `padding: 13px 16px`, uppercase. Keep the working-hours string short so the top bar does not wrap/expand.
- `main#main { padding-top: 105px }`; landing cancels it with `.hms-landing { margin-top: -105px }` and starts its hero at `padding-top: 110px`.

Sections / flow:

- The legacy theme floats everything (`.heading`, `.serv`, `#about`). On the landing page **reset floats** and use `display: flow-root` on `.hms-section` so sections stack (no overlap).
- Section padding 34px; heading margin-bottom 22px.
- Constrain images: about 270px, department tiles 130px, doctor cards 230px, testimonials avatar 46px — always `object-fit: cover`.
- Footer: `padding: 34px 0`, `h3` 15px, `p` 13px; copyright `padding: 12px 0`.

Admin:

- Admin uses AdminLTE (`resources/views/admins/layouts/app.blade.php`); guest header/footer CSS does not apply. Keep admin panels compact and use the existing `hms-*` classes in `public/assets/css/master.css`.

## Where styles live

- `public/css/custom.css` — guest theme + appended `HMS compact header` and `HMS compact landing flow` blocks (put new guest overrides at the end).
- `public/assets/css/master.css` — admin compact theme.
- Blade: `resources/views/index.blade.php` (landing), `resources/views/layouts/app.blade.php` (guest shell).

## Testing notes (Livewire 3)

- Use `assertHasNoErrors()` / `assertHasErrors('field')`, and `assertForbidden()` for permission gates (they return 403 status, they do not throw).
- `$this->redirect(route('...'))` is safe; avoid `dd()` in component actions.
- Browser `wire:model` needs Alpine's tree initialized after Livewire boots; each layout includes a `livewire:initialized` listener that calls `window.Alpine.initTree(document.body)` (150ms delay). If typed inputs stop syncing on a new layout, add the same snippet.

## Auth & routing conventions

- Guest/tenant staff login: `GET|POST /login` (`admin_login_form` / `admin_login`) — handled by `AdminController`; staff land on `/admin/dashboard`.
- Super Admin login: `GET /admin` + `POST /admin/login` (`superadmin.login` / `superadmin.login.attempt`) and `POST /admin/logout` (`superadmin.logout`). The platform panel stays at `/superadmin/*`.
- `app/Http/Middleware/Authenticate.php::redirectTo()` points at `admin_login_form`; do not reference `route('login')` directly (the default route is overridden).
- Routes are split by module; `routes/web.php` is only a loader:
  - `routes/site.php` (public), `routes/auth.php` (`Auth::routes()` + `/login`), `routes/admin.php` (`/admin/*`, `auth`+`checksuperadmin`), `routes/superadmin.php` (`/admin*` login + `/superadmin/*`, `superadmin`).
  - Add a new web module by `require`-ing a new file inside `routes/web.php` (keeps the `web` middleware group).

## API conventions (v1, Sanctum)

- `routes/api.php` is a loader; module files live in `routes/api/` (`auth.php`, `site.php`, `appointments.php`, `admin.php`, `superadmin.php`). All inherit the `api` prefix + `api` middleware group from `bootstrap/app.php`; the loader adds the `v1` segment.
- Controllers: `App\Http\Controllers\Api\V1\*`, superadmin under `Api\V1\SuperAdmin`.
- Auth: `POST /api/v1/auth/login` (email/password/device_name → Bearer token), `GET /api/v1/auth/me`, `POST /api/v1/auth/logout` (`auth:sanctum`).
- Public: `GET /api/v1/site|doctors|departments`, `GET /api/v1/appointments/doctors` (returns `doctors.id` + employee name — use these ids for booking), `POST /api/v1/appointments/request`.
- Staff (tenant-scoped): `GET /api/v1/admin/{dashboard|patients|appointments|medicines|staff}` guarded by `auth:sanctum` + `api.staff` (requires an active tenant user).
- Platform: `GET|POST /api/v1/superadmin/tenants`, `GET|PUT|DELETE /api/v1/superadmin/tenants/{tenant}`, `GET /api/v1/superadmin/errors` guarded by `auth:sanctum` + `api.superadmin`.
- Every response uses the envelope `{ success, message?, data? }`. API middleware aliases live in `bootstrap/app.php`.
- Mirror every new web feature with a matching API endpoint for the mobile app.
- Clients must send `Accept: application/json`, otherwise the custom `auth` middleware redirects to `/login` instead of returning 401.
- PowerShell `curl.exe` mangles inline JSON — write the body to a file and use `--data-binary "@file.json"` (use `-o file -w "%{http_code}"` for status).
