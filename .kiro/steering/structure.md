# Project Structure

```
├── config/              # Configuration files
│   ├── config.php       # Main config (loaded globally)
│   ├── config.dev.php   # Development overrides
│   └── config.prod.php  # Production overrides
│
├── includes/            # PHP function modules (business logic)
│   ├── db.php           # PDO connection, query helpers
│   ├── auth.php         # Authentication, sessions, RBAC
│   ├── functions.php    # Validation, sanitization, utilities
│   ├── attendance.php   # Attendance recording logic
│   ├── barcode.php      # Barcode generation
│   ├── notifications.php # SMS/Email sending
│   ├── csrf.php         # CSRF protection
│   ├── header.php       # Common HTML header
│   ├── sidebar.php      # Navigation sidebar
│   └── footer.php       # Common HTML footer
│
├── pages/               # PHP page files (views + controllers)
│   ├── dashboard.php    # Main dashboard
│   ├── scan.php         # Barcode scanning interface
│   ├── students.php     # Student list
│   ├── student-*.php    # Student CRUD pages
│   ├── attendance-*.php # Attendance views
│   ├── reports.php      # Report generation
│   └── login.php        # Authentication
│
├── assets/
│   ├── css/app.css      # Tailwind source
│   ├── js/scanner.js    # Camera scanner logic
│   └── images/          # Static images
│
├── storage/
│   ├── barcodes/        # Generated barcode SVGs
│   └── exports/         # Generated reports
│
├── database/
│   ├── schema.sql       # Full database schema
│   └── seeds.sql        # Test data
│
├── cron/                # Scheduled tasks
│   └── process-retries.php
│
└── index.php            # Public landing page
```

## Conventions
- Pages load config, includes, then render HTML
- Use `require_once __DIR__ . '/../includes/...'` for includes
- All DB queries via `dbQuery()`, `dbFetchOne()`, `dbFetchAll()`, `dbInsert()`, `dbExecute()`
- Output escaping with `e()` function (htmlspecialchars wrapper)
- Flash messages via `setFlash()` / `displayFlash()`
- Auth checks: `requireAuth()`, `requireRole()`, `requireAnyRole()`
