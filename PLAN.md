# HMS — Master Plan & Task Tracker

> Living document. Update the checkboxes and the progress bars **after every completed task**.
> If the machine shuts down unexpectedly, this file is the source of truth for what is done and what is next.

Project: Hospital Management System (HMS) — Laravel 12 + Livewire 3 + MySQL
Repo: `D:\Amtech\hospitalms` · Dev URL: `http://127.0.0.1:8100`

---

## 0. Progress Overview

```
Overall              [####......]  ~43%   (foundation + mode + landing + booking + super admin core + API v1 auth done)

Phase 1 Super Admin  [######....]   65%   (auth, panel, tenant CRUD, error monitor built + smoke-tested)
Phase 2 Admin        [##........]   20%   (settings + mode + staff list exist)
Phase 3 Reception    [#.........]   10%   (appointment module exists)
Phase 4 Doctor       [##........]   20%   (prescriptions/history exist)
Phase 5 Lab          [..........]    0%
Phase 6 Nurse (IPD)  [..........]    0%
Phase 7 Pharmacy/Store [##########] 100%  (dispense counter, FEFO, store ledger, payment-done → bill)
Phase 8 Accountant   [####......]   38%   (GST-ready invoices, ledger + income/expense vouchers, salary run)
Phase 9 Dean/Calendar [#.........]   15%   (meeting calendar exists)
Phase 10 Reports/Notify ##.......   20%   (bell + notifications exist)
Phase 11 Platform/Multi-tenant [.........]  5%   (tenants + tenant_id + scoping backfill)
API v1 (mobile)      [##........]   20%   (Sanctum auth + site/appointments/admin/superadmin endpoints live)
```

Legend: `[#]` done · `[.]` pending. Recalculate `done / total` per phase when updating.

---

## 1. Types of Tenant

The system has **two institution modes** (already in `config/hms.php`):

| | Clinic | Hospital |
|---|---|---|
| Size | 1–5 doctors | 10+ beds, many departments |
| Login roles | admin, receptionist, doctor, pharmacist | admin, dean, doctor, nurse, receptionist, pharmacist, storekeeper, laboratorist, accountant |
| OPD | yes | yes |
| IPD / Bed / Nurse | no | yes |
| Lab | basic / outsourced | full |
| Accountant | no (pharmacist does money) | yes |

**Rule:** a mode switch hides/disables the modules and roles that are not allowed. Data is never deleted on switch, only hidden.

---

## 2. Global Rules (apply to every phase)

- [ ] **RBAC** — every route/action checked against a role permission (helpers already in `app/helpers.php`: `hms_can`, `hms_role_enabled`, `hms_enabled_roles`).
- [ ] **Audit trail** — log who did what and when for clinical/financial actions (`audit_logs` table).
- [ ] **Soft deletes** on all clinical + financial records (recoverable; user is worried about data loss).
- [ ] **Backups** — scheduled DB + uploads backup with retention; document restore steps. (Prevents history loss after a crash.)
- [ ] **Error monitor** — every uncaught exception saved to `system_errors` and shown in Super Admin → Errors (see Phase 1).
- [ ] **Design** — follow `AGENTS.md` compact theme. Same look for public + admin.
- [ ] **Search** must never `dd()`; it filters or redirects.
- [ ] **Money** — store amounts as decimal, never float; one invoice = one patient stay.
- [ ] **Every list** has: search, pagination, empty state, sort.
- [ ] **Every create/edit** has server-side validation + inline error messages.
- [ ] **No external CA needed** — accountant role does ledger, vouchers, salary, GST-ready invoices.
- [ ] **Mobile/tablet** responsive, no horizontal scroll (verified by `audit.js`).

---

## 2.5 Technical Architecture (agreed)

- **Pure Laravel 12** (no October CMS) — Services in `app/Services/*`, resolved via the **Service Container** (`app()->make`, constructor injection). No business logic in Livewire components.
- **Modular monolith** with clear **service boundaries** per domain (Tenant, Billing, Pharmacy, Lab, Clinical). Each module talks through a service interface so it can be extracted to a **microservice** later without rewriting callers.
- **Decision (2026-10-06): stay a modular monolith for v1.** A real split (per-service DBs, bus, deploy units) is a multi-week re-architecture; today the `routes/api.php` module files (`api/v1/admin|site|appointments|superadmin`) are the extraction seam, and `app/Services/*` keep domains behind interfaces. CI/CD ships now; microservice split stays a future Phase if a scale need shows up.
- **Redis** for queue + cache in production (`QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`). Code must use the queue/cache **abstractions only** (never call Redis directly) so this XAMPP box can run `sync`/`database` today. *This machine has no `redis` PHP extension yet — install before production.*
- **Queues** for slow work: SMS/WhatsApp, report generation, notifications, nightly backups.
- **Eager loading** (`with()`, `withCount()`) everywhere — no N+1; add a guard in review.
- **Pure Laravel forms** — `FormRequest` validation + Blade components; server-side validation always.
- Multi-tenant: **shared database, shared schema**, `tenant_id` on business tables. Global scope added phase by phase (Phase 11); existing rows are back-filled to tenant #1.

