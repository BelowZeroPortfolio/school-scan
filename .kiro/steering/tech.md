# Tech Stack

## Backend
- PHP 8.1+ (procedural style with function-based modules)
- MySQL 8.0+ with PDO (prepared statements only)
- No framework - vanilla PHP with custom includes

## Frontend
- Tailwind CSS (via CDN or compiled)
- Chart.js for dashboard visualizations
- QuaggaJS for camera-based barcode scanning

## Key Libraries (Composer)
- `picqer/php-barcode-generator` - Barcode generation
- `phpmailer/phpmailer` - Email notifications
- `tecnickcom/tcpdf` - PDF exports
- `phpoffice/phpspreadsheet` - Excel exports

## Common Commands

```bash
# Install dependencies
composer install
npm install

# Build CSS
npm run build        # Production build
npm run watch        # Development watch mode

# Database setup
mysql -u root -p < database/schema.sql
php database/create-admin.php
```

## Configuration
- Environment config in `config/config.php` (direct values, no .env parsing)
- Separate config files: `config.dev.php`, `config.prod.php`
- Storage paths: `storage/barcodes/`, `storage/exports/`
