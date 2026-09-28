# 📚 BookTrack — A System for Faster Borrowing and Returns

A simple PHP + MySQL library system with two portals:
- **Admin/Librarian** – manage books & students, a fast borrow/return counter, approve borrow requests, view transaction history.
- **Student** – register/login, browse the catalog, request to borrow, view their own loans and fines.

## 🛠 Requirements
- XAMPP (PHP 7.4+ and MySQL/MariaDB) — https://www.apachefriends.org/

## 🚀 Setup (XAMPP)

1. **Copy the project folder**
   Copy the entire `booktrack` folder into your XAMPP `htdocs` directory, e.g.:
   ```
   C:\xampp\htdocs\booktrack
   ```

2. **Start Apache and MySQL**
   Open the XAMPP Control Panel and start both **Apache** and **MySQL**.

3. **Create the database**
   - Open http://localhost/phpmyadmin
   - Click **Import** → choose the file `database.sql` from the project folder → click **Go**.
   - This creates the `booktrack_db` database with tables and sample data.

4. **Check the database config (usually no changes needed)**
   Open `config/db.php` and confirm these match your MySQL setup (defaults work for standard XAMPP):
   ```php
   $DB_HOST = 'localhost';
   $DB_NAME = 'booktrack_db';
   $DB_USER = 'root';
   $DB_PASS = '';
   ```

5. **Open the system**
   - Login page (admin and student): http://localhost/booktrack/

## 🔑 Default Login

**Admin / Librarian** — http://localhost/booktrack/
- Username: `admin`
- Password: `admin123`

**Student** — http://localhost/booktrack/
- Register a new account via the "Register here" link on the login page.

> Admin and student share one login page. After login, the system checks the account's
> role and sends the user to the correct dashboard. Every admin page re-checks the
> session role on load, so a student cannot open admin pages.

## ⚙️ Adjustable Settings

In `config/db.php`:
```php
define('LOAN_DAYS', 7);        // how many days a book can be borrowed before it's due
define('FINE_PER_DAY', 5.00);  // fine charged per day overdue
```

## 📁 Project Structure
```
booktrack/
├── config/db.php              → database connection & settings
├── includes/                  → shared functions, headers/footers
├── admin/                     → librarian portal (dashboard, books, students,
│                                 borrow/return counter, requests, history)
├── student/                   → student portal (register, browse, my books)
├── css/style.css              → all styling
├── database.sql               → database schema + sample data
└── index.php                  → login page (admin & student)
```

## 🔄 How Borrowing Works (the "faster" part)

There are two ways a loan starts:
1. **Walk-in / counter borrowing (fastest):** the librarian searches for the book AND the
   student in **Borrow / Return**, picks the student from a dropdown, and clicks
   **Borrow Now** — the loan is created instantly, due date auto-calculated.
2. **Self-service request:** a student browses the catalog and clicks **Request to
   Borrow**. It appears under the librarian's **Borrow Requests** page for a one-click
   **Approve** or **Reject**.

**Returning** is always done at the librarian's counter: search the active loan in
**Borrow / Return** and click **Return Now**. If it's late, the fine is calculated and
shown automatically based on `FINE_PER_DAY`.

## 🔒 Notes
- Passwords are hashed with PHP's `password_hash()` (bcrypt) — never stored in plain text.
- All database queries use PDO prepared statements to prevent SQL injection.
- This is a learning/capstone-style project — for real production use, add HTTPS,
  CSRF tokens, and rate-limiting on login.

## 🙋 Troubleshooting
- **"Database connection failed"** → make sure MySQL is running in XAMPP and that you
  imported `database.sql`.
- **Can't log in as admin** → re-import `database.sql`; the seeded password is `admin123`.
- **Blank white page** → check `Apache error log` in XAMPP; usually a typo path or PHP
  version issue (this project needs PHP 7.4+).
