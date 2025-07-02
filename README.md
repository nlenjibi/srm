# Student Management System

A comprehensive web-based application for managing student records in educational institutions. Built with PHP, MySQL, and AJAX for real-time updates and modern user experience.

## 🚀 Features

### User Management
- **Multi-role Authentication**: Admin, Teacher, and Student roles with different permissions
- **Secure Session Management**: Session timeout, role-based access control
- **User Account Creation**: Automatic user account generation for students and teachers

### Student Information Management
- **Complete Student Profiles**: Personal details, contact information, parent information
- **Academic Records**: Class, section, admission date, student ID generation
- **Real-time CRUD Operations**: Create, read, update, delete students with AJAX
- **Advanced Search & Filtering**: Search by name, student ID, filter by class/section/status

### Grading System
- **Multiple Exam Types**: Quiz, midterm, final, assignment
- **Automatic Grade Calculation**: Based on percentage thresholds (A: 90+, B: 80+, etc.)
- **Grade Reports**: Comprehensive grade tracking and reporting
- **Teacher Grade Entry**: Real-time grade input with immediate calculation

### Attendance Management
- **Daily Attendance Tracking**: Mark present, absent, or late with remarks
- **Bulk Operations**: Mark all present/absent functionality
- **Attendance Reports**: Daily and periodic attendance summaries
- **Real-time Updates**: AJAX-powered attendance recording

### Dashboard & Analytics
- **Statistical Overview**: Total students, teachers, subjects, classes
- **Recent Activities**: Latest students, grades, attendance
- **Quick Actions**: Direct access to common operations
- **Real-time Updates**: Live dashboard statistics

### Additional Features
- **Extracurricular Activities**: Track student participation in activities
- **Subject Management**: Manage subjects with credits and descriptions
- **Class Management**: Organize students into classes and sections
- **Responsive Design**: Works seamlessly on desktop, tablet, and mobile devices

## 🏗️ Project Structure

```
student-management/
├── index.php                 # Login page
├── config/
│   ├── database.php          # Database connection and utilities
│   └── config.php            # Application configuration
├── includes/
│   ├── header.php            # Dashboard header with navigation
│   └── footer.php            # Dashboard footer
├── auth/
│   ├── login.php             # Login processing
│   ├── logout.php            # Logout processing
│   └── session.php           # Session management and authentication
├── admin/
│   ├── dashboard.php         # Admin dashboard
│   ├── manage_students.php   # Student management (CRUD)
│   ├── manage_teachers.php   # Teacher management
│   ├── manage_users.php      # User account management
│   ├── subjects.php          # Subject management
│   ├── classes.php           # Class management
│   └── reports.php           # Report generation
├── teacher/
│   ├── dashboard.php         # Teacher dashboard
│   ├── students.php          # View assigned students
│   ├── grades.php            # Grade management
│   ├── attendance.php        # Attendance recording
│   └── profile.php           # Teacher profile
├── student/
│   ├── dashboard.php         # Student dashboard
│   ├── profile.php           # Student profile
│   ├── grades.php            # View grades
│   ├── attendance.php        # View attendance
│   ├── schedule.php          # Class schedule
│   └── activities.php        # Extracurricular activities
├── api/
│   ├── students.php          # Student API (AJAX endpoints)
│   ├── teachers.php          # Teacher API
│   ├── grades.php            # Grades API
│   ├── attendance.php        # Attendance API
│   └── dashboard.php         # Dashboard statistics API
├── assets/
│   ├── css/
│   │   └── style.css         # Modern responsive stylesheet
│   └── js/
│       └── app.js            # AJAX functionality and interactions
├── database/
│   └── schema.sql            # Database schema and sample data
└── README.md                 # Project documentation
```

## 📋 Requirements

- **Web Server**: Apache/Nginx with PHP support
- **PHP**: Version 7.4 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Browser**: Modern browser with JavaScript enabled

## ⚙️ Installation

### 1. Clone or Download
```bash
git clone <repository-url>
# or download and extract the ZIP file
```

### 2. Database Setup
1. Create a MySQL database named `student_management`
2. Import the database schema:
```bash
mysql -u username -p student_management < database/schema.sql
```

