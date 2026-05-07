<div align="center">

# 💪 Fitness Studio — Gym SaaS Platform

**A full-featured, multi-tenant Gym Management SaaS built with Laravel 11**

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-22c55e?style=flat-square)](LICENSE)
[![Status](https://img.shields.io/badge/Status-Active-22c55e?style=flat-square)]()

*Built as a portfolio project demonstrating production-grade SaaS architecture patterns.*

</div>

---

## Overview

Fitness Studio is a **multi-tenant SaaS platform** that allows gym owners to manage their entire gym operation from a single dashboard — members, trainers, attendance (including biometric ZKTeco integration), point-of-sale, and data-driven reporting with interactive charts.

Each gym operates in complete data isolation. A gym owner can only ever see and interact with their own data — enforced at the Eloquent query layer via a global scope, not just application-level checks.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend Framework | Laravel 11 (PHP 8.2+) |
| Authentication | Laravel Sanctum (API tokens + session) |
| Authorization | Spatie Laravel Permission (roles & permissions) |
| Database ORM | Eloquent with Global Scopes |
| Frontend | Blade templates, Alpine.js, Chart.js 4 |
| Notifications | Custom WhatsApp Channel + Laravel Notifications |
| Biometric | ZKTeco device integration (punch/sync protocol) |
| Styling | Custom dark UI (CSS variables, Inter font) |
| Queue | Laravel Events & Listeners |

---

## Modules

### 🏠 Dashboard
- Real-time KPI overview: total members, active memberships, today's attendance, revenue
- Recent members list and today's check-in feed
- Expiry alerts (members expiring in 7 days)

### 👥 Member Management
- Full CRUD with soft delete
- Membership assignment, renewal, and cancellation
- Plan types: Monthly, Quarterly, Yearly
- Member search and status filtering

### 🏋️ Trainer Module
- Trainer profiles with specialization, certifications, and hourly rate
- Many-to-many member assignments (with active/inactive tracking)
- Training session scheduling (upcoming/completed/cancelled)

### 🕐 Attendance System
- Manual check-in / check-out
- **ZKTeco Biometric device integration** — bulk sync and real-time punch events
- Source tracking (manual vs. biometric), duration calculation
- Today's attendance summary with check-in/out times

### 🛒 Point of Sale (POS)
- Product catalog with stock management
- Full invoice lifecycle (draft → paid / partially paid / cancelled)
- Partial payment support with payment method tracking
- Auto-generated invoice numbers
- Revenue summary endpoint

### 📊 Reports & Analytics
Interactive Chart.js dashboards for:
- **Monthly Revenue** — 12-month bar chart with year-over-year comparison
- **Member Growth** — Monthly new members (bar) + cumulative total (line)
- **Attendance Trends** — Daily / weekly / monthly check-in volume with unique member tracking

### 🔔 Notifications
Event-driven notification system:
| Event | Trigger | Channel |
|-------|---------|---------|
| `MemberCheckedIn` | Attendance check-in | WhatsApp |
| `MembershipExpiring` | Scheduled task (7 days before) | WhatsApp |
| `MembershipExpired` | Membership end date passed | WhatsApp |
| `PaymentReceived` | Invoice payment recorded | WhatsApp |

---

## Architecture

```
HTTP Request
     │
     ├── auth:sanctum          (resolve authenticated user)
     │
     └── ResolveGym Middleware
           │  ├── Validates gym exists & is active
           │  └── Sets GymContext singleton (gym_id)
           │
           └── Controller → Service → Eloquent Model
                                           │
                                    GymScope (global)
                                    WHERE gym_id = {context}
```

**Tenant isolation is enforced at the query layer.** Every model that carries a `gym_id` uses the `HasGymScope` trait, which boots a global Eloquent scope. No query can accidentally leak cross-gym data — even if a developer forgets to filter.

Super-admins bypass the scope (context stays `null` → no WHERE clause), enabling cross-gym reporting.

```
App\GymContext          ← singleton, holds current gym_id
App\Models\Scopes\GymScope    ← Eloquent Scope, reads GymContext
App\Models\Concerns\HasGymScope  ← trait, boots the scope on 9 models
App\Services\BaseService     ← abstract, exposes gymId() / requireGymId()
App\Http\Middleware\ResolveGym   ← sets GymContext per request
```

---

## Database Schema

```
gyms
 └── users (gym_id)
       ├── memberships (gym_id, plan_id)
       │     └── membership_plans (gym_id)
       ├── attendances (gym_id)
       ├── trainer_profiles (gym_id)
       ├── trainer_member [pivot] (trainer_id, member_id)
       └── training_sessions (gym_id, trainer_id, member_id)

invoices (gym_id, user_id)
 ├── invoice_items (invoice_id)
 └── payments (gym_id, invoice_id)

products (gym_id)
```

18 migrations total. All tenant tables carry a `gym_id` foreign key. `gym_id` is nullable to support super-admin records that don't belong to a single gym.

---

## API Reference

All API endpoints require `Authorization: Bearer {token}` (Sanctum).

```
GET     /api/dashboard

GET     /api/membership-plans
POST    /api/membership-plans
PUT     /api/membership-plans/{id}
DELETE  /api/membership-plans/{id}

GET     /api/members
POST    /api/members
PUT     /api/members/{id}
DELETE  /api/members/{id}
GET     /api/members/{id}/memberships
POST    /api/members/{id}/memberships
POST    /api/memberships/{id}/renew
POST    /api/memberships/{id}/cancel

GET     /api/trainers
POST    /api/trainers
PUT     /api/trainers/{id}
DELETE  /api/trainers/{id}
POST    /api/trainers/{id}/assign-member
DELETE  /api/trainers/{id}/members/{memberId}
GET     /api/trainers/{id}/members
POST    /api/trainers/{id}/sessions
GET     /api/trainers/{id}/schedule
PATCH   /api/sessions/{id}
GET     /api/sessions/upcoming

POST    /api/attendance/check-in
POST    /api/attendance/check-out
GET     /api/attendance/today
GET     /api/attendance/my-status

POST    /api/biometric/sync
POST    /api/biometric/punch

GET     /api/reports/revenue?year=2026
GET     /api/reports/members?year=2026
GET     /api/reports/attendance?period=daily&start_date=2026-04-01&end_date=2026-05-07

GET     /api/pos/products
POST    /api/pos/products
PUT     /api/pos/products/{id}
DELETE  /api/pos/products/{id}
GET     /api/pos/invoices
POST    /api/pos/invoices
GET     /api/pos/invoices/{id}
POST    /api/pos/invoices/{id}/pay
POST    /api/pos/invoices/{id}/payments
POST    /api/pos/invoices/{id}/unpay
POST    /api/pos/invoices/{id}/cancel
DELETE  /api/pos/invoices/{id}
GET     /api/pos/revenue
```

---

## Installation

```bash
# 1. Clone the repository
git clone https://github.com/your-username/fitness-studio.git
cd fitness-studio

# 2. Install PHP dependencies
composer install

# 3. Set up environment
cp .env.example .env
php artisan key:generate

# 4. Configure database in .env
DB_CONNECTION=mysql
DB_DATABASE=fitness_studio
DB_USERNAME=root
DB_PASSWORD=

# 5. Run migrations and seed
php artisan migrate --seed

# 6. Start the development server
php artisan serve
```

**Default credentials after seeding:**

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@fitness.com | password |
| Owner | owner@fitness.com | password |

---

## Project Structure

```
app/
├── GymContext.php                     ← Tenant context singleton
├── Http/
│   ├── Controllers/
│   │   ├── Api/                       ← API controllers (JSON)
│   │   └── Web/                       ← Web controllers (Blade)
│   ├── Middleware/
│   │   └── ResolveGym.php             ← Tenant isolation middleware
│   ├── Requests/                      ← Form request validation (9 classes)
│   └── Resources/                     ← API transformers (6 classes)
├── Models/
│   ├── Concerns/HasGymScope.php       ← Tenant scope trait
│   └── Scopes/GymScope.php            ← Eloquent global scope
├── Services/
│   ├── BaseService.php                ← Abstract base with gymId() helper
│   ├── AttendanceService.php
│   ├── BiometricAttendanceService.php
│   ├── DashboardService.php
│   ├── MembershipService.php
│   ├── POSService.php
│   ├── ReportService.php
│   ├── TrainerService.php
│   └── NotificationService.php
├── Channels/WhatsAppChannel.php       ← Custom notification channel
├── Events/                            ← 4 domain events
├── Listeners/                         ← 4 event listeners
└── Notifications/                     ← 4 notification classes
```

---

## Key Design Decisions

**Why global scopes instead of manual filters?**
Manual filtering (`->where('gym_id', $gymId)`) is easy to forget. A global Eloquent scope is automatic — the WHERE clause is injected at the query builder level before any SQL is executed. A developer cannot accidentally omit it.

**Why a GymContext singleton?**
Passing `$gymId` through every method signature is noise. A request-scoped singleton (`app()->singleton`) stores the resolved gym once per request (set by middleware) and is readable from anywhere — services, scopes, event listeners — without parameter drilling.

**Why BaseService?**
Centralises the `$this->gymId()` / `$this->requireGymId()` helpers so every service speaks the same language when it needs the current tenant.

---

## Author

**Muhammad [Your Name]** — Full Stack Laravel Developer

- Portfolio: [your-portfolio-url]
- LinkedIn: [your-linkedin]
- GitHub: [your-github]

---

<div align="center">
  <sub>Built with ❤️ using Laravel 11 · PHP 8.2 · MySQL</sub>
</div>
