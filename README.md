# DefMan (Defect Manager) — 2004 Archival Project & 2026 Full-Stack Modernization

**Original Completion Date:** April 14, 2004  
**Modernization Release:** 2026 (PHP 8.3+ REST API • MySQL 8 InnoDB • React 19 + TypeScript • PixiJS v8 WebGPU)

Welcome to **DefMan (Defect Manager)**! This repository contains both:
1. **The Historical 2004 Archive (`/src/*.php`, `/src/install/*`):** Preserved in its original 2004 form (HTML 4.01 `<table>` layouts, `<?` short tags, `mysql_*` functions).
2. **The 2026 Modernized Architecture (`/src/modern/`):** A complete full-stack migration into a decoupled **PHP 8.3+ PDO REST API**, a **MySQL 8.0+ InnoDB Schema**, a **React 19 (Vite + TypeScript) SPA**, and a **PixiJS v8 (WebGPU/WebGL2)** visual defect triage & IEEE (1992) fault cost analytics canvas.

---

## 🚀 2026 Modernization Architecture (`/src/modern/`)

### Directory Structure

```text
src/
├── modern/
│   ├── database/
│   │   └── schema.sql                           # MySQL 8.0+ InnoDB DDL (utf8mb4_unicode_ci, Foreign Keys, Indexes)
│   ├── backend/
│   │   ├── public/
│   │   │   └── index.php                        # PHP 8.3 REST API Front Controller & Router
│   │   └── src/
│   │       ├── Database/
│   │       │   └── DatabaseSeeder.php           # PDO Seeder (Admin/System, Analyzer, Customer & IEEE 1992 defects)
│   │       ├── Domain/Enum/
│   │       │   ├── IeeeFaultType.php            # Native PHP 8.3 Backed Enum for IEEE (1992) Fault Classifications
│   │       │   └── DomainEnums.php              # UserLevel, DefectSeverity, DefectPriority, DefectStatus, OriginPhase
│   │       ├── Infrastructure/Database/
│   │       │   └── Database.php                 # Strict-typed PDO Singleton (ERRMODE_EXCEPTION, prepared statements)
│   │       ├── Repository/
│   │       │   ├── UserRepository.php           # Argon2id auth, random_bytes(32) sessions & password recovery
│   │       │   ├── ProductRepository.php        # Product catalog CRUD & defect counts
│   │       │   ├── DefectRepository.php         # Multi-column sorting, filtering & deleted-user preservation
│   │       │   └── AnalysisRepository.php       # IEEE 1992 triage, lead-time delta metrics, effort (h) & EUR cost
│   │       └── Http/
│   │           ├── Middleware/
│   │           │   └── RbacMiddleware.php       # Enforces Level 1 (Public) to Level 4 (Admin) RBAC
│   │           └── Controller/
│   │               ├── AuthController.php       # Login, Logout, User Application, Password Recovery
│   │               ├── UserController.php       # Admin Approval Queue, Role Assignment, User Profile Settings
│   │               ├── DefectController.php     # Public/Customer Reporting, Analyzer Queue, Triage & Resolution
│   │               └── ProductController.php    # Product Management CRUD
│   └── frontend/
│       └── src/
│           ├── types/defman.ts                  # TypeScript Enums (IeeeFaultType, UserLevel, etc.) & DTOs
│           ├── api/client.ts                    # Typed Fetch REST Client with Bearer Token Auth
│           ├── App.tsx                          # React 19 SPA Shell & Role-Aware Navigation
│           └── components/
│               ├── PublicReportForm.tsx         # Level 1 Public defect submission + inline help modal
│               ├── CustomerReportForm.tsx       # Level 2+ Customer defect submission
│               ├── DefectReportGrid.tsx         # Multi-column sorting/filtering grid & full-details toggle
│               ├── DefectDetailModal.tsx        # Report inspector & editor modal
│               ├── AnalyzerQueue.tsx            # Level 3 Analyzer triage table & 5 lead-time delta metrics
│               ├── DefectAnalyzerWorkbench.tsx  # IEEE (1992) fault classification, origin phase & fix workbench
│               ├── DefectResolutionForm.tsx     # Effort hours, EUR/h rate helper, fix cost & total defect cost
│               ├── DefectTriageCanvas.tsx       # PixiJS v8 (WebGPU/WebGL) 2D interactive triage & cost canvas
│               ├── AdminUserApproval.tsx        # Level 4 Admin user management & pending applicant approval queue
│               ├── ProductManager.tsx           # Level 4 Admin product catalog CRUD
│               └── UserProfileSettings.tsx      # Password, email, and personal profile settings
├── DefMan-2026-Implementation-Document.pdf      # Complete exported implementation reference (PDF)
└── DefMan-2026-Implementation-Document.txt      # Complete exported implementation reference (TXT)
```

---

### 🗺️ 2004 Legacy-to-2026 Modern File Mapping

