# DefMan (Defect Manager) — 2026 Full-Stack Modernization

**Original Completion Date:** April 14, 2004  
**Modernization Release:** 2026 (PHP 8.3+ REST API • MySQL 8 InnoDB • React 19 + TypeScript • PixiJS v8 WebGPU)

Welcome to **DefMan (Defect Manager) — 2026 Edition**! This repository contains the **2026 Modernized Architecture** of the historical 2004 DefMan bug and fault cost tracking system: a complete full-stack migration from the [original 2004 archival codebase (`defman-2004-archive`)](https://github.com/lassesali/defman-2004-archive) (HTML 4.01 `<table>` layouts, `<?` short tags, `mysql_*` functions) into a decoupled **PHP 8.3+ PDO REST API**, a **MySQL 8.0+ InnoDB Schema**, a **React 19 (Vite + TypeScript) SPA**, and a **PixiJS v8 (WebGPU/WebGL2)** visual defect triage & IEEE (1992) fault cost analytics canvas.

---

## 📸 Screenshots & Interface Gallery

### 1. Interactive WebGPU Triage & IEEE (1992) Cost Canvas
![Interactive WebGPU Triage & IEEE (1992) Cost Canvas](defman2026_screenshot1.jpg)
*Figure 1: Hardware-accelerated PixiJS v8 (WebGPU) triage board displaying defects across lifecycle lanes (`OPEN`, `ANALYZING`, `FIXED`, `RESOLVED`). Node radius scales dynamically with total EUR defect cost, outer ring thickness reflects developer fix effort, and node color maps to its IEEE 1044-1992 fault classification. Hovering reveals real-time cost and effort breakdowns.*

### 2. Analyzer Triage & Cost Queue
![Analyzer Triage & Cost Queue](defman2026_screenshot2.jpg)
*Figure 2: The Analyzer Queue (`form_queue.php` / `form_alist.php` modernization) featuring IEEE 1992 fault badges, lead-time delta metrics (`Sub -> Assg` and `Assg -> Reso`), and instant 14-mode sorting and filtering across severity, priority, and lifecycle status.*

### 3. Multi-Column Defect Report Grid
![Multi-Column Defect Report Grid](defman2026_screenshot3.jpg)
*Figure 3: Comprehensive defect report overview (`form_list.php` modernization) with multi-dimensional filtering by severity, priority, status, and software product, alongside automated submission age calculation.*

### 4. Role-Based User Management & Approval Queue
![Role-Based User Management & Approval Queue](defman2026_screenshot4.jpg)
*Figure 4: Level 4 Administrator dashboard for managing active accounts, toggling RBAC privilege tiers (Customer, Analyzer, Admin), monitoring Katiska session online presence, and provisioning new users with auto-generated credentials.*

### 5. Defect Analyzer Workbench (IEEE 1044-1992 Classification)
![Defect Analyzer Workbench](defman2026_screenshot5.jpg)
*Figure 5: The two-stage Defect Analyzer Workbench (`form_analyze.php` & `form_deffix.php` / `form_defreso.php`) enabling root-cause SDLC phase tracking, IEEE 1992 fault classification with mutual-exclusion custom overrides, and precision EUR cost accounting.*

---

## 🚀 2026 Modernization Architecture

### Directory Structure

```text
.
├── docker-compose.yml                           # Docker Compose orchestration (MySQL 8.0 + PHP 8.3 Apache Backend)
├── database/
│   └── schema.sql                               # MySQL 8.0+ InnoDB DDL (utf8mb4_unicode_ci, Foreign Keys, Indexes)
├── backend/
│   ├── Dockerfile                               # PHP 8.3 Apache container with PDO MySQL & rewrite routing
│   ├── .dockerignore                            # Docker build context exclusions
│   ├── docker-entrypoint.sh                     # Container entrypoint (waits for DB readiness & auto-seeds)
│   ├── bin/
│   │   └── seed.php                             # CLI Database Seeder runner (`--if-empty` idempotency support)
│   ├── public/
│   │   ├── .htaccess                            # Apache rewrite & Bearer Authorization header passthrough
│   │   └── index.php                            # PHP 8.3 REST API Front Controller & Router
│   └── src/
│       ├── Database/
│       │   └── DatabaseSeeder.php               # PDO Seeder (Admin/System, Analyzer, Customer & IEEE 1992 defects)
│       ├── Domain/Enum/
│       │   ├── IeeeFaultType.php                # Native PHP 8.3 Backed Enum for IEEE (1992) Fault Classifications
│       │   └── DomainEnums.php                  # UserLevel, DefectSeverity, DefectPriority, DefectStatus, OriginPhase
│       ├── Infrastructure/Database/
│       │   └── Database.php                     # Strict-typed PDO Singleton (ERRMODE_EXCEPTION, prepared statements)
│       ├── Repository/
│       │   ├── UserRepository.php               # Argon2id auth, random_bytes(32) sessions & password recovery
│       │   ├── ProductRepository.php            # Product catalog CRUD & defect counts
│       │   ├── DefectRepository.php             # Multi-column sorting, filtering & deleted-user preservation
│       │   └── AnalysisRepository.php           # IEEE 1992 triage, lead-time delta metrics, effort (h) & EUR cost
│       └── Http/
│           ├── Middleware/
│           │   └── RbacMiddleware.php           # Enforces Level 1 (Public) to Level 4 (Admin) RBAC
│           └── Controller/
│               ├── AuthController.php           # Login, Logout, User Application, Password Recovery
│               ├── UserController.php           # Admin Approval Queue, Role Assignment, User Profile Settings
│               ├── DefectController.php         # Public/Customer Reporting, Analyzer Queue, Triage & Resolution
│               └── ProductController.php        # Product Management CRUD
├── frontend/
│   └── src/
│       ├── types/defman.ts                      # TypeScript Enums (IeeeFaultType, UserLevel, etc.) & DTOs
│       ├── api/client.ts                        # Typed Fetch REST Client with Bearer Token Auth
│       ├── App.tsx                              # React 19 SPA Shell & Role-Aware Navigation
│       └── components/
│           ├── PublicReportForm.tsx             # Level 1 Public defect submission + inline help modal
│           ├── CustomerReportForm.tsx           # Level 2+ Customer defect submission
│           ├── DefectReportGrid.tsx             # Multi-column sorting/filtering grid & full-details toggle
│           ├── DefectDetailModal.tsx            # Report inspector & editor modal
│           ├── AnalyzerQueue.tsx                # Level 3 Analyzer triage table & 5 lead-time delta metrics
│           ├── DefectAnalyzerWorkbench.tsx      # IEEE (1992) fault classification, origin phase & fix workbench
│           ├── DefectResolutionForm.tsx         # Effort hours, EUR/h rate helper, fix cost & total defect cost
│           ├── DefectTriageCanvas.tsx           # PixiJS v8 (WebGPU/WebGL) 2D interactive triage & cost canvas
│           ├── AdminUserApproval.tsx            # Level 4 Admin user management & pending applicant approval queue
│           ├── ProductManager.tsx               # Level 4 Admin product catalog CRUD
│           └── UserProfileSettings.tsx          # Password, email, and personal profile settings
├── DefMan-2026-Implementation-Document.pdf      # Complete exported implementation reference (PDF)
├── DefMan-2026-Implementation-Document.txt      # Complete exported implementation reference (TXT)
├── LICENSE                                      # Project license
└── README.md                                    # Project documentation
```

---

### 🗺️ 2004 Legacy-to-2026 Modern File Mapping

| Legacy 2004 File(s) | Modernized 2026 Layer | Key Upgrades |
| :--- | :--- | :--- |
| `install/installdb.php`, `install-part1.php`, `install-part2.php` | `database/schema.sql` & `DatabaseSeeder.php` | `MyISAM` → `InnoDB`, `utf8mb4_unicode_ci`, Foreign Keys, `DECIMAL` cost columns, PDO Seeder |
| `config.php`, `dbconnect.php`, `db.php` | `Database.php` & `*Repository.php` | Removed `<?` short tags & `mysql_*` calls; strict-typed PHP 8.3 PDO prepared statements |
| `defecttype.txt`, `faulttypes.html` | `IeeeFaultType.php` & `types/defman.ts` | Native PHP 8.3 Backed Enum & TypeScript Enum with IEEE (1992) taxonomy & hex colors |
| `login.php`, `logout.php`, `randompassword.php`, `form_lostpassword.php` | `AuthController.php` & `RbacMiddleware.php` | Plaintext passwords → `password_hash(..., PASSWORD_ARGON2ID)` & `random_bytes(32)` tokens |
| `form_raportpub.php` (+ `helpreportpub.html`) | `<PublicReportForm />` (`POST /api/defects/public`) | Level 1 unauthenticated defect reporting with inline help example |
| `form_raport.php` (+ `helpreport.html`) | `<CustomerReportForm />` (`POST /api/defects`) | Level 2+ authenticated customer defect reporting |
| `form_raportlist.php`, `form_list.php` | `<DefectReportGrid />` (`GET /api/defects`) | Multi-column sorting, filtering, `[DELETED USER]` badge & summary/full-details toggle |
| `form_defrap.php` | `<DefectDetailModal />` (`GET/PUT /api/defects/{id}`) | Modal report viewer/editor with direct jump to Analyzer Workbench |
| `form_alist.php`, `form_analyzed.php` | `<AnalyzerQueue />` (`GET /api/analyzer/queue`) | Computes `Submitted→Assigned`, `Submitted→Fixed`, `Submitted→Resolved`, `Assigned→Fixed`, `Assigned→Resolved` |
| `form_analyze.php` (+ `helpanalyze.html`) | `<DefectAnalyzerWorkbench />` (`PUT /api/defects/{id}/analyze`) | IEEE (1992) fault classification, `Other` custom override, lifecycle phase checkboxes |
| `form_deffix.php`, `form_defreso.php` | `<DefectResolutionForm />` (`PUT /api/defects/{id}/resolve`) | Logs fix description, effort hours (`fixeffort`), fix cost (`fixcost` €), and total defect cost (`costofdefect` €) |
| `form_usermanagement.php`, `form_apply.php`, `form_newuser.php` | `<AdminUserApproval />` (`GET/POST/PATCH/DELETE /api/admin/users`) | Admin user table, online/offline indicator, applicant approval/decline workflow |
| `form_productdetails.php` | `<ProductManager />` (`GET/POST/DELETE /api/admin/products`) | Admin product catalog CRUD with report counts |
| `form_userdetails.php` | `<UserProfileSettings />` (`GET/PUT /api/users/me`) | Self-service password, email, and personal settings |
| *New in 2026* | `<DefectTriageCanvas />` (PixiJS v8 WebGPU) | 2D interactive defect & cost canvas with drag-and-drop lane triage |

---

### 🎨 PixiJS v8 (WebGPU/WebGL) Visual Defect & Cost Canvas
`<DefectTriageCanvas />` (`frontend/src/components/DefectTriageCanvas.tsx`) initializes a hardware-accelerated PixiJS v8 surface (`await app.init({ preference: 'webgpu', antialias: true })`):
- **IEEE (1992) Fault Type Color Coding:** Nodes are color-coded across all 10 IEEE fault classifications (`Logic`, `Computational`, `Interface`, `Data handling`, `Data`, `Documentation`, `Document quality`, `Enhancement`, `Caused by a prev. Fix`, `Other`).
- **Severity & Priority Outer Glow Ring:** Critical and most-urgent defects render high-visibility outer rings.
- **Effort & Cost Radius Scaling:** Node radius scales proportionally to **Fix Effort (Hours)** and **Monetary Cost (€ EUR)**.
- **Interactive Lane Triage:** Supports viewport pan/zoom, hover telemetry tooltips, clicking any node to launch `<DefectAnalyzerWorkbench />`, and dragging nodes across `OPEN` → `ANALYZING` → `FIXED` → `RESOLVED` lanes to trigger `PUT /api/defects/{id}/resolve`.

---

### 🔐 Seeded Test Accounts (`DatabaseSeeder.php`)

| Role (RBAC Level) | Username | Email | Default Password |
| :--- | :--- | :--- | :--- |
| **Admin (Level 4)** | `zuck` | `zuckerberg@fb.com` | `Admin#2026!Zuck` |
| **Admin / System (Level 4)** | `billg` | `billg@microsoft.com` | `System#2026!Bill` |
| **Analyzer (Level 3)** | `julia` | `julia@hotmail.com` | `Analyze#2026!Julia` |
| **Customer (Level 2)** | `sabrin` | `sabrina@hotmail.com` | `Cust#2026!Sabrina` |

---

### ⚡ Quick Start (2026 Stack)

#### Option A: Docker (Backend + MySQL 8.0)

1. **Build and start the MySQL 8.0 database and PHP 8.3 Backend API:**
   ```bash
   docker compose up --build -d
   ```
   - Automatically provisions `database/schema.sql` on first startup.
   - Waits for database health and runs `backend/bin/seed.php --if-empty` to seed test accounts, products, and IEEE (1992) defects.
   - Exposes the REST API at `http://localhost:8080`.

2. **Build & run the backend image standalone (optional):**
   ```bash
   docker build -t defman-backend ./backend
   docker run -p 8080:8080 \
     -e DB_HOST=host.docker.internal \
     -e DB_PORT=3306 \
     -e DB_NAME=opr0413 \
     -e DB_USER=opr0413 \
     -e DB_PASS=d3fman13 \
     defman-backend
   ```

#### Option B: Local CLI Setup

1. **Provision MySQL 8 Schema & Seed Data:**
   ```bash
   mysql -u root -p opr0413 < database/schema.sql
   php backend/bin/seed.php
   ```

2. **Start the PHP 8.3+ REST API:**
   ```bash
   php -S localhost:8080 -t backend/public
   ```

3. **Start the React 19 + PixiJS v8 WebGPU SPA:**
   ```bash
   cd frontend
   npm install react react-dom pixi.js
   npm run dev
   ```

---

## ✨ Features
DefMan tracks the full lifecycle of software bugs, from reporting to resolution and cost analysis:

* **Role-Based Access Control (RBAC):** Four distinct user levels:
    * *Public (Level 1):* Can submit general bug reports.
    * *Customer (Level 2):* Can log in and track their submitted issues.
    * *Analyzer (Level 3):* Developers who analyze defects, propose fixes, and track resolution metrics.
    * *Admin (Level 4):* Full system access, including user approval and product management.
* **Defect Analysis & Fix Tracking:** Classifies bugs based on IEEE (1992) software fault types (Logic, Computational, Interface, etc.) and tracks fix effort (hours) and monetary cost (€ EUR).
* **User Management:** Account registration, admin approval workflows, user profile settings, and password recovery.
* **Custom Reporting:** Multi-column sorting and filtering of issues by priority, status, severity, and product.

---

## 🕰️ Historical Context
Originally completed on April 14, 2004 (at the University of Kuopio, Finland) using PHP 4 (`mysql_*` functions and `<?` short tags), MySQL `MyISAM`, and HTML 4.01 `<table>` layouts, DefMan has been fully re-architected in 2026 into a modern PHP 8.3+ PDO REST API, MySQL 8 InnoDB schema, and React 19 + PixiJS v8 WebGPU frontend while preserving all original domain logic, RBAC levels, and IEEE (1992) fault classification workflows. The unmigrated 2004 version is preserved in the [`lassesali/defman-2004-archive`](https://github.com/lassesali/defman-2004-archive) repository.