## 3. Phase 1 — Super Admin (platform owner: us)

Goal: one **centralized panel** that manages every hospital/clinic running on our server, and keeps **only two core jobs**: create an admin and choose clinic/hospital.

### 1.1 Tenant (Hospital/Clinic) onboarding — the only real Super Admin form
- [ ] Super Admin login is separate from tenant admin (already separate guard).
- [ ] **Step 1 — Type:** choose `Clinic` or `Hospital` (radio).
- [ ] **Step 2 — Profile form:**
  - [ ] Name of facility
  - [ ] Contact number(s)
  - [ ] Address
  - [ ] Opening / closing time + working hours text
  - [ ] Logo upload
  - [ ] Cover/hero images upload (gallery)
  - [ ] If **Hospital**: number of beds, number of floors/wards, number of staff (approx), departments list, whether lab/OT/ambulance exist.
  - [ ] If **Clinic**: number of doctors, services offered.
  - [ ] Sub-domain / domain field (e.g. `metro.hms.app` or client's own domain).
  - [ ] Timezone + currency.
- [ ] **Step 3 — Create Admin:** name, email, phone, password → admin is created and **mapped to this tenant** (`tenant_id`/`organization_id`).
- [ ] Tenant status: `active` / `suspended` / `trial` + expiry date.
- [ ] Super Admin can edit / suspend / delete a tenant.
- [ ] Super Admin can reset a tenant admin's password.

### 1.2 Tenant list dashboard
- [ ] Table: tenant name, mode, domain, admin, staff count, beds, status, created.
- [ ] Filters: mode, status, search.
- [ ] Click → drill-down (read-only overview of that tenant's key stats).
- [ ] Impersonate tenant admin (Super Admin support login) — **logged in audit**.

### 1.3 Multi-domain / centralization (Phase 11 details, decide now)
- [ ] Default deployment: one database, `tenants` table + `tenant_id` on business tables → one Super Admin sees all.
- [ ] Domain resolution: map incoming host → tenant (middleware).
- [ ] When a client takes **their own domain + own DB on our server**: add tenant connection + give Super Admin cross-tenant read (and DB access) so we can support them.
- [ ] When a client hosts **fully on their own server**: ship the same app; they get their **own separate Super Admin** (isolated). No cross-access — that is acceptable.
- [ ] Write a short decision note in this file once chosen.

### 1.4 Error monitor (Super Admin → Errors)
- [ ] `system_errors` table: tenant, url, method, user, role, message, file, line, stack, occurred_at, resolved_at.
- [ ] Hook Laravel's exception handler to also write here.
- [ ] List page: filter by tenant/date/resolved, open detail, mark resolved.
- [ ] Badge count of unresolved errors on Super Admin dashboard.

### 1.5 Super Admin dashboard
- [ ] Counts: tenants, staff, patients, appointments today, revenue today.
- [ ] System: PHP/Laravel version, DB size, storage, last backup, queue status.

**Acceptance:** from one Super Admin login we can create a clinic tenant + admin, create a hospital tenant + admin, upload logo/images, set domain, and see any error that occurred on any tenant.

### 1.6 Concrete build (files)

- Migrations: `create_tenants_table`, `add_tenant_id_to_users_table`, `create_system_errors_table`.
- Models: `App\Models\Tenant`, `App\Models\SystemError`; `User::tenant()`.
- Role `super_admin` (level 0), kept **out** of tenant mode lists.
- Auth: `SuperAdminController` (login/logout), middleware `EnsureSuperAdmin`, routes under `/superadmin`.
- Panel layout `resources/views/superadmin/layouts/app.blade.php` (AdminLTE assets, compact).
- Livewire `App\Http\Livewire\SuperAdmin\`: `Dashboard`, `Tenants` (list), `TenantForm` (create/edit wizard), `Errors` (monitor).
- Service: `App\Services\TenantService` (create tenant + admin in one DB transaction), `App\Services\ErrorLogger`.
- Exception hook in `bootstrap/app.php` → `SystemError::record()`.
- Seeder: `super@hms.com` (role super_admin) + back-fill existing data to tenant #1.

- [x] PLAN architecture documented
- [x] tenants/users/system_errors migrations
- [x] Tenant + SystemError models
- [x] super_admin role + seeder
- [x] exception → system_errors hook
- [x] super admin auth (routes/middleware/controller)
- [x] super admin layout
- [x] Livewire Dashboard / Tenants / TenantForm / Errors
- [x] smoke test + lint + audit still green
- [x] impersonate admin, tenant drill-down, password reset, timezone/currency, system stats, unresolved-error badge
- [x] routes split per module (`site/auth/admin/superadmin`) — `routes/web.php` is a loader
- [x] API v1 foundation: Sanctum + module route files + auth/site/appointments/admin/superadmin endpoints

---

## 3b. API v1 (mobile mirror)

Goal: every web feature gets a matching JSON endpoint so the mobile app stays in lock-step.

- Auth (Sanctum bearer tokens): `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, `POST /api/v1/auth/logout`.
- Public: `GET /api/v1/site`, `GET /api/v1/doctors`, `GET /api/v1/departments`, `GET /api/v1/appointments/doctors`, `POST /api/v1/appointments/request`.
- Staff (tenant-scoped): `GET /api/v1/admin/{dashboard,patients,appointments,medicines,staff}` (`auth:sanctum` + `api.staff`).
- Platform: `GET|POST /api/v1/superadmin/tenants`, `GET|PUT|DELETE /api/v1/superadmin/tenants/{tenant}`, `GET /api/v1/superadmin/errors` (`auth:sanctum` + `api.superadmin`).
- Response envelope: `{ success, message?, data? }`; `data` may be a paginator (`per_page` honoured).

- [x] Sanctum installed + `personal_access_tokens` migrated + `HasApiTokens` on `User`
- [x] `routes/api.php` loader + `routes/api/*` module files
- [x] auth / site / appointments / admin / superadmin controllers
- [ ] tenant scoping on API queries (global scope) - blocked on Phase 11
- [ ] mirror remaining web modules (reports, lab, pharmacy, nurse, accountant, dean)
- [ ] API docs / Postman collection

---

## 4. Phase 2 — Admin (tenant owner)

Goal: run one facility. Admin = Super Admin **of that tenant only**.

- [x] Settings page (site name, contacts, socials, logo, mode).
- [x] Clinic/Hospital mode stored per tenant.
- [x] Staff directory (filtered by enabled roles).
- [x] **Admin never applies for leave** — approve/reject only, and only as the
      fallback approver when no Dean exists (§9b.5, §9.5).
- [x] **Admin is view-only on clinical data** — prescriptions and reports are
      read-only for the admin; no editing of a clinical body.
- [x] **Admin is view-only on allocation** — beds/rooms are allocated by the
      Dean (executed by reception), the admin only sees occupancy.
- [x] **Admin creates users per allowed role** — the create form only offers
      roles the tenant's mode allows (clinic: doctor, receptionist, pharmacist;
      hospital: + nurse, laboratorist, storekeeper, accountant, dean/moderator).
      `staff.manage` is an admin-only permission; the role list is re-checked
      server-side, so a forged `nurse` post on a clinic is rejected, and
      `super_admin` is never assignable from inside a tenant.
- [x] Mode hides what the mode does not have (PLAN.md §16 verified by test).
- [ ] **Facilities tab** (§9c.3): every hospital/clinic with a NEW badge while it
      has no admin, its type, the roles it still needs and its staff/bed counts.
- [ ] **Admin + Dean get CRUD for each ticked role** (§9c.4) — doctors, nurses,
      labouratorist, store keeper, accountant, HR — scoped to their own tenant.
- [ ] Departments management.
- [ ] All **appointments** visible to admin.
- [ ] Admin dashboard cards: patients today, appointments, beds occupied/free, revenue, low stock, unresolved items.
- [ ] Facility profile edit (name, number, timing, logo, images, beds).
- [ ] Reports: daily/monthly, doctor-wise, department-wise, revenue, pharmacy, lab.
- [ ] Bulk import staff/patients (optional).

**Acceptance:** an admin can create every staff role allowed by the mode, see all appointments, read occupancy and reports, and never edit a prescription/report body or allocate a bed.

---

## 5. Phase 3 — Receptionist (Front Desk) + Appointments

Goal: patient walks in → registered → queued → sent to doctor; hospital adds admission (IPD).

- [x] Public + admin appointment table, status, edit/cancel, search.
- [x] Public website booking (`appointmentform`) → `requested_appointments`.
- [ ] Admin approves a website request → creates a real patient + appointment (with doctor).
- [ ] **Patient registration**: name, age/DOB, gender, phone, address, emergency contact, photo, UHID (unique patient id).
- [ ] Search existing patient before creating a new one (avoid duplicates).
- [ ] **Queue / token**: assign token number; list of waiting patients.
- [ ] **Bell to call patient:** doctor presses "Call next" → receptionist screen highlights "send [patient] in". (Livewire event/poll.)
- [ ] Vitals capture at reception: BP, pulse, temperature, SpO2, weight, height (feeds doctor screen).
- [ ] **Clinic OPD:** register → vitals → queue → doctor.
- [ ] **Hospital OPD:** + write **disease/chief complaint**, mark **OPD or IPD**.
- [ ] **IPD admission:** choose ward/bed from **available** beds → set bed `reserved` → assign doctor.
- [ ] **Assign doctor:** show doctors on duty/available; see recommendation in §9.
- [ ] Appointment states (see §8): `requested → scheduled → waiting → in_consult → treated/done → (pending) → terminated`.
- [ ] "Today" list: only today's + upcoming; completed move to Treated list and disappear from Today.
- [ ] **3-day rule:** an appointment not completed within 3 days auto-moves to `pending`, then `terminated` (configurable).
- [ ] Follow-up appointment auto-created when doctor sets a follow-up date.
- [ ] Send reminder (SMS/WhatsApp/email) — later.

**Acceptance:** receptionist can register a patient, capture vitals, queue them, send them in when the doctor calls, and (hospital) admit to a bed and assign a doctor.

---

## 6. Phase 4 — Doctor (OPD + IPD)

Goal: see patients (clinic common tasks) and manage clinical records.

- [x] Prescription model + list + history foundations.
- [ ] Doctor sees only **their own** appointments (assigned to them).
- [ ] Patient check screen: complaint, history, allergies, previous visits, current vitals.
- [ ] Update vitals (can edit what reception/nurse captured).
- [ ] Diagnosis (with ICD-10 code list), notes.
- [ ] **E-prescription:**
  - [ ] Medicine picker shows **only available stock** (batch + qty).
  - [ ] Expired / out-of-stock shown in **red and not selectable**.
  - [ ] Per medicine: dosage, **timing/frequency**, **number of days**.
  - [ ] **Quantity auto-calculated** = dose × frequency × days.
  - [ ] Save → appears on pharmacist screen (and IPD drug chart).
- [ ] **Order investigations:** tick lab/radiology tests → lab receives order.
- [ ] **View lab report** when patient returns; adjust or continue medicine.
- [ ] **Follow-up date** → auto-create next appointment.
- [ ] Referral to another doctor/department (optional).
- [ ] IPD: daily rounds notes, change orders, discharge order + discharge summary.
- [ ] Doctor cannot edit billing.

**Acceptance:** doctor completes a consultation, writes a full prescription using only available medicines (expired ones visible but greyed/red + unselectable), orders a test, later sees the report, and sets a follow-up that auto-books.

---

## 7. Phase 5 — Laboratory

Goal: perform ordered tests and publish reports online to the doctor + patient portal.

- [ ] Lab dashboard: pending orders, in-progress, completed.
- [ ] Doctor order creates a lab request (patient, tests, priority: routine/STAT).
- [ ] **Call the patient** (status + contact shown) → schedule collection.
- [ ] Sample collection + barcode/label, sample status.
- [ ] Result entry (manual now; analyzer interface later).
- [ ] Attach / upload report file + structured values.
- [ ] Report auto-**published to doctor** and to patient record (view-only).
- [ ] Critical value alert to doctor (optional).
- [ ] Test master (name, price, reference range) + packages.
- [ ] Lab billing line added to invoice.
- [ ] (Optional) Radiology tie-in: X-ray/USG/CT order → report upload.

**Acceptance:** a doctor's order reaches the lab, lab calls patient, enters/uploads result, and the doctor sees it before the follow-up visit.

---

## 8. Phase 6 — Nurse (IPD)

Goal: execute doctor's orders round the clock and log everything via the system.

- [ ] Nurse **assigned to bed** by doctor → sees only assigned patients.
- [ ] Per patient: give medicine (from chart), change saline/IV, ECG, check vitals at the **interval the doctor set**.
- [ ] **Step-by-step condition notes** (timeline) until discharge.
- [ ] Drug administration record (what/when/given by).
- [ ] Alert doctor (in-app) if critical.
- [ ] **Discharge handling** — reason captured:
  - [ ] Recovered / improved
  - [ ] Expired
  - [ ] Taken away by family (LAMA / DAMA)
  - [ ] Transferred
- [ ] On discharge → bed goes `cleaning` → `available`; final bill prepared.
- [ ] Handover notes between shifts (optional).

**Acceptance:** nurse sees assigned beds only, records meds/vitals on schedule, adds timeline notes, and triggers discharge with a reason.

---

## 9. Recommendations (open questions the user asked)

1. **Who assigns the doctor?** → **Receptionist** assigns for normal OPD walk-ins, choosing from the on-duty list. **Dean** controls the duty roster and handles IPD/emergency reassignment and exceptions. Reason: receptionist is at the counter when the patient arrives; the dean owns staffing.
2. **Who creates beds/wards?** → **Dean** creates rooms + bed numbers during setup (this is configuration, done once). **Receptionist allocates** a free bed at admission; **Nurse** updates occupied/cleaning status. **Admin is view-only** — they see occupancy and reports but never allocate. In clinic mode there is no bed module at all.
3. **Vitals** → captured by **receptionist for OPD**, by **nurse for IPD**, both editable by the **doctor**. This matches the user's two statements.
4. **Mode** → the Super Admin picks clinic/hospital at creation; Admin can request a change; only Super Admin flips it.
5. **Who owns allocation?** → **Dean**. The admin stays in view-only: occupancy, bed maps and reports are readable, but allocation and release are not available to them. Receptionist executes at the counter; the Dean owns the beds.

---

## 9b. Rooms, beds and private rooms (agreed model)

The user distinguishes three kinds of in-patient accommodation, and they are
stored differently.

| Kind | Stored as | Has bed numbers | Occupancy |
|---|---|---|---|
| **General ward** | one `rooms` row of type `general`, `capacity` beds | yes — bed numbers `G1..Gn` | many patients per room |
| **ICU** | one `rooms` row of type `icu`, `capacity` beds | yes — bed numbers `ICU1..ICUn` | one patient per bed |
| **Private room** | one `rooms` row of type `private`, `capacity = 1` | yes — bed number `P1..Pn` | one patient, whole room |

Rules:

- A **bed number always exists** for ward, ICU and private. Nothing is stored as a
  bare "occupied room" without a numbered bed, so a bed map can be printed and
  a discharge can free exactly one bed.
- **Bed numbers run per accommodation kind across the whole facility**, not per
  room: the second general ward continues `G5..G8`, and the second private room
  is `P2`, never another `P1`. Existing numbers are never renumbered, so a stay
  keeps pointing at the same bed.
- A **private room is a separate section**, never mixed into the general ward
  list, because it is billed differently and a private patient is not expected
  to share.
- **Private rooms are optional and asked about at creation.** In the Super Admin
  hospital wizard: *"Do you provide private rooms?"* → **Yes / No**.
  - **Yes** → reveal a quantity field *"How many private rooms?"* (min 1).
  - **No** → the quantity field is hidden and not validated, nothing is created.
- A facility that answered **No** cannot create a private room later either; the
  room form refuses it, so the declared capacity cannot drift.
- The private room yes/no + quantity is a **tenant property**
  (`private_room_enabled`, `private_room_count`), so it is fixed by the
  Super Admin at onboarding and only editable by them afterwards.
- Rooms, beds and their occupancy are **hospital-mode only**. Clinic mode hides
  the whole module (see §16).
- Access split (see §9b.5): Dean creates rooms + bed numbers (`beds.manage`),
  Dean and receptionist allocate (`beds.allocate`), nurse marks a bed cleaned or
  out of order (`beds.status`). Admin has none of the three.

Pending implementation:

- [x] `rooms`: add `name`, `type` += `icu`, `capacity`, `daily_rate`, `floor`.
- [x] `beds`: add `bed_number`, `status` += `cleaning`, `reserved`, `maintenance`.
- [x] `stays`: add `bed_id` so a stay occupies a numbered bed, not just a room.
- [x] Super Admin wizard: private room Yes/No + quantity (conditional field).
- [x] Dean room/bed setup screen; admin sees the same screen read-only.
- [x] Private rooms listed in their own section, separate from general ward.
- [ ] Seed a default general ward + ICU from the declared bed count at onboarding.
- [ ] Print-friendly bed map.

---

## 9c. Clinic types, required roles, and the global tenants view

The user wants the product to be sellable to any kind of facility worldwide, so
a tenant is no longer just "hospital or clinic". A clinic also declares **what
kind of clinic it is** and **which roles it actually needs**, and the platform
can see all of that at a glance.

### 9c.1 Clinic / facility types

A clinic is not one thing. A dental clinic has no ward, no nurse and no ICU; a
skin clinic has no labouratorist; a multispeciality clinic needs everything. So
the wizard asks for a **type** and that type pre-suggests the roles.

Types (seeded, editable by Super Admin, `applies_to` = hospital / clinic / both):

| Type | Typical roles it needs |
|---|---|
| General | doctor, receptionist, pharmacist |
| Dental | doctor, receptionist |
| Skin / Dermatology | doctor, receptionist, pharmacist |
| Eye / Ophthalmology | doctor, receptionist |
| ENT | doctor, receptionist |
| Orthopaedic | doctor, receptionist, nurse (if IPD), laboratorist |
| Paediatric | doctor, receptionist, nurse (if IPD), laboratorist |
| Gynaecology | doctor, receptionist, nurse (if IPD) |
| Cardiology | doctor, receptionist, nurse (if IPD), laboratorist |
| Neurology | doctor, receptionist, nurse (if IPD), laboratorist |
| Oncology | doctor, receptionist, nurse (if IPD), laboratorist, storekeeper |
| Nephrology / Dialysis | doctor, receptionist, nurse (if IPD), laboratorist |
| Multispeciality | every operational role |

- Type lives on the tenant (`clinic_type_id`) so it can be filtered, reported on
  and shown to the customer.
- The type is a **suggestion, not a lock**: the Super Admin ticks the final role
  list themselves, because only they know what the facility actually bought.

### 9c.2 Required roles are decided at creation

- When a tenant is created, the wizard shows the roles its type suggests and
  stores the **final ticked set** as the tenant's required roles
  (`tenant_role_requirements`).
- **Every screen in that tenant is driven by the required role list**, not by the
  tenant's mode alone. A dental clinic never renders the laboratory or inventory
  menu, a general clinic never renders the ward.
- This one list is the source of truth for: sidebar entries, the admin's staff
  create form, what the Dean may manage, and what the platform dashboard counts.
- Roles are **closed by default**: a role the Super Admin did not tick cannot be
  assigned to anybody in that tenant, and its pages 403 even if the URL is typed
  by hand. This is the same server-side check as §9b.5, applied to whole
  modules instead of single actions.

### 9c.3 The admin's tab: every facility, with what is missing

Inside the tenant admin panel a **"Facilities"** tab lists every hospital and
clinic the platform hosts:

- Facility **name**, **mode** and **type**.
- A **NEW** badge while nobody has been made admin of it yet — a freshly created
  tenant with no admin is un-sellable and un-usable, so this is the queue the
  office works through.
- **Which roles it still needs**: every ticked role with a headcount target and
  how many are actually staffed, so a half-built facility is obvious.
- **Staff count** and **bed count** per facility.
- Clicking through assigns the admin and adjusts the role list.

### 9c.4 Admin + Dean see CRUD for the roles that were ticked

- Once the role list is set, the **admin and the Dean** get CRUD **for each ticked
  role** — create the doctors, the nurses, the labouratorist, the store keeper —
  scoped to their own tenant.
- Both are still blocked from any role the facility did not tick.
- The admin is the tenant owner, so the admin can also change the role list's
  staffing plan; the Dean can create staff but not grant themselves a role.

### 9c.5 Global (platform) view

The Super Admin needs a sales-level view, not per-tenant detail:

- Total facilities, split hospital / clinic.
- Split **by type**, so it is visible that 14 dental clinics exist.
- **Unassigned count** — how many facilities still have no admin.
- Staff and bed totals, and utilisation.
- Exportable, so it can be shown to a prospective customer.

---

## 9d. Machines, investigations and their calculation

Every facility has diagnostic machines and each machine runs tests that are
charged for. The user wants the **whole rate card configured and calculated in
the system**, and a **print** of it.

### 9d.1 Machine master

- **Machine**: name, code, modality, department, location (OPD lab / each ICU),
  serial, vendor, purchase date, warranty end, status (working / under service /
  retired), **hourly or per-test rate**.
- **Modalities** seeded: ECG, MRI, CT Scan, X-Ray, Ultrasound, Doppler, Echo,
  Audiometry, Ophthalmology (OCT, Slit Lamp, Fundus), Pathology (Haematology,
  Biochemistry, Microbiology, Serology), BP, Spirometry, Treadmill, EEG, EMG,
  Dialysis, EMG/NCS, Mammography, Bone Densitometry, Cystoscopy, Endoscopy.
- A machine can be **attached to a bed or a room**, which is what makes ICU
  reporting work (§9d.3).

### 9d.2 Tests and how a charge is calculated

- **Test**: name, code, machine, department, turnaround time, and a **rate**.
- The charge is **calculated, not typed**, so it cannot drift from the rate card:
  - `base` — a flat fee for the test;
  - `base + per_unit × units` — e.g. per slide, per exam, per hour of scan;
  - `machine_rate × units` — used when the machine owns the price;
  - `urgent × factor` — an urgent multiplier (default 1.5);
  - optional `discount` and `tax %`, giving a final payable.
- Every line shows **how it was arrived at** (the formula is printed), so the
  counter and the patient see the same number.

### 9d.3 ICU machines and reports by bed / room

- **In the ICU the nurse and the doctor add machines themselves** — the ICU is
  where machines live and it moves fast, so it cannot wait on the Dean's setup
  round. Adding needs the ICU scope only (`icufacility`).
- A doctor opens **"Reports"**, picks a **bed number or room number** from the
  live bed map, and sees **everything recorded against that bed**: vitals,
  investigations with their calculated charge, machine readings, notes and
  medicines given.
- The same screen prints: a single sheet per bed, printable with the bed number,
  room, patient, machines used and the bill.
- Read access is any clinical role that works in the ward (doctor, nurse, Dean);
  the admin sees it read-only, same as §9b.5.

### 9d.4 Build state

- [ ] Machine master CRUD (Dean sets up, admin read-only).
- [ ] Test / rate card with a visible formula per line.
- [ ] Charge calculation + printable rate card.
- [ ] Nurse + doctor add machines in the ICU; machines attach to a bed / room.
- [ ] Doctor: pick bed / room → all reports for it.
- [ ] Print sheet per bed.

---

## 10. Phase 7 — Pharmacist + Store (Inventory)

Goal: dispense medicines and keep stock correct. In **clinic mode the pharmacist also does store/inventory** (no accountant).

- [x] Medicine master: generic + brand, composition, batch, Mfg date, Expiry, MRP, supplier.
- [x] Stock ledger: purchase (stock in), issue/dispense (stock out), return, adjustment (write-off for expiry/damage).
- [x] **Expiry alerts** 30/60/90 days; **low-stock / reorder** alerts.
- [x] FEFO/FIFO dispensing (first-expiry first-out).
- [x] Pharmacist sees doctor's e-prescriptions → dispense → **deducts stock**.
- [x] Mark medicine **"payment done"** when handed over (posts to billing).
- [x] Out-of-stock medicines cannot be dispensed (doctor side already marks red).
- [x] **Storekeeper** (hospital): stock-in via drawer, write-offs, ledger, expiry/dead-stock alerts.
- [x] Clinic: pharmacist does the above store duties too (same `medicines.manage` on pharmacist in clinic mode).
- [x] Reports: expiry / stock / reorder alerts on the store dashboard; dispense history in the ledgers.

**Acceptance:** dispensing a prescription reduces stock, expiry is visible, and payment-done posts to the patient bill.

---

## 11. Phase 8 — Accountant (Billing, Ledger, Payroll)

Goal: all money in one place; **no external CA needed**. Easy UI for non-accountants.

- [x] **Invoice fields** (GST-ready, kept simple): every bill carries amount, tax, discount, an `invoice_no`, and `paid_at` — no CA required.
- [x] Pharmacy: pharmacist marks "payment done" per medicine (creates the bill + income); **accountant collects the rest** on open invoices.
- [x] **Ledger**: every patient payment books an income voucher; every salary paid books an expense voucher; running balance is shown.
- [x] **Vouchers**: receipt (income) + payment (expense) are auto-generated from the flow (no manual double-entry yet).
- [x] **Salary run**: a month's payroll issues one due voucher per salaried staff (from `employees.salary`); "Pay" clears it and posts the expense.
- [ ] **Payslip** with allowances and deductions (net is flat = gross today).
- [ ] **Invoice = per patient stay** (OPD visit or IPD admission) with auto-charged bed / doctor / procedure / lab / radiology lines.
- [ ] Operation charges entered in patient history flow to billing (bed charge auto-calculated from allocation → discharge date).
- [ ] Reception collects first payment (registration/consult/advance) + advance adjustment.
- [ ] **At discharge:** final bill, pending amount, payment cleared, receipt printed.
- [ ] Discounts/packages, partial-payment statuses shown per line, refunds journal.
- [ ] Full books: patient ledger, **day book**, **trial balance**, **P&L**.
- [ ] CFO reports: daily collection, pending dues, doctor-wise revenue, department-wise revenue.

**Acceptance:** a patient is admitted, charges accumulate (bed, doctor, lab, medicine, operation), and on discharge the accountant produces a final invoice and clears dues; salary run works.

---

## 12. Phase 9 — Dean + Calendar / Events

Goal: dean manages staffing, approvals, and the facility calendar.

- [x] Meeting calendar (create/respond/open).
- [ ] **Calendar also supports events** beyond meetings:
  - [ ] Blood donation camp (date + time)
  - [ ] Visiting/special doctor (date + time)
  - [ ] Any hospital event
- [ ] Popup / open calendar view (month/week/day), click a day to add.
- [ ] Event types with color + who can see.
- [ ] Dean approves: leave (exists), duty roster, doctor reassignment.
- [ ] Dean sees staff, beds, admissions oversight.

**Acceptance:** dean creates a blood-donation camp and a visiting-doctor event from the calendar; both show on the right date with time.

---

## 13. Phase 10 — Reports, Analytics & Notifications

- [x] Notification bell + notifications table.
- [ ] Reports hub: patient stats, OPD/IPD census, bed occupancy, revenue, pharmacy, lab, doctor performance, due list.
- [ ] Date-range filters + export (CSV/PDF/print).
- [ ] Notifications: appointment reminders, report-ready, low-stock, due-payment — via in-app + SMS/WhatsApp/email (configurable).
- [ ] Dashboard KPIs per role.

---

## 14. Phase 11 — Platform / Multi-tenant (Super Admin infra)

- [ ] `tenants` table + `tenant_id` on all business tables.
- [ ] Host → tenant resolution middleware.
- [ ] Super Admin cross-tenant access + per-tenant DB connection option.
- [ ] Error monitor (Phase 1.4) reads across tenants.
- [ ] Backups + restore runbook.
- [ ] Audit log viewer.

---

## 15. Standard HMS modules from market research (add if the tenant is a hospital)

Already planned above: OPD, IPD, EMR, e-prescription, Pharmacy, Lab, Billing, HR/Payroll, Inventory, Calendar, Reports.
Additional / later-ready (mark in-scope when a client pays for them):

- [ ] Radiology (RIS) — imaging orders + reports
- [ ] OT (Operation Theatre) scheduling, anesthesia notes, implant tracking
- [ ] Emergency / casualty + MLC
- [ ] Blood bank
- [ ] Ambulance
- [ ] Insurance / TPA / cashless claims
- [ ] Government schemes (e.g. PMJAY/Ayushman) — region specific
- [ ] Patient portal / ABHA link (India digital health)
- [ ] SMS / WhatsApp gateway
- [ ] Multi-branch / inter-branch transfer
- [ ] NABH/MRD document management
- [ ] MIS/BI dashboards (advanced)
- [ ] AI/automation helpers (later)

---

## 16. Feature flags by mode (single source of truth)

```
CLINIC   : admin, receptionist, doctor, pharmacist
           modules: appointments, patients, OPD, prescriptions, pharmacy, billing(basic), reports
           hidden : IPD, beds, nurse, lab, store, accountant, dean
HOSPITAL : all roles
           modules: everything above + IPD, beds, nurse, lab, OT(opt), accountant,
                    store, dean, calendar/events, advanced reports
```

Keep this table and `config/hms.php` in sync.

**Refinement (§9c.2): mode is only the outer bound.** The tenant's **required role
list**, ticked by the Super Admin at creation, is what actually drives the
sidebar, the staff form, the Dean's permissions and the platform counts. So a
*dental* clinic is narrower than the `CLINIC` line above, and a *multispeciality*
hospital is wider than `HOSPITAL` implies. `config/hms.php` still bounds what a
mode may ever allow; the tenant list narrows it further. Nothing can ever widen
past the mode.

```

## 17. Build Order (do not jump the line)

```
1  Super Admin (tenant onboarding + admin + errors)      <-- start here
2  Admin (roles, profile, dashboard, view-only clinical)
2a Clinic type + required roles at creation (§9c.1, §9c.2)
2b Facilities tab + role CRUD for admin & dean (§9c.3, §9c.4)
2c Machines / investigations / ICU reporting (§9d)
3  Receptionist + Appointments (+ IPD admission)
4  Doctor (prescription engine + lab orders + follow-up)
5  Laboratory
6  Nurse (IPD)
7  Pharmacist + Store
8  Accountant
9  Dean + Calendar events
10 Reports + Notifications
11 Platform / multi-tenant hardening
```

---

## 18. How to update this file

1. Tick `- [ ]` → `- [x]` when a task is verified.
2. Recompute the phase % = done / total, update the bar and the Overall line.
3. Note the date next to major milestones.
4. Keep this file committed so a crash never loses the roadmap.

---

_Document created for the HMS build. Next action: Phase 1 done; Phase 2 in
progress — next up is §9c.1/§9c.2 (clinic type + required roles at creation),
then §9c.3 (facilities tab), then §9d (machines & ICU reporting)._
