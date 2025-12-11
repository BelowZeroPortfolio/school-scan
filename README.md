# Barcode Attendance System

A production-ready barcode-based student attendance tracking system built with PHP, MySQL, and Tailwind CSS.

## Features

- **Barcode Scanning**: Support for hardware scanners and camera-based scanning
- **Student Management**: Add, edit, and view student records with automatic barcode generation
- **Attendance Tracking**: Record attendance with duplicate prevention
- **Parent Notifications**: Automatic SMS/Email notifications via Twilio and PHPMailer
- **Reporting**: Generate attendance reports in CSV, PDF, and Excel formats
- **Role-Based Access**: Admin, Operator, and Viewer roles
- **Security**: CSRF protection, prepared statements, password hashing
- **Retry System**: Automatic retry for failed operations with exponential backoff

## Requirements

- PHP 8.1 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- Composer
- Node.js (for Tailwind CSS compilation)

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd attendance-system
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node dependencies and build CSS**
   ```bash
   npm install
   npm run build
   ```

4. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database and API credentials
   ```

5. **Create database**
   ```bash
   mysql -u root -p
   CREATE DATABASE attendance_dev;
   USE attendance_dev;
   SOURCE database/schema.sql;
   ```

6. **Set up storage directories**
   ```bash
   chmod 755 storage/barcodes
   chmod 755 storage/exports
   ```

7. **Configure web server**
   - Point document root to the project directory
   - Ensure `.htaccess` is enabled (for Apache)

## Configuration

### Environment Variables

Edit `.env` file with your settings:

- `APP_ENV`: Environment (development/production)
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`: Database credentials
- `MAIL_*`: Email configuration for PHPMailer
- `TWILIO_*`: Twilio credentials for SMS notifications

### Database

The database schema is located in `database/schema.sql` and includes:

- `users`: System users with role-based access
- `students`: Student records with barcode information
- `attendance`: Attendance records with timestamps
- `notification_logs`: Notification delivery tracking
- `retry_queue`: Failed operation retry queue
- `system_logs`: System event logging
- `sessions`: Secure session management

## Usage

### Default Admin Credentials

After setting up the database, you can create the admin user in two ways:

**Option 1: Run the seed file**
```bash
mysql -u root -p school < database/seeds.sql
```

**Option 2: Run the PHP script**
```bash
php database/create-admin.php
```

**Default Login Credentials:**
```
Username: admin
Password: password
```

**⚠️ Important:** Change the password immediately after first login!

The seed file also creates optional test users:
- **Operator**: Username `operator`, Password `password`
- **Viewer**: Username `viewer`, Password `password`

### Color Scheme

The system uses a purple and orange color palette:

- **Primary (Purple)**: `#8B5CF6` - Main UI elements, buttons, highlights
- **Accent (Orange)**: `#F59E0B` - Alerts, warnings, secondary actions
- **Neutrals**: Gray scale for text and backgrounds

## Project Structure

```
attendance-system/
├── config/              # Configuration files
├── includes/            # PHP business logic functions
├── pages/               # PHP page files
├── assets/              # CSS, JavaScript, images
├── storage/             # Generated files (barcodes, exports)
├── database/            # Database schema
├── cron/                # Cron job scripts
├── vendor/              # Composer dependencies
├── index.php            # Entry point
└── .htaccess            # Apache configuration
```

## Development

### Running Locally

1. Start your local server (XAMPP, WAMP, or built-in PHP server)
   ```bash
   php -S localhost:8000
   ```

2. Access the application at `http://localhost:8000`

### Building CSS

```bash
npm run watch    # Watch for changes
npm run build    # Build for production
```

## Security

- All database queries use prepared statements
- CSRF protection on all forms
- Password hashing with bcrypt
- Session security with regeneration
- Input validation and sanitization
- XSS prevention with output escaping
- Security headers via `.htaccess`

## License

Proprietary - All rights reserved

## Support

For support, please contact the system administrator.
