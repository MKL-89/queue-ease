# QueueEase – Test Cases

**Test environment:** Windows 11 Pro, XAMPP (PHP 8.3, MariaDB 10.11), PHP built-in server on `localhost:8080`, Chromium-based browser.

**How tests were run**
- **Auto:** run by `tests/e2e_test.sh` (HTTP requests with curl plus database checks) on **20 Nov 2026**. All 96 checks passed.
- **Browser:** checked by hand in the browser on 20 Nov 2026.
- **Pending:** not yet run. Fill in *Actual result* and *Status* after you test them yourself.

> Rerun the tests on your own machine before the presentation and update this table with your results and screenshots.

| Test ID | Functionality | Test steps / input | Expected result | Actual result | Status | Method |
|---|---|---|---|---|---|---|
| **Database connection** |||||||
| TC-01 | Database connection | Open any page with MySQL running | Page loads with data from `dqms_db` | All pages returned HTTP 200 with data | Pass | Auto |
| TC-02 | DB unavailable message | Stop MySQL, open home page | Friendly "Database connection failed" message | | Pending | Manual |
| **Login and logout** |||||||
| TC-03 | Admin login | `admin@queueease.local` / `Admin@123` | Redirect to admin dashboard | Redirected to `admin/dashboard.php` | Pass | Auto + Browser |
| TC-04 | Staff login | `grace@queueease.local` / `Staff@123` | Redirect to counter console | Redirected to `staff/dashboard.php` | Pass | Auto + Browser |
| TC-05 | Customer login | `customer@queueease.local` / `Customer@123` | Redirect to customer dashboard | Redirected to `customer/dashboard.php` | Pass | Auto |
| TC-06 | Wrong password | Valid email, wrong password | Error "Incorrect email or password", no login | Login refused | Pass | Auto |
| TC-07 | Brute-force lockout | 5 wrong passwords, then correct password | "Too many failed attempts" for 5 minutes | Correct password refused with lockout message | Pass | Auto |
| TC-08 | Inactive account | Deactivate staff, try to log in | Login refused | Login refused | Pass | Auto |
| TC-09 | Secure logout | Click Log out, then open dashboard | Session destroyed, redirect to login | Redirected to login (302) | Pass | Auto + Browser |
| TC-10 | Logout via GET link | Open `logout.php` directly | Ignored (logout needs POST + CSRF token) | User stayed logged in | Pass | Auto |
| TC-11 | Session idle timeout | Leave logged in for 30+ minutes, then click | Logged out with "inactivity" message | | Pending | Manual |
| **User registration** |||||||
| TC-12 | Customer registration | Valid name, email, phone, password | Account created, redirect to login | Account and customer profile created | Pass | Auto |
| TC-13 | Duplicate email | Register same email again | "An account with this email already exists" | Message shown | Pass | Auto |
| TC-14 | Invalid input | Name "X", email "bad", phone "12", password "short" | Form rejected, fields highlighted | Rejected with field errors | Pass | Auto |
| **Role permissions** |||||||
| TC-15 | Not logged in | Open `admin/dashboard.php` | Redirect to login | 302 to login | Pass | Auto |
| TC-16 | Customer opens admin page | Customer opens `admin/dashboard.php` | 403 Access denied | 403 | Pass | Auto |
| TC-17 | Staff opens reports | Staff opens `reports/index.php` | 403 Access denied | 403 | Pass | Auto |
| TC-18 | Customer opens staff console | Customer opens `staff/dashboard.php` | 403 Access denied | 403 | Pass | Auto |
| TC-19 | Staff uses unassigned counter | Grace calls next on Counter 2 via API | "You are not assigned to this counter" | Refused | Pass | Auto |
| TC-20 | View another customer's ticket | Customer opens `my_ticket.php?id=<other>` | Refused, redirect to history | Redirected | Pass | Auto |
| **Ticket generation** |||||||
| TC-21 | Customer ticket | Choose Medical Consultation | Ticket `MED001` created, status Waiting | `MED001` created | Pass | Auto |
| TC-22 | Daily sequence | 5 guests take MED tickets | `MED002` … `MED006` in order | `MED001,…,MED006` | Pass | Auto |
| TC-23 | Guest ticket + tracking code | Guest form with name, phone, service | Ticket and `TRK-XXXXXX` code shown | Ticket page shown with code | Pass | Auto + Browser |
| TC-24 | Queue position / wait | Track `MED006` with 5 ahead | Position 6, 5 ahead, estimated minutes | Position 6, 5 ahead | Pass | Auto |
| TC-25 | Unknown tracking code | `TRK-ZZZZZZ` | "Ticket not found" (404) | 404 | Pass | Auto |
| TC-26 | Inactive service | Deactivate service, try to take ticket | "Service is not available" | | Pending | Manual |
| **Duplicate tickets** |||||||
| TC-27 | Same customer, same service | Take MED ticket twice | Second refused: "You already have an active ticket" | Refused, 1 ticket only | Pass | Auto |
| TC-28 | Same customer, other service | Take MED then PAY | Both allowed | 2 tickets | Pass | Auto |
| TC-29 | Guest duplicate | Same phone + same service twice | Second refused | Refused | Pass | Auto |
| **Queue processing** |||||||
| TC-30 | Call Next | Staff clicks Call Next | Oldest waiting ticket becomes Called at this counter | `MED004` called at Counter 3 | Pass | Auto + Browser |
| TC-31 | Simultaneous Call Next | Two staff at two MED counters call at the same instant | Each gets a **different** ticket | `MED001` and `MED002` | Pass | Auto |
| TC-32 | Call while busy | Call Next with a ticket still open | "Finish or skip ticket … first" | Refused | Pass | Auto |
| TC-33 | Recall | Click Recall on a called ticket | Recall count +1, display re-announces | Recall count = 1 | Pass | Auto |
| TC-34 | Start Service | Click Start Service | Status Serving | Serving | Pass | Auto |
| TC-35 | Complete Service | Click Complete Service | Status Served, completion time saved | Served | Pass | Auto |
| TC-36 | Wrong order | Complete a ticket that was not started | "Action not allowed while ticket is called" | Refused | Pass | Auto |
| TC-37 | Skip | Click Skip on a called ticket | Status Skipped, logged | Skipped | Pass | Auto |
| TC-38 | Customer cancel | Customer cancels own waiting ticket | Status Cancelled | Cancelled | Pass | Auto |
| TC-39 | Cancel another's ticket | Customer posts another ticket's ID | Refused | Status unchanged | Pass | Auto |
| TC-40 | Admin cancel | Admin cancels waiting ticket | Status Cancelled | Cancelled | Pass | Auto |
| TC-41 | Position updates | After 2 tickets processed, check MED006 | People ahead drops from 5 to 3 | 3 ahead | Pass | Auto |
| **Counter assignment / admin management** |||||||
| TC-42 | Create service | Name "Test Service", prefix TST | Service created | Created | Pass | Auto |
| TC-43 | Duplicate prefix | New service with prefix MED | "Prefix is already used" | Rejected | Pass | Auto |
| TC-44 | Delete service in use | Delete Medical Consultation | Refused (has tickets/counters) | Refused | Pass | Auto |
| TC-45 | Register staff | New staff with valid data | Staff account created | Created | Pass | Auto |
| TC-46 | Assign staff to counter | Assign new staff to Counter 1 | Assignment saved | Saved | Pass | Auto |
| TC-47 | Duplicate assignment | Assign same staff to same counter again | Refused (only 1 row) | 1 row | Pass | Auto |
| TC-48 | Announcement | Publish announcement | Appears on display ticker | Appeared in display API | Pass | Auto |
| **Live display** |||||||
| TC-49 | Display auto-refresh | Keep display open, staff calls ticket | "Now calling" changes within ~5 s without reload | Changed to `MED003 – Counter 2` | Pass | Browser |
| TC-50 | Display privacy | Inspect display data | No customer names/phones | None found | Pass | Auto |
| TC-51 | Voice announcement | Click Voice on, call a ticket | Chime + spoken ticket and counter | | Pending | Manual |
| **Security** |||||||
| TC-52 | CSRF on forms | Submit ticket form with forged token | Rejected, no ticket | No ticket created | Pass | Auto |
| TC-53 | CSRF on AJAX | Staff action with forged token | "Session has expired" | Refused | Pass | Auto |
| TC-54 | SQL injection (search) | Search `' OR 1=1 -- ` | Treated as text, no rows, no error | No rows, page OK | Pass | Auto |
| TC-55 | SQL injection (login) | Email `' OR '1'='1` | Login refused | Refused | Pass | Auto |
| TC-56 | XSS (input) | Name `<script>alert(1)</script>` | Rejected / escaped, script never runs | Rejected by validation | Pass | Auto |
| TC-57 | XSS (reflected) | `track.php?ref=<script>…` | Output escaped | Escaped | Pass | Auto |
| TC-58 | Private folders | Open `config/config.php`, `includes/init.php`, `database/dqms_db.sql` | 403 Forbidden | 403 | Pass | Auto |
| TC-59 | Password storage | Inspect `users.password_hash` in phpMyAdmin | Bcrypt hash (`$2y$…`), not plain text | Bcrypt hashes stored | Pass | Auto |
| TC-60 | Activity log | Perform actions, open Activity Logs | Logins, failures, ticket actions listed | Rows recorded | Pass | Auto |
| **Reports** |||||||
| TC-61 | Report page | Open Reports with a date range | Summary, daily, service, counter, staff tables + charts | Page loads without errors | Pass | Auto + Browser |
| TC-62 | Printable report | Click Printable report → Print | Clean A4 layout without menus | | Pending | Manual |
| **Responsive design** |||||||
| TC-63 | Mobile ticket page | Open ticket page at 375 px wide | Readable card, no overflow | Displays correctly | Pass | Browser |
| TC-64 | Mobile dashboard | Open staff/admin page at 375 px wide | Sidebar hidden behind ☰ menu; tables scroll | Menu opens and closes; tables scroll | Pass | Browser |
| TC-65 | Tablet / TV display | Open display on a large screen and a tablet | Layout adapts | | Pending | Manual |
| **Extra features** |||||||
| TC-66 | QR code on ticket | Open an active ticket | QR code shown that opens the tracking page | QR rendered with tracking URL | Pass | Auto + Browser |
| TC-67 | Rate served ticket | Owner gives 5 stars | Rating saved | Saved (5) | Pass | Auto |
| TC-68 | Rate twice | Owner rates the same ticket again | Refused, one rating only | 1 row kept | Pass | Auto |
| TC-69 | Rate someone else's ticket | Staff/other user posts a rating | Refused | No rating saved | Pass | Auto |
| TC-70 | Invalid rating | 9 stars | Rejected (1-5 only) | Rejected | Pass | Auto |
| TC-71 | CSV export | Admin clicks Export CSV | `.csv` file with header row and filtered tickets | text/csv with tickets | Pass | Auto |
| TC-72 | CSV export permission | Customer opens export URL | 403 | 403 | Pass | Auto |
| TC-73 | Light / dark mode | Click sun/moon button, reload | Theme switches and is remembered | Switches and persists | Pass | Browser |
| TC-74 | Keyboard shortcuts | On the staff console press N, S, C | Call next, start, complete | | Pending | Manual |

## Known limitations found during testing
- The login lockout is tracked per browser session. Clearing cookies resets the attempt counter, and a production system would also track attempts per IP address in the database.
- The estimated waiting time is an approximation based on today's average service time.
- Voice announcements depend on the voices installed in the browser or operating system.
