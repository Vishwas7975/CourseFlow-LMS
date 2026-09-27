# CourseFlow LMS

A multi-role Learning Management System supporting Admin, Trainer, Student, and Finance portals — built for managing course delivery, live classes, quizzes, coding challenges, internships, and payments end to end.

---

## Features

- **Multi-role access**: separate dashboards for Admin, Trainer, Student, and Finance
- **Course management**: course creation, syllabus uploads, lesson delivery, and enrollment tracking
- **Live classes & scheduling**: trainers can schedule and manage live sessions
- **Assessments**: quizzes, coding challenges, and progress tracking per student
- **Certificates**: automated certificate generation and verification
- **Payments**: course/internship/project payment tracking with finance reporting
- **Authentication**: standard login plus Google OAuth sign-in
- **Notifications**: in-app notification system for role-based updates

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP, MySQLi |
| Frontend | HTML, CSS, JavaScript |
| Database | MySQL |
| Auth | Google OAuth 2.0 |
| Email | SMTP (PHPMailer-style setup) |

---

## Project Structure

```
admin/        Admin dashboard — courses, users, payments, verification
trainer/      Trainer dashboard — lessons, quizzes, live classes
student/      Student dashboard — courses, assignments, certificates
finance/      Finance dashboard — payments, revenue reports
api/          REST-style endpoints (enrollment, certificates)
internship/   Internship registration & payment flows
fdp/          Faculty Development Program registration
includes/     Shared components (mailer, OAuth, sidebar, header)
config/       Database & environment configuration
assets/       CSS/JS/images
schema.sql    Database schema (structure only, no data)
```

---

## Getting Started

### Prerequisites

- PHP 8+
- MySQL Server
- Apache/Nginx (or XAMPP for local dev)
- A Google Cloud OAuth 2.0 Client (for Google Sign-In)

### 1. Clone the Repository

```bash
git clone https://github.com/Vishwas7975/CourseFlow-LMS
cd CourseFlow-LMS
```

### 2. Set Up the Database

```sql
CREATE DATABASE courseflow_lms;
```

Then import the schema:

```bash
mysql -u your_db_username -p courseflow_lms < schema.sql
```

### 3. Configure Environment Variables

Update `config/db.php` with your database credentials:

```php
$conn = new mysqli("localhost", "your_db_username", "your_db_password_here", "courseflow_lms");
```

Update `config/secrets.php` with your own SMTP and Google OAuth credentials:

```php
define('VS_SMTP_HOST', 'your-smtp-host');
define('VS_SMTP_USER', 'your-email@yourdomain.com');
define('VS_SMTP_PASS', 'your_smtp_password_here');

define('VS_GOOGLE_CLIENT_ID', 'your-google-client-id.apps.googleusercontent.com');
define('VS_GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
define('VS_GOOGLE_REDIRECT_URI', 'https://yourdomain.com/google_callback.php');
```

**Never commit real credentials.** Keep `config/secrets.php` out of version control in your own deployments (add it to `.gitignore`).

### 4. Run the Application

Point your web server's document root to the project folder, or use PHP's built-in server for local testing:

```bash
php -S localhost:8000
```

---

## Notes

This is a portfolio version of a production LMS — sample configuration values are used throughout, and the included `schema.sql` contains table structure only (no real user data).

---

## License

This project is shared for portfolio and demonstration purposes.
