# Quick Setup Guide

## Step-by-Step Installation

### 1. Database Setup

Create the database and import the schema:

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema
mysql -u root -p school < database/schema.sql

# Import seed data (includes admin user and sample students)
mysql -u root -p school < database/seeds.sql
```

### 2. Configuration

The system is pre-configured for localhost in `config/config.php`:

```php
'db_host' => 'localhost',
'db_name' => 'school',
'db_user' => 'root',
'db_pass' => '',
```

Update these values if your setup is different.

### 3. Access the System

Navigate to: `http://localhost/school-scan/`

You'll be automatically redirected to the login page.

### 4. Login

Use the default admin credentials:

```
Username: admin
Password: password
```

**⚠️ IMPORTANT: Change this password after first login!**

## Default Users

The seed file creates three users for testing:

| Username | Password | Role | Access Level |
|----------|----------|------|--------------|
| admin | password | Admin | Full access to all features |
| operator | password | Operator | Can scan, manage students, view reports |
| viewer | password | Viewer | Read-only access to reports |

## Sample Data

The seed file includes 5 sample students:
- STU001 - John Doe (Grade 10-A)
- STU002 - Alice Smith (Grade 10-A)
- STU003 - Michael Johnson (Grade 10-B)
- STU004 - Emily Brown (Grade 11-A)
- STU005 - Daniel Wilson (Grade 11-B)

You can use these to test the attendance scanning feature.

## Troubleshooting

### Can't login?

1. Verify the database was created: `mysql -u root -p -e "SHOW DATABASES;"`
2. Check if tables exist: `mysql -u root -p school -e "SHOW TABLES;"`
3. Verify admin user exists: `mysql -u root -p school -e "SELECT username, role FROM users;"`

### Database connection error?

1. Check `config/config.php` has correct database credentials
2. Verify MySQL is running
3. Ensure the database name matches (`school`)

### Page not found?

1. Verify your web server is pointing to the correct directory
2. Check that `.htaccess` is being read (Apache only)
3. Ensure `mod_rewrite` is enabled (Apache)

## Next Steps

After logging in:

1. **Change Password**: Go to your profile and update the default password
2. **Add Students**: Navigate to Students → Add Student
3. **Generate Barcodes**: Barcodes are automatically generated when adding students
4. **Test Scanning**: Go to Scan Attendance and test with sample student IDs
5. **View Dashboard**: Check the dashboard for attendance statistics

## Creating Additional Admin Users

Run this SQL command:

```sql
INSERT INTO users (username, password_hash, role, full_name, email, is_active)
VALUES (
    'newadmin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    'New Administrator',
    'newadmin@example.com',
    TRUE
);
```

This creates a user with:
- Username: `newadmin`
- Password: `password`

Or use PHP to hash a custom password:

```php
<?php
echo password_hash('your-password-here', PASSWORD_DEFAULT);
?>
```

## Security Checklist

Before going to production:

- [ ] Change all default passwords
- [ ] Update database credentials in config
- [ ] Enable HTTPS (uncomment in `.htaccess`)
- [ ] Set `debug` to `false` in config
- [ ] Configure email/SMS settings
- [ ] Set up regular database backups
- [ ] Review and update security headers
- [ ] Test all user roles and permissions

## Support

For issues or questions, refer to:
- Main README.md for detailed documentation
- SCANNING_GUIDE.md for barcode scanning setup
- Database schema in `database/schema.sql`
