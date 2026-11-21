# QueueEase – Web-Based Digital Queue Management System

A digital queue management system for hospitals, banks, colleges and government offices.
Customers take a digital ticket (for example `MED004`), watch their position and estimated
waiting time live, and go to the counter when their number is called. Staff call and serve
customers from their counters, and administrators manage the whole system.

**Technologies:** HTML5, CSS3, JavaScript (AJAX with `fetch`), core PHP 8 (PDO), and
MySQL/MariaDB on XAMPP. It uses no frameworks. The only library is Chart.js, which is stored locally in
`assets/js/vendor/` so the charts work offline.

> **New here?** Step-by-step installation for a new computer (what to install, how to import the database and how to run it): see **HOW_TO_RUN.txt** in this folder.

---

## 1. Running the project

The project runs on **PHP's built-in server on port 8080**. That way it does not interfere with
another system already using XAMPP's Apache on port 80. It uses XAMPP's **MySQL** (port 3306)
with its own database, `dqms_db`.

### Step 1 – Start MySQL
Open the **XAMPP Control Panel** and start **MySQL**. Apache can stay running for your other
project.

### Step 2 – Create the database (first time only)
**Option A – phpMyAdmin**
1. Open `http://localhost/phpmyadmin`
2. Click **Import**, choose `database/dqms_db.sql` and click **Go**.
   The file creates the `dqms_db` database itself.
3. *(Optional demo data)* Import `database/sample_data.sql` to add one week of past
   tickets so the charts and reports have data to show. These rows are marked `DEMO-`.

**Option B – command line (Git Bash or CMD)**
```bash
C:\xampp\mysql\bin\mysql.exe -u root < database\dqms_db.sql
C:\xampp\mysql\bin\mysql.exe -u root < database\sample_data.sql
```

> ⚠ Importing `dqms_db.sql` **drops and recreates** `dqms_db`. It does not touch any other database.

### Step 3 – Start the web server
Double-click **`start_server.bat`** in the project folder. It opens
`http://localhost:8080` in your browser. Keep the black window open while you use the
system, and close it (or press Ctrl+C) to stop.

To start it by hand instead:
```bash
C:\xampp\php\php.exe -S localhost:8080 -t . router.php
```

### Alternative – run inside XAMPP Apache
You can also copy the whole folder to `C:\xampp\htdocs\digital-queue-management\`
and open `http://localhost/digital-queue-management/`. URLs are worked out automatically
and the `.htaccess` files protect the private folders.

### Default accounts (change the passwords after first use)
| Role | Email | Password |
|---|---|---|
| Administrator | `admin@queueease.local` | `Admin@123` |
| Staff (Counter 1 & 5) | `grace@queueease.local` | `Staff@123` |
| Staff (Counter 2) | `brian@queueease.local` | `Staff@123` |
| Staff (Counter 3) | `faith@queueease.local` | `Staff@123` |
| Staff (Counter 4) | `kevin@queueease.local` | `Staff@123` |
| Customer | `customer@queueease.local` | `Customer@123` |

### Changing settings
Edit **`config/config.php`** to change the institution name, timezone (default
`Africa/Nairobi`), database login, session timeout and refresh intervals.

---

## Extra features
| Feature | Where |
|---|---|
| **Light / dark mode** (remembered per browser) | Sun/moon button in the top bar on every page |
| **Premium design** with local fonts (Outfit, Plus Jakarta Sans, JetBrains Mono) | `assets/fonts/` - no internet needed |
| **QR code on every active ticket** - scan it to open the live tracking page | Ticket page (customer + guest) |
| **Customer star ratings (1-5)** after being served, with optional comment | Ticket page once the status is *Served* |
| **Satisfaction analytics** - average rating card, rating chart, ratings per service and staff | Admin dashboard, Reports |
| **Keyboard shortcuts** on the counter console: `N` call next, `R` recall, `S` start, `C` complete, `K` skip | Staff console |
| **CSV export** of filtered tickets (opens in Excel) | Admin → All Tickets → Export CSV |
| Animated counters, toast pop-ups and a time-of-day greeting | Dashboards |

> **QR codes on a phone:** the code contains the address the PC is using. `localhost` only works on the PC itself. To scan with a phone on the same Wi-Fi, start the server with `C:
mpp\php\php.exe -S 0.0.0.0:8080 -t . router.php` and open the site using the PC's IP address, e.g. `http://192.168.1.20:8080`.

## 2. Suggested demonstration for your lecturer
1. Open **Live Display** (`/public/display.php`) on a second screen. Click **Voice off** to switch voice announcements on.
2. On a phone or another browser, open the home page and click **Get a Ticket**. Pick *Medical Consultation* and you get, for example, `MED001` and a tracking code.
3. Log in as **brian@queueease.local** and click **Call Next**. The display flashes and announces the ticket, and the customer's ticket page changes to *"It is your turn!"*.
4. Click **Recall**, **Start Service** and **Complete Service**, and watch the statuses change everywhere.
5. Log in as **admin** to show the dashboard charts, *All Tickets*, *Reports → Printable report* and *Activity Logs*.

---