### 3. Configure Database Connection
Edit `config/database.php` with your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'your_username');
define('DB_PASSWORD', 'your_password');
define('DB_NAME', 'student_management');
```

### 4. Set Up Web Server
- Place the project folder in your web server's document root
- Ensure PHP has write permissions for session management
- Configure virtual host (optional but recommended)

### 5. Access the Application
Open your browser and navigate to:
```
http://localhost/student-management/
```

## 👤 Default Login Credentials

### Administrator
- **Username**: `admin`
- **Password**: `password`

### Demo Accounts (Create via admin panel)
- **Teacher**: `teacher` / `password`
- **Student**: `student` / `password`

## 🔧 Configuration

### Application Settings
Edit `config/config.php` to customize:
- Application name and version
- Session timeout duration
- Pagination settings
- Grade calculation thresholds
- File upload settings

### Security Settings
- Change default passwords immediately after installation
- Set secure session parameters for production
- Configure HTTPS for production deployment
- Review and update file permissions

## 🚀 Usage Guide

### Admin Dashboard
1. **Login** as administrator
2. **Manage Users**: Create teacher and student accounts
3. **Add Students**: Use the student management interface
4. **Setup Classes**: Create classes and assign teachers
5. **Configure Subjects**: Add subjects with credits
6. **Monitor System**: Use dashboard for overview

### Teacher Functions
1. **View Students**: See assigned students and their details
2. **Record Grades**: Enter exam scores with automatic grade calculation
3. **Mark Attendance**: Daily attendance with bulk operations
4. **Generate Reports**: Class performance and attendance reports

### Student Portal
1. **View Profile**: Personal information and academic details
2. **Check Grades**: Exam results and overall performance
3. **View Attendance**: Attendance history and statistics
4. **Activity Participation**: Extracurricular activities

## 🔄 AJAX Features

The system uses AJAX extensively for real-time updates:

### Real-time Operations
- **Student Search**: Instant search without page reload
- **Grade Calculation**: Automatic grade computation
- **Form Submissions**: Seamless form processing
- **Data Filtering**: Dynamic content filtering
- **Dashboard Updates**: Live statistics updates

### JavaScript Classes
- **StudentManager**: Handles student CRUD operations
- **GradeManager**: Manages grade entry and calculation
- **AttendanceManager**: Attendance recording functionality
- **Dashboard**: Real-time dashboard updates
- **AjaxHelper**: Centralized AJAX request handling

## 🎨 UI/UX Features

### Modern Design
- **Responsive Layout**: Mobile-first design approach
- **Clean Interface**: Intuitive navigation and user experience
- **Font Awesome Icons**: Professional iconography
- **Color-coded Status**: Visual status indicators
- **Loading States**: User feedback during operations

### Accessibility
- **Keyboard Navigation**: Full keyboard accessibility
- **Screen Reader Support**: Semantic HTML structure
- **High Contrast**: Clear visual hierarchy
- **Error Handling**: Comprehensive error messages

## 🔒 Security Features

### Authentication & Authorization
- **Password Hashing**: Secure password storage using PHP's password_hash()
- **Session Security**: Secure session configuration with timeout
- **Role-based Access**: Different permission levels for user types
- **Input Validation**: Server-side validation and sanitization
- **SQL Injection Prevention**: Prepared statements throughout

### Data Protection
- **XSS Prevention**: Output escaping and input sanitization
- **CSRF Protection**: Token-based form protection (can be enhanced)
- **Error Handling**: Secure error logging without information disclosure

## 📊 Database Schema

### Core Tables
- **users**: Authentication and user management
- **students**: Student personal and academic information
- **teachers**: Teacher profiles and qualifications
- **subjects**: Subject definitions with credits
- **classes**: Class organization and teacher assignments
- **grades**: Exam scores and grade records
- **attendance**: Daily attendance tracking
- **extracurricular_activities**: Activity management
- **student_activities**: Student-activity relationships

### Key Relationships
- Users → Students/Teachers (One-to-One)
- Students → Grades (One-to-Many)
- Students → Attendance (One-to-Many)
- Teachers → Classes (One-to-Many)
- Subjects → Grades (One-to-Many)

## 🔧 Customization

### Adding New Features
1. **Create API Endpoint**: Add new PHP file in `/api/` directory
2. **Update JavaScript**: Extend existing classes or create new ones
3. **Add UI Components**: Create new pages following existing structure
4. **Update Navigation**: Modify `includes/header.php` for new menu items

### Theming
- **CSS Variables**: Customize colors in `:root` selector in `style.css`
- **Layout Changes**: Modify grid layouts and responsive breakpoints
- **Icon Updates**: Replace Font Awesome icons as needed

## 🐛 Troubleshooting

### Common Issues

**Database Connection Errors**
- Verify database credentials in `config/database.php`
- Ensure MySQL service is running
- Check database user permissions

**AJAX Not Working**
- Verify JavaScript console for errors
- Ensure proper file paths in AJAX requests
- Check server logs for PHP errors

**Login Issues**
- Verify default admin user exists in database
- Check session configuration
- Ensure proper file permissions

**Styling Issues**
- Clear browser cache
- Verify CSS file paths
- Check for JavaScript errors preventing CSS loading

## 📈 Future Enhancements

### Planned Features
- **Email Notifications**: Parent and student notifications
- **SMS Integration**: Attendance and grade alerts
- **Report Generation**: PDF reports and transcripts
- **Calendar Integration**: Events and examination schedules
- **Online Examination**: Digital exam platform
- **Fee Management**: Student fee tracking and payments
- **Library Management**: Book issuing and tracking
- **Transport Management**: Bus routes and tracking

### Technical Improvements
- **API Documentation**: Swagger/OpenAPI documentation
- **Unit Testing**: PHPUnit test coverage
- **Performance Optimization**: Caching and query optimization
- **Progressive Web App**: Offline functionality
- **Real-time Notifications**: WebSocket implementation

## 📝 License

This project is open source and available under the [MIT License](LICENSE).

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📞 Support

For support and questions:
- Create an issue on GitHub
- Check the troubleshooting section
- Review the code documentation

## 🙏 Acknowledgments

- **Font Awesome** for the comprehensive icon library
- **PHP Community** for excellent documentation and resources
- **Modern CSS Grid** for flexible layouts
- **AJAX/Fetch API** for seamless user experience

---

**Built with ❤️ for educational institutions worldwide**