| Legacy 2004 File(s) | Modernized 2026 Layer | Key Upgrades |
| :--- | :--- | :--- |
| `install/installdb.php`, `install-part1.php`, `install-part2.php` | `modern/database/schema.sql` & `DatabaseSeeder.php` | `MyISAM` → `InnoDB`, `utf8mb4_unicode_ci`, Foreign Keys, `DECIMAL` cost columns, PDO Seeder |
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
`<DefectTriageCanvas />` (`modern/frontend/src/components/DefectTriageCanvas.tsx`) initializes a hardware-accelerated PixiJS v8 surface (`await app.init({ preference: 'webgpu', antialias: true })`):
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

1. **Provision MySQL 8 Schema & Seed Data:**
   ```bash
   mysql -u root -p opr0413 < modern/database/schema.sql
   php -r '
     require "modern/backend/src/Infrastructure/Database/Database.php";
     require "modern/backend/src/Domain/Enum/IeeeFaultType.php";
     require "modern/backend/src/Domain/Enum/DomainEnums.php";
     require "modern/backend/src/Database/DatabaseSeeder.php";
     (new DefMan\Database\DatabaseSeeder(DefMan\Infrastructure\Database\Database::getConnection()))->run();
   '
   ```

2. **Start the PHP 8.3+ REST API:**
   ```bash
   php -S localhost:8080 -t modern/backend/public
   ```

3. **Start the React 19 + PixiJS v8 WebGPU SPA:**
   ```bash
   cd modern/frontend
   npm install react react-dom pixi.js
   npm run dev
   ```

---

## 🕰️ Historical Context (2004 Archival Codebase)
This project was originally developed and hosted in 2004 (partially at the University of Kuopio, Finland, as indicated by legacy URLs in the code). It demonstrates the standard web technologies and coding paradigms of the era:
* **HTML 4.01 Transitional** with `<table>`-based layouts.
* **Legacy PHP** utilizing `<?` short open tags.
* **Direct MySQL Database Integration** using the now-deprecated `mysql_*` functions.

## ✨ Features
DefMan was designed to track the full lifecycle of software bugs, from reporting to resolution and cost analysis.

* **Role-Based Access Control (RBAC):** Four distinct user levels:
    * *Public (Level 1):* Can submit general bug reports.
    * *Customer (Level 2):* Can log in and track their submitted issues.
    * *Analyzer (Level 3):* Developers who analyze defects, propose fixes, and track resolution metrics.
    * *Admin (Level 4):* Full system access, including user approval and product management.
* **Defect Analysis & Fix Tracking:** Classifies bugs based on IEEE (1992) software fault types (Logic, Computational, Interface, etc.). Tracks the effort (hours) and monetary cost of fixes.
* **User Management:** Features account registration, admin approval workflows, user settings, and password recovery.
* **Custom Reporting:** Advanced sorting and filtering of issues by priority, status, severity, and product.

## 🛠️ Technology Stack (2004 Era)
* **Backend:** PHP (Requires PHP 4 or 5)
* **Database:** MySQL
* **Frontend:** HTML 4.01, CSS, Vanilla JavaScript

## ⚠️ Running the 2004 Archival Code Today (Disclaimer)
**Do not run the unmigrated 2004 root PHP files on a modern production server.** Because the root archive is preserved as it was written in 2004, it relies on deprecated practices that are incompatible with modern PHP (PHP 7.0+). Specifically:
1.  **Deprecated Database Functions:** It uses `mysql_connect()` and `mysql_query()`, which were removed in PHP 7.0. Modern environments require `mysqli` or `PDO` (provided in `/src/modern/backend/`).
2.  **Short Open Tags:** The 2004 code relies heavily on `<?` instead of `<?php`.
3.  **Security:** The 2004 application predates modern web security frameworks and does not use prepared statements, making it vulnerable to SQL Injection (SQLi) and Cross-Site Scripting (XSS). Use the `/src/modern/` implementation for all modern deployments.

If you wish to run the 2004 archive locally for nostalgic or educational purposes, you will need to configure a legacy environment (e.g., an old version of XAMPP running PHP 5.4 or older, with `short_open_tag = On` enabled in your `php.ini`). Alternatively, you can use **Docker** with the image `plutonianbe/php54-apache:latest` to easily spin up a compatible environment.

## Application screenshots ##

<img width="622" height="220" alt="image" src="https://github.com/user-attachments/assets/8894b50e-0d86-4a44-9fac-682569dec756" />

<img width="1824" height="2314" alt="image" src="https://github.com/user-attachments/assets/66aa6096-1d14-4a72-a5b9-0761b32b8163" />

<img width="1920" height="2222" alt="image" src="https://github.com/user-attachments/assets/420d75ab-d0b3-41a4-8de9-a9091a9932f4" />

## 📄 License
This project is open-sourced for archival and educational purposes.
