# Troubleshooting Guide

## Common Issues and Solutions

### 1. "Not Found" Error After Login

**Symptom:** After logging in, you see "The requested URL was not found on this server" error.

**Cause:** The `app_url` configuration doesn't match your actual server path.

**Solution:**

1. Open `config/config.php`
2. Update the `app_url` setting to match your server:

```php
// For XAMPP/WAMP with project in htdocs/school-scan
'app_url' => 'http://localhost/school-scan',

// For built-in PHP server
'app_url' => 'http://localhost:8000',

// For custom port
'app_url' => 'http://localhost:8080/school-scan',
```

3. Clear your browser cache and try again

### 2. Database Connection Error

**Symptom:** "Database connection failed" error

**Solutions:**

1. **Check MySQL is running:**
   - XAMPP: Start MySQL from control panel
   - WAMP: Start MySQL service
   - Command line: `mysql -u root -p`

2. **Verify database exists:**
   ```bash
   mysql -u root -p -e "SHOW DATABASES;"
   ```
   
3. **Check credentials in config/config.php:**
   ```php
   'db_host' => 'localhost',
   'db_name' => 'school',
   'db_user' => 'root',
   'db_pass' => '',  // Your MySQL password
   ```

4. **Create database if missing:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE school CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

### 3. Can't Login - Invalid Credentials

**Symptom:** "Invalid username or password" error

**Solutions:**

1. **Verify admin user exists:**
   ```bash
   mysql -u root -p school -e "SELECT username, role FROM users;"
   ```

2. **Re-run seed file:**
   ```bash
   mysql -u root -p school < database/seeds.sql
   ```

3. **Reset password manually:**
   ```bash
   php database/reset-password.php
   ```

4. **Create admin user:**
   ```bash
   php database/create-admin.php
   ```

### 4. CSS Not Loading / Page Looks Broken

**Symptom:** Page displays but without styling

**Solution:**

The system now uses Tailwind CSS CDN, so no build step is required. If styles aren't loading:

1. **Check browser console for errors:**
   - Press F12 → Console tab
   - Look for CDN loading errors

2. **Verify internet connection:**
   - Tailwind CSS loads from `https://cdn.tailwindcss.com`
   - Chart.js loads from `https://cdn.jsdelivr.net`

3. **Clear browser cache:**
   - Press Ctrl+Shift+Delete
   - Clear cached images and files

4. **Check Content Security Policy:**
   - The `.htaccess` file should allow CDN connections
   - Verify CSP header includes `https://cdn.tailwindcss.com` and `https://cdn.jsdelivr.net`

### 5. Session Timeout / Keeps Logging Out

**Symptom:** Gets logged out frequently

**Solution:**

Edit `config/config.php`:
```php
'session_timeout' => 28800, // 8 hours in seconds
```

### 6. CSRF Token Validation Failed

**Symptom:** "CSRF token validation failed" error on form submission

**Solutions:**

1. **Clear browser cookies:**
   - Press Ctrl+Shift+Delete
   - Clear cookies and site data

2. **Check session directory is writable:**
   - PHP needs write access to session directory

3. **Verify session is starting:**
   - Check `session_start()` is called before any output

### 7. Barcode Images Not Generating

**Symptom:** Student barcodes don't appear

**Solutions:**

1. **Check storage directory permissions:**
   ```bash
   chmod 755 storage/barcodes
   ```

2. **Verify Composer dependencies installed:**
   ```bash
   composer install
   ```

3. **Check picqer library is installed:**
   ```bash
   composer require picqer/php-barcode-generator
   ```

### 8. Apache .htaccess Not Working

**Symptom:** Clean URLs don't work, 404 errors

**Solutions:**

1. **Enable mod_rewrite:**
   - XAMPP: Edit `httpd.conf`, uncomment `LoadModule rewrite_module`
   - Restart Apache

2. **Allow .htaccess overrides:**
   In `httpd.conf`, find your directory and set:
   ```apache
   <Directory "C:/xampp/htdocs">
       AllowOverride All
   </Directory>
   ```

3. **Restart Apache after changes**

### 9. Charts Not Displaying

**Symptom:** Dashboard shows but charts are missing

**Solutions:**

1. **Check browser console for errors:**
   - Press F12 → Console tab
   - Look for Chart.js errors or CSP violations

2. **Verify Chart.js is loading:**
   - Check network tab for Chart.js CDN request
   - Should load from `https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js`

3. **Content Security Policy issue:**
   - If you see "violates Content Security Policy" error
   - The `.htaccess` file has been updated to allow CDN connections
   - Restart Apache after any `.htaccess` changes

4. **Clear browser cache:**
   - Press Ctrl+F5 to hard refresh

### 10. Email/SMS Notifications Not Sending

**Symptom:** Notifications fail silently

**Solutions:**

1. **Check configuration in config/config.php:**
   ```php
   // Email settings
   'smtp_host' => 'your-smtp-host',
   'smtp_username' => 'your-username',
   'smtp_password' => 'your-password',
   
   // Twilio settings
   'twilio_account_sid' => 'your-sid',
   'twilio_auth_token' => 'your-token',
   ```

2. **Check system logs:**
   - Login as admin
   - Go to System Logs page
   - Look for notification errors

3. **Test email configuration:**
   - Use a service like Mailtrap for testing
   - Verify SMTP credentials are correct

## Getting Help

If you're still experiencing issues:

1. **Check system logs:**
   - Login as admin → System Logs
   - Look for error messages

2. **Enable debug mode:**
   In `config/config.php`:
   ```php
   'debug' => true,
   ```

3. **Check PHP error log:**
   - XAMPP: `xampp/apache/logs/error.log`
   - WAMP: `wamp/logs/php_error.log`

4. **Verify PHP version:**
   ```bash
   php -v
   ```
   Should be PHP 8.1 or higher

5. **Check database tables:**
   ```bash
   mysql -u root -p school -e "SHOW TABLES;"
   ```
   Should show 7 tables: users, students, attendance, notification_logs, retry_queue, system_logs, sessions

## Quick Diagnostic Checklist

- [ ] MySQL is running
- [ ] Database 'school' exists
- [ ] All tables are created (run schema.sql)
- [ ] Admin user exists (run seeds.sql)
- [ ] app_url in config matches your server path
- [ ] PHP version is 8.1 or higher
- [ ] Composer dependencies installed
- [ ] Storage directories are writable
- [ ] Apache mod_rewrite is enabled (if using Apache)
- [ ] Browser cookies are enabled