## 3. Folder structure
```
digital queue management system/
├── index.php              Public home page (services, now serving, announcements)
├── login.php              Login for all roles (redirects by role)
├── register.php           Customer self-registration
├── logout.php             Secure logout (POST + CSRF token)
├── track.php              Track a ticket by its tracking code (guests)
├── router.php             Router for PHP's built-in server (blocks private folders)
├── start_server.bat       Double-click to start the project on port 8080
├── config/
│   ├── config.php         Application, database and security settings
│   └── database.php       PDO connection
├── includes/
│   ├── init.php           Bootstrap: config, secure session, helpers (every page loads this)
│   ├── functions.php      Escaping, validation, flash messages, pagination
│   ├── auth.php           Login/session handling and role-based access control
│   ├── csrf.php           CSRF token protection
│   ├── logger.php         Activity logging
│   ├── queue_functions.php  Core queue logic: tickets, call next, actions, wait estimate
│   ├── report_functions.php Report queries
│   ├── icons.php          Inline SVG icons
│   ├── header.php / footer.php               Dashboard layout (sidebar + top bar)
│   ├── public_header.php / public_footer.php Public page layout
│   ├── ticket_view.php    Reusable live ticket card
│   └── access_denied.php  403 page
├── admin/                 dashboard, tickets, services, counters, staff, assignments,
│                          customers, announcements, activity_logs
├── staff/                 dashboard (counter console), waiting, history
├── customer/              dashboard, get_ticket, my_ticket, history
├── queue/
│   ├── guest_ticket.php   Ticket without registration
│   ├── rate_ticket.php    Saves a customer star rating
│   └── api/               JSON endpoints: display_data, ticket_status, staff_action
├── public/display.php     Full-screen live queue display (AJAX + voice)
├── reports/               index (interactive), print (printable), report_tables
├── assets/css|js|images   Styles, scripts, icons (Chart.js in assets/js/vendor)
├── database/              dqms_db.sql (schema + default data), sample_data.sql (demo tickets + ratings)
├── tests/e2e_test.sh      Automated end-to-end test script
└── docs/TEST_CASES.md     Test case table
```

---

## 4. How the main features work

### Ticket numbers
- Format: service **prefix** plus a 3-digit **daily sequence**, e.g. `REG001`, `MED004`, `PAY012`.
- Each service starts again at 001 every day.
- The database enforces `UNIQUE (service_id, ticket_date, sequence_no)`, so duplicates are impossible.
- Each ticket also gets a random tracking code (`TRK-XXXXXX`) for guests.
- A customer (or a guest phone number) cannot hold two active tickets for the same service.

### Ticket statuses
`waiting → called → serving → served`; `called → skipped` (no-show);
`waiting → cancelled` (customer/admin; the admin can also cancel *called* tickets).
Tickets left over from a previous day are closed automatically.

### Preventing two staff from calling the same ticket
`call_next_ticket()` in `includes/queue_functions.php`:
1. Starts a **transaction** and locks the counter row (`SELECT … FOR UPDATE`), so a double click cannot call two tickets.
2. Selects the oldest waiting ticket with `FOR UPDATE` (a row lock).
3. Updates it with `… WHERE id = ? AND status = 'waiting'` and checks `rowCount()`.
   If another counter took it first, the update changes 0 rows and the next ticket is tried.

Ticket generation also locks the service row, so two people taking a ticket at the
same moment cannot get the same number.

### Estimated waiting time
`(people ahead + 1) × average service minutes ÷ open counters for the service`.
The average comes from tickets actually served today. If none have been served yet, it
falls back to the service's configured average.

### Live updates
- The display screen polls `queue/api/display_data.php` every 5 seconds.
- Ticket pages poll `queue/api/ticket_status.php` every 8 seconds.
- The staff console polls `queue/api/staff_action.php` every 5 seconds.

### Voice and alerts (free, offline)
- The display reads tickets aloud with the browser **SpeechSynthesis API**, e.g. "Ticket number M E D zero zero four, please proceed to Counter 2".
- Browsers only allow speech after a click, so press **Voice off → Voice on** once.
- Customers can press **Alert me when called** to get a sound, voice and browser notification.
- SMS needs a paid gateway (e.g. Africa's Talking or Twilio), so these on-screen and voice alerts are the local alternative.

---

## 5. Security measures
| Threat | Protection |
|---|---|
| Password theft | `password_hash()` / `password_verify()` (bcrypt); passwords are never stored in plain text |
| SQL injection | PDO **prepared statements** everywhere; `ATTR_EMULATE_PREPARES = false` |
| Cross-site scripting (XSS) | All output escaped with `e()` (`htmlspecialchars`); JS uses `escapeHtml()`/`textContent` |
| CSRF | Random token in every POST form and AJAX call, checked with `hash_equals()` |
| Session hijacking / fixation | HttpOnly + SameSite cookies, strict mode, `session_regenerate_id()` at login, 30-min idle timeout |
| Unauthorised access | `require_role()` on every protected page and API; the account is re-checked in the DB on each request |
| Brute-force login | 5 failed attempts → 5-minute lockout |
| Direct file access | `.htaccess` (Apache) and `router.php` (built-in server) block config/includes/database |
| Accountability | Logins, failed logins, access denials and every queue/admin action are written to `activity_logs` |

---

## 6. Testing
- The test case table is in [`docs/TEST_CASES.md`](docs/TEST_CASES.md).
- To rerun the automated tests, run this from **Git Bash** while the server is running:
```bash
bash tests/e2e_test.sh
```
- Afterwards, re-import `database/dqms_db.sql` for a clean demo.

## 7. Troubleshooting
| Problem | Fix |
|---|---|
| "Database connection failed" | Start MySQL in XAMPP and import `database/dqms_db.sql` |
| Port 8080 already in use | In `start_server.bat`, change `8080` to another free port, e.g. `8090` |
| Times look wrong | Set `APP_TIMEZONE` in `config/config.php` |
| No voice on display | Click the **Voice off** button once (browsers block speech until you click) |
| Charts missing | Make sure `assets/js/vendor/chart.umd.min.js` exists |
