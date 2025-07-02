# Quick Installation Guide

## Prerequisites

Before installing the Student Management System, ensure you have:

- **XAMPP/WAMP/LAMP** (or similar local server environment)
- **Web Browser** (Chrome, Firefox, Safari, Edge)
- **Text Editor** (VS Code, Sublime Text, etc.) - optional

## Step-by-Step Installation

### 1. Download & Extract

1. Download the project files
2. Extract to your web server directory:
   - **XAMPP**: `C:\xampp\htdocs\student-management\`
   - **WAMP**: `C:\wamp64\www\student-management\`
   - **Linux**: `/var/www/html/student-management/`

### 2. Database Setup

#### Option A: Using phpMyAdmin (Recommended)
1. Open `http://localhost/phpmyadmin`
2. Click "New" to create a database
3. Name it `student_management`
4. Click "SQL" tab
5. Copy and paste the contents of `database/schema.sql`
6. Click "Go" to execute

#### Option B: Using Command Line
```bash
mysql -u root -p
CREATE DATABASE student_management;
USE student_management;
SOURCE /path/to/student-management/database/schema.sql;
EXIT;
```

### 3. Configure Database Connection

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');        // Your MySQL username
define('DB_PASSWORD', '');            // Your MySQL password (leave empty for XAMPP)
define('DB_NAME', 'student_management');
```

### 4. Test Installation

1. Start your web server (XAMPP/WAMP)
2. Open browser and go to: `http://localhost/student-management/`
3. You should see the login page

### 5. Login with Default Credentials

- **Username**: `admin`
- **Password**: `password`

## Verification Checklist

- [ ] Login page displays correctly
- [ ] Can login with admin credentials
- [ ] Dashboard shows without errors
- [ ] Can access "Manage Students" page
- [ ] Modal opens when clicking "Add New Student"
- [ ] No JavaScript errors in browser console

## Troubleshooting

### Common Issues

**"Database connection failed"**
- Check if MySQL service is running
- Verify database credentials in `config/database.php`
- Ensure `student_management` database exists

**"Page not found" or 404 errors**
- Check file permissions (should be readable)
- Verify web server is running
- Ensure correct path: `http://localhost/student-management/`

**CSS/JS not loading**
- Check browser developer tools for 404 errors
- Verify file paths in HTML
- Clear browser cache

**Login not working**
- Ensure database is properly imported
- Check if `users` table has admin record
- Verify password in database (should be hashed)

### Quick Fixes

**Reset Admin Password:**
```sql
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';
```
(This sets password to 'password')

**Check Database Tables:**
```sql
SHOW TABLES;
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM subjects;
```

## Next Steps

After successful installation:

1. **Change Admin Password**: Login and update the default password
2. **Add Sample Data**: Create test students and teachers
3. **Explore Features**: Try adding students, recording grades, marking attendance
4. **Customize**: Modify colors, logos, and settings as needed

## Production Deployment

For production use:

1. **Secure Database**: Create dedicated MySQL user with limited privileges
2. **HTTPS**: Configure SSL certificate
3. **File Permissions**: Set proper file permissions (644 for files, 755 for directories)
4. **Backup**: Set up regular database backups
5. **Updates**: Keep PHP and MySQL updated

## Getting Help

- Check the main `README.md` for detailed documentation
- Review browser console for JavaScript errors
- Check web server error logs
- Verify PHP version compatibility (7.4+)

---

**Installation typically takes 5-10 minutes on a local development environment.**