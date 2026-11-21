#!/bin/bash
# =====================================================================
#  QueueEase - automated end-to-end HTTP test script
#  File: tests/e2e_test.sh
#  Run from Git Bash while the server is running on http://localhost:8080:
#      bash tests/e2e_test.sh
#  WARNING: it deletes TODAY's non-demo tickets first so results are repeatable.
#  Re-import database/dqms_db.sql afterwards for a clean demo.
# =====================================================================
# End-to-end HTTP tests for QueueEase (run against http://localhost:8080)
B=http://localhost:8080
T=$(mktemp -d)
pass=0; fail=0
ok()   { echo "PASS  $1"; pass=$((pass+1)); }
bad()  { echo "FAIL  $1"; fail=$((fail+1)); }
check(){ if eval "$2"; then ok "$1"; else bad "$1"; fi; }
token(){ grep -o 'name="csrf_token" value="[a-f0-9]*"' "$1" | head -1 | sed 's/.*value="//;s/"//'; }
metatoken(){ grep -o 'name="csrf-token" content="[a-f0-9]*"' "$1" | head -1 | sed 's/.*content="//;s/"//'; }
login(){ # $1 jar  $2 email  $3 password
  curl -s -c $1 -b $1 $B/login.php -o $T/l.html
  local tk=$(token $T/l.html)
  curl -s -c $1 -b $1 -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf_token=$tk" --data-urlencode "email=$2" --data-urlencode "password=$3" $B/login.php
}
SQL(){ /c/xampp/mysql/bin/mysql.exe -u root dqms_db -N -e "$1"; }

# reset today's live data so the test is repeatable
SQL "DELETE FROM queue_tickets WHERE ticket_date = CURDATE() AND reference_code NOT LIKE 'DEMO-%'; DELETE FROM customers WHERE phone IN ('0722111222','0733444555'); DELETE FROM users WHERE email='testcust@example.com';"

# ---- Pages load ----
for p in index.php login.php register.php track.php queue/guest_ticket.php public/display.php queue/api/display_data.php; do
  c=$(curl -s -o /dev/null -w "%{http_code}" $B/$p); check "GET $p -> 200" "[ $c = 200 ]"
done
for p in config/config.php includes/init.php database/dqms_db.sql; do
  c=$(curl -s -o /dev/null -w "%{http_code}" $B/$p); check "Private file $p blocked -> 403" "[ $c = 403 ]"
done

# ---- Authentication ----
r=$(login $T/bad.jar admin@queueease.local WrongPass1); check "Wrong password rejected (no redirect)" "[ -z \"$r\" ]"
r=$(login $T/admin.jar admin@queueease.local Admin@123); check "Admin login redirects to admin dashboard" "[[ \"$r\" == *admin/dashboard.php ]]"
r=$(login $T/staff.jar grace@queueease.local Staff@123); check "Staff login redirects to staff console" "[[ \"$r\" == *staff/dashboard.php ]]"
r=$(login $T/staff2.jar brian@queueease.local Staff@123); check "Second staff login" "[[ \"$r\" == *staff/dashboard.php ]]"
r=$(login $T/staff3.jar faith@queueease.local Staff@123); check "Third staff login" "[[ \"$r\" == *staff/dashboard.php ]]"
r=$(login $T/cust.jar customer@queueease.local Customer@123); check "Customer login redirects to customer dashboard" "[[ \"$r\" == *customer/dashboard.php ]]"

# ---- Role-based access ----
c=$(curl -s -o /dev/null -w "%{http_code}" $B/admin/dashboard.php); check "Anonymous -> admin page redirected to login" "[ $c = 302 ]"
c=$(curl -s -b $T/cust.jar -o /dev/null -w "%{http_code}" $B/admin/dashboard.php); check "Customer -> admin page = 403" "[ $c = 403 ]"
c=$(curl -s -b $T/staff.jar -o /dev/null -w "%{http_code}" $B/reports/index.php); check "Staff -> reports = 403" "[ $c = 403 ]"
c=$(curl -s -b $T/cust.jar -o /dev/null -w "%{http_code}" $B/staff/dashboard.php); check "Customer -> staff console = 403" "[ $c = 403 ]"
for p in admin/dashboard.php admin/tickets.php admin/services.php admin/counters.php admin/staff.php admin/assignments.php admin/customers.php admin/announcements.php admin/activity_logs.php reports/index.php reports/print.php; do
  c=$(curl -s -b $T/admin.jar -o $T/page.html -w "%{http_code}" $B/$p)
  check "Admin GET $p -> 200, no PHP errors" "[ $c = 200 ] && ! grep -qiE 'Fatal error|Warning:|Notice:|Deprecated:' $T/page.html"
done
for p in staff/dashboard.php staff/waiting.php staff/history.php; do
  c=$(curl -s -b $T/staff.jar -o $T/page.html -w "%{http_code}" $B/$p)
  check "Staff GET $p -> 200, no PHP errors" "[ $c = 200 ] && ! grep -qiE 'Fatal error|Warning:|Notice:|Deprecated:' $T/page.html"
done
for p in customer/dashboard.php customer/get_ticket.php customer/history.php; do
  c=$(curl -s -b $T/cust.jar -o $T/page.html -w "%{http_code}" $B/$p)
  check "Customer GET $p -> 200, no PHP errors" "[ $c = 200 ] && ! grep -qiE 'Fatal error|Warning:|Notice:|Deprecated:' $T/page.html"
done

# ---- Registration ----
curl -s -c $T/reg.jar -b $T/reg.jar $B/register.php -o $T/r.html; tk=$(token $T/r.html)
r=$(curl -s -c $T/reg.jar -b $T/reg.jar -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf_token=$tk" -d "full_name=Test Customer&email=testcust@example.com&phone=0733444555&password=Passw0rd1&confirm_password=Passw0rd1" $B/register.php)
check "Customer registration succeeds" "[[ \"$r\" == *login.php ]]"
curl -s -c $T/reg.jar -b $T/reg.jar $B/register.php -o $T/r.html; tk=$(token $T/r.html)
curl -s -c $T/reg.jar -b $T/reg.jar -o $T/r2.html --data-urlencode "csrf_token=$tk" -d "full_name=Test Customer&email=testcust@example.com&phone=0733444555&password=Passw0rd1&confirm_password=Passw0rd1" $B/register.php
check "Duplicate email registration rejected" "grep -q 'already exists' $T/r2.html"
curl -s -c $T/reg.jar -b $T/reg.jar $B/register.php -o $T/r.html; tk=$(token $T/r.html)
curl -s -c $T/reg.jar -b $T/reg.jar -o $T/r3.html --data-urlencode "csrf_token=$tk" -d "full_name=X&email=bad&phone=12&password=short&confirm_password=short" $B/register.php
check "Invalid registration data rejected" "grep -q 'Please correct the highlighted fields' $T/r3.html"

# ---- CSRF ----
r=$(curl -s -b $T/cust.jar -c $T/cust.jar -o /dev/null -w "%{http_code}" -d "service_id=1&csrf_token=forged" $B/customer/get_ticket.php)
cnt=$(SQL "SELECT COUNT(*) FROM queue_tickets t JOIN customers c ON c.id=t.customer_id WHERE c.user_id=6 AND t.ticket_date=CURDATE()")
check "Forged CSRF token: no ticket created" "[ $cnt = 0 ]"

# ---- Ticket generation: customer ----
curl -s -b $T/cust.jar -c $T/cust.jar $B/customer/get_ticket.php -o $T/g.html; tk=$(metatoken $T/g.html)
r=$(curl -s -b $T/cust.jar -c $T/cust.jar -o /dev/null -w "%{redirect_url}" -d "service_id=2&csrf_token=$tk" $B/customer/get_ticket.php)
check "Customer gets MED ticket" "[[ \"$r\" == *my_ticket.php?id=* ]]"
num=$(SQL "SELECT ticket_number FROM queue_tickets t JOIN customers c ON c.id=t.customer_id WHERE c.user_id=6 AND t.ticket_date=CURDATE()")
check "Ticket number is MED001 (daily sequence restarts)" "[ \"$num\" = MED001 ]"
r=$(curl -s -b $T/cust.jar -c $T/cust.jar -o /dev/null -w "%{redirect_url}" -d "service_id=2&csrf_token=$tk" $B/customer/get_ticket.php)
cnt=$(SQL "SELECT COUNT(*) FROM queue_tickets t JOIN customers c ON c.id=t.customer_id WHERE c.user_id=6 AND t.ticket_date=CURDATE()")
check "Duplicate active ticket for same service blocked" "[ $cnt = 1 ]"
curl -s -b $T/cust.jar $B/customer/get_ticket.php -o $T/g2.html
check "Duplicate shows a clear message" "grep -q 'already have an active ticket' $T/g2.html"
curl -s -b $T/cust.jar -c $T/cust.jar -o /dev/null -d "service_id=3&csrf_token=$tk" $B/customer/get_ticket.php
cnt=$(SQL "SELECT COUNT(*) FROM queue_tickets t JOIN customers c ON c.id=t.customer_id WHERE c.user_id=6 AND t.ticket_date=CURDATE()")
check "Same customer can hold tickets for different services" "[ $cnt = 2 ]"

# ---- Ticket generation: guests (5 more MED tickets) ----
for i in 1 2 3 4 5; do
  curl -s -c $T/g$i.jar -b $T/g$i.jar $B/queue/guest_ticket.php -o $T/gt.html; tk=$(token $T/gt.html)
  curl -s -c $T/g$i.jar -b $T/g$i.jar -o /dev/null --data-urlencode "csrf_token=$tk" -d "service_id=2&full_name=Guest Person $(echo ABCDE | cut -c$i)&phone=07001112$i$i" $B/queue/guest_ticket.php
done
seq=$(SQL "SELECT GROUP_CONCAT(ticket_number ORDER BY sequence_no) FROM queue_tickets WHERE service_id=2 AND ticket_date=CURDATE()")
check "Guest tickets continue the sequence MED001..MED006" "[ \"$seq\" = 'MED001,MED002,MED003,MED004,MED005,MED006' ]"
ref=$(SQL "SELECT reference_code FROM queue_tickets WHERE ticket_number='MED006' AND ticket_date=CURDATE()")
j=$(curl -s "$B/queue/api/ticket_status.php?ref=$ref")
check "Tracking API: MED006 position 6, 5 ahead" "echo '$j' | grep -q '\"people_ahead\":5' && echo '$j' | grep -q '\"position\":6'"
c=$(curl -s -o /dev/null -w "%{http_code}" "$B/queue/api/ticket_status.php?ref=TRK-ZZZZZZ"); check "Tracking unknown code -> 404" "[ $c = 404 ]"
c=$(curl -s -o $T/tr.html -w "%{http_code}" "$B/track.php?ref=$ref"); check "Track page shows ticket" "grep -q MED006 $T/tr.html"

# guest duplicate (same phone, same service)
curl -s -c $T/g1.jar -b $T/g1.jar $B/queue/guest_ticket.php -o $T/gt.html; tk=$(token $T/gt.html)
curl -s -c $T/g1.jar -b $T/g1.jar -o $T/gd.html --data-urlencode "csrf_token=$tk" -d "service_id=2&full_name=Guest Person A&phone=0700111211" $B/queue/guest_ticket.php
check "Guest duplicate (same phone + service) blocked" "grep -q 'already have an active ticket' $T/gd.html"

# ---- Staff queue processing ----
curl -s -b $T/staff2.jar $B/staff/dashboard.php -o $T/s2.html; tk2=$(metatoken $T/s2.html)
curl -s -b $T/staff3.jar $B/staff/dashboard.php -o $T/s3.html; tk3=$(metatoken $T/s3.html)
curl -s -b $T/staff.jar  $B/staff/dashboard.php -o $T/s1.html; tk1=$(metatoken $T/s1.html)

j=$(curl -s -b $T/staff.jar -d "counter_id=2&action=call&csrf_token=$tk1" $B/queue/api/staff_action.php)
check "Staff not assigned to Counter 2 is refused" "echo '$j' | grep -q 'not assigned'"

# Two staff at two MED counters press Call Next at the same moment
curl -s -b $T/staff2.jar -d "counter_id=2&action=call&csrf_token=$tk2" $B/queue/api/staff_action.php > $T/c2.json &
curl -s -b $T/staff3.jar -d "counter_id=3&action=call&csrf_token=$tk3" $B/queue/api/staff_action.php > $T/c3.json &
wait
a=$(grep -o 'Now calling MED00[0-9]' $T/c2.json); b=$(grep -o 'Now calling MED00[0-9]' $T/c3.json)
check "Simultaneous Call Next gives different tickets ($a / $b)" "[ -n \"$a\" ] && [ -n \"$b\" ] && [ \"$a\" != \"$b\" ]"
d=$(SQL "SELECT COUNT(*) FROM queue_tickets WHERE ticket_date=CURDATE() AND service_id=2 AND status='called'")
check "Exactly two MED tickets are 'called'" "[ $d = 2 ]"
j=$(curl -s -b $T/staff2.jar -d "counter_id=2&action=call&csrf_token=$tk2" $B/queue/api/staff_action.php)
check "Cannot call next while a ticket is open at the counter" "echo '$j' | grep -q 'before calling the next'"
j=$(curl -s -b $T/staff2.jar -d "counter_id=2&action=complete&csrf_token=$tk2" $B/queue/api/staff_action.php)
check "Cannot complete a ticket that was not started" "echo '$j' | grep -q 'not allowed'"
j=$(curl -s -b $T/staff2.jar -d "counter_id=2&action=recall&csrf_token=$tk2" $B/queue/api/staff_action.php)
check "Recall works" "echo '$j' | grep -q 'recalled'"
j=$(curl -s -b $T/staff2.jar -d "counter_id=2&action=start&csrf_token=$tk2" $B/queue/api/staff_action.php)
check "Start service -> serving" "echo '$j' | grep -q '\"status\":\"serving\"'"
j=$(curl -s -b $T/staff2.jar -d "counter_id=2&action=complete&csrf_token=$tk2" $B/queue/api/staff_action.php)
check "Complete service -> served" "echo '$j' | grep -q 'marked as served'"
j=$(curl -s -b $T/staff3.jar -d "counter_id=3&action=skip&csrf_token=$tk3" $B/queue/api/staff_action.php)
check "Skip -> skipped" "echo '$j' | grep -q 'skipped'"
j=$(curl -s -b $T/staff2.jar -d "counter_id=2&action=call&csrf_token=forged" $B/queue/api/staff_action.php)
check "Staff action with forged CSRF refused" "echo '$j' | grep -q 'session has expired'"
st=$(SQL "SELECT GROUP_CONCAT(CONCAT(ticket_number,':',status) ORDER BY sequence_no) FROM queue_tickets WHERE ticket_date=CURDATE() AND service_id=2")
echo "      MED queue now: $st"
rc=$(SQL "SELECT recall_count FROM queue_tickets WHERE ticket_date=CURDATE() AND service_id=2 AND status='served'")
check "Recall count recorded" "[ \"$rc\" = 1 ]"
j=$(curl -s "$B/queue/api/ticket_status.php?ref=$ref")
check "MED006 now has 3 ahead after 2 processed" "echo '$j' | grep -q '\"people_ahead\":3'"

# ---- Display API ----
j=$(curl -s $B/queue/api/display_data.php)
check "Display API returns counters, recent calls and waiting" "echo '$j' | grep -q '\"recent\":\[{' && echo '$j' | grep -q '\"waiting\"'"
check "Display API exposes no customer names/phones" "! echo '$j' | grep -qiE 'Guest Person|0700111'"

# ---- Cancel ----
curl -s -b $T/cust.jar -c $T/cust.jar $B/customer/dashboard.php -o $T/cd.html; tk=$(metatoken $T/cd.html)
pid=$(SQL "SELECT t.id FROM queue_tickets t JOIN customers c ON c.id=t.customer_id WHERE c.user_id=6 AND t.ticket_date=CURDATE() AND t.service_id=3")
curl -s -b $T/cust.jar -o /dev/null -d "ticket_id=$pid&csrf_token=$tk" $B/customer/my_ticket.php
s=$(SQL "SELECT status FROM queue_tickets WHERE id=$pid"); check "Customer cancels own waiting ticket" "[ $s = cancelled ]"
other=$(SQL "SELECT id FROM queue_tickets WHERE ticket_number='MED006' AND ticket_date=CURDATE()")
curl -s -b $T/cust.jar -o /dev/null -d "ticket_id=$other&csrf_token=$tk" $B/customer/my_ticket.php
s=$(SQL "SELECT status FROM queue_tickets WHERE id=$other"); check "Customer cannot cancel someone else's ticket" "[ $s = waiting ]"
c=$(curl -s -b $T/cust.jar -o /dev/null -w "%{redirect_url}" "$B/customer/my_ticket.php?id=$other"); check "Customer cannot view someone else's ticket" "[[ \"$c\" == *history.php ]]"

# ---- Admin CRUD ----
curl -s -b $T/admin.jar $B/admin/services.php -o $T/as.html; tk=$(metatoken $T/as.html)
curl -s -b $T/admin.jar -o /dev/null -d "action=save&id=0&name=Test Service&prefix=TST&description=Testing&avg_service_minutes=4&is_active=1&csrf_token=$tk" $B/admin/services.php
sid=$(SQL "SELECT id FROM services WHERE prefix='TST'"); check "Admin creates service" "[ -n \"$sid\" ]"
curl -s -b $T/admin.jar -o $T/dup.html -d "action=save&id=0&name=Another&prefix=MED&avg_service_minutes=4&is_active=1&csrf_token=$tk" $B/admin/services.php
check "Duplicate prefix rejected" "grep -q 'already used' $T/dup.html"
curl -s -b $T/admin.jar -o /dev/null -d "action=delete&id=2&csrf_token=$tk" $B/admin/services.php
x=$(SQL "SELECT COUNT(*) FROM services WHERE id=2"); check "Service with tickets cannot be deleted" "[ $x = 1 ]"
curl -s -b $T/admin.jar -o /dev/null -d "action=delete&id=$sid&csrf_token=$tk" $B/admin/services.php
x=$(SQL "SELECT COUNT(*) FROM services WHERE prefix='TST'"); check "Unused service deleted" "[ $x = 0 ]"
curl -s -b $T/admin.jar -o /dev/null -d "action=save&id=0&full_name=Test Staff&email=teststaff@example.com&phone=0712345678&password=Staff1234&is_active=1&csrf_token=$tk" $B/admin/staff.php
uid=$(SQL "SELECT id FROM users WHERE email='teststaff@example.com' AND role='staff'"); check "Admin registers staff" "[ -n \"$uid\" ]"
curl -s -b $T/admin.jar -o /dev/null -d "action=assign&user_id=$uid&counter_id=1&csrf_token=$tk" $B/admin/assignments.php
x=$(SQL "SELECT COUNT(*) FROM staff_counters WHERE user_id=$uid"); check "Admin assigns staff to counter" "[ $x = 1 ]"
curl -s -b $T/admin.jar -o /dev/null -d "action=assign&user_id=$uid&counter_id=1&csrf_token=$tk" $B/admin/assignments.php
x=$(SQL "SELECT COUNT(*) FROM staff_counters WHERE user_id=$uid"); check "Duplicate assignment prevented" "[ $x = 1 ]"
curl -s -b $T/admin.jar -o /dev/null -d "action=toggle&id=$uid&csrf_token=$tk" $B/admin/staff.php
r=$(login $T/ts.jar teststaff@example.com Staff1234); check "Deactivated staff cannot log in" "[ -z \"$r\" ]"
curl -s -b $T/admin.jar -o /dev/null -d "action=delete&id=$uid&csrf_token=$tk" $B/admin/staff.php
x=$(SQL "SELECT COUNT(*) FROM users WHERE id=$uid"); check "Admin deletes staff" "[ $x = 0 ]"
curl -s -b $T/admin.jar -o /dev/null -d "action=save&id=0&title=Test notice&message=This is a test announcement&is_active=1&csrf_token=$tk" $B/admin/announcements.php
j=$(curl -s $B/queue/api/display_data.php); check "New announcement appears on display API" "echo '$j' | grep -q 'Test notice'"
SQL "DELETE FROM announcements WHERE title='Test notice'"
w=$(SQL "SELECT id FROM queue_tickets WHERE ticket_number='MED006' AND ticket_date=CURDATE()")
curl -s -b $T/admin.jar -H "Referer: $B/admin/tickets.php" -o /dev/null -d "action=cancel&ticket_id=$w&csrf_token=$tk" $B/admin/tickets.php
s=$(SQL "SELECT status FROM queue_tickets WHERE id=$w"); check "Admin cancels a waiting ticket" "[ $s = cancelled ]"

# ---- XSS ----
curl -s -c $T/x.jar -b $T/x.jar $B/queue/guest_ticket.php -o $T/gt.html; tk=$(token $T/gt.html)
curl -s -c $T/x.jar -b $T/x.jar -o $T/x.html --data-urlencode "csrf_token=$tk" --data-urlencode "full_name=<script>alert(1)</script>" -d "service_id=1&phone=0722111222" $B/queue/guest_ticket.php
check "Script tag in name rejected by validation" "grep -q 'Please correct' $T/x.html && ! grep -q '<script>alert(1)' $T/x.html"
c=$(curl -s -o $T/x2.html -w "%{http_code}" "$B/track.php?ref=%3Cscript%3Ealert(1)%3C/script%3E")
check "Reflected input is escaped on track page" "! grep -q '<script>alert(1)' $T/x2.html"

# ---- SQL injection ----
c=$(curl -s -b $T/admin.jar -o $T/sqli.html -w "%{http_code}" "$B/admin/tickets.php?q=%27%20OR%201%3D1%20--%20&from=2000-01-01")
check "SQL injection attempt in search returns no rows and no error" "[ $c = 200 ] && grep -q 'No tickets match' $T/sqli.html"
r=$(login $T/sq.jar "' OR '1'='1" "x"); check "SQL injection in login rejected" "[ -z \"$r\" ]"

# ---- Customer star rating ----
mref=$(SQL "SELECT t.reference_code FROM queue_tickets t JOIN customers c ON c.id=t.customer_id WHERE c.user_id=6 AND t.ticket_date=CURDATE() AND t.status='served'")
mid=$(SQL "SELECT t.id FROM queue_tickets t WHERE t.reference_code='$mref'")
curl -s -b $T/cust.jar -c $T/cust.jar "$B/customer/my_ticket.php?id=$mid" -o $T/mt.html; tk=$(metatoken $T/mt.html)
check "Served ticket shows rating form to its owner" "grep -q 'How was your service' $T/mt.html"
curl -s -b $T/staff.jar $B/staff/dashboard.php -o $T/s1.html; stk=$(metatoken $T/s1.html)
curl -s -b $T/staff.jar -o /dev/null -d "ref=$mref&rating=1&csrf_token=$stk" $B/queue/rate_ticket.php
x=$(SQL "SELECT COUNT(*) FROM service_feedback WHERE ticket_id=$mid"); check "Non-owner cannot rate a ticket" "[ $x = 0 ]"
curl -s -b $T/cust.jar -o /dev/null -d "ref=$mref&rating=9&csrf_token=$tk" $B/queue/rate_ticket.php
x=$(SQL "SELECT COUNT(*) FROM service_feedback WHERE ticket_id=$mid"); check "Invalid rating (9 stars) rejected" "[ $x = 0 ]"
curl -s -b $T/cust.jar -o /dev/null -d "ref=$mref&rating=5&comment=Great service&csrf_token=$tk" $B/queue/rate_ticket.php
x=$(SQL "SELECT rating FROM service_feedback WHERE ticket_id=$mid"); check "Owner rates served ticket 5 stars" "[ \"$x\" = 5 ]"
curl -s -b $T/cust.jar -o /dev/null -d "ref=$mref&rating=1&csrf_token=$tk" $B/queue/rate_ticket.php
x=$(SQL "SELECT COUNT(*) FROM service_feedback WHERE ticket_id=$mid"); check "Ticket can only be rated once" "[ $x = 1 ]"
wref=$(SQL "SELECT reference_code FROM queue_tickets WHERE ticket_date=CURDATE() AND status='waiting' LIMIT 1")
curl -s -o $T/qr.html "$B/track.php?ref=$wref"; check "Active ticket page includes QR code" "grep -q 'data-qr=\"http' $T/qr.html"
c=$(curl -s -b $T/admin.jar -o /dev/null -w "%{http_code}" "$B/reports/index.php"); check "Reports page with ratings loads" "[ $c = 200 ]"

# ---- CSV export ----
h=$(curl -s -b $T/admin.jar -D - -o $T/export.csv "$B/admin/tickets.php?export=csv" | grep -i "content-type")
check "CSV export returns text/csv" "echo '$h' | grep -qi 'text/csv'"
check "CSV export contains today's tickets" "grep -q 'MED001' $T/export.csv && head -1 $T/export.csv | grep -q 'Tracking code'"
c=$(curl -s -b $T/cust.jar -o /dev/null -w "%{http_code}" "$B/admin/tickets.php?export=csv"); check "Customer cannot export CSV" "[ $c = 403 ]"

# ---- Logout ----
curl -s -b $T/cust.jar -c $T/cust.jar $B/customer/dashboard.php -o $T/cd.html; tk=$(metatoken $T/cd.html)
curl -s -b $T/cust.jar -c $T/cust.jar -o /dev/null -d "csrf_token=$tk" $B/logout.php
c=$(curl -s -b $T/cust.jar -o /dev/null -w "%{http_code}" $B/customer/dashboard.php); check "After logout dashboard requires login" "[ $c = 302 ]"
c=$(curl -s -b $T/admin.jar -o /dev/null -w "%{http_code}" $B/logout.php); check "GET logout does nothing (POST + CSRF only)" "[ $c = 302 ] && [ \$(curl -s -b $T/admin.jar -o /dev/null -w '%{http_code}' $B/admin/dashboard.php) = 200 ]"

# ---- Activity log ----
n=$(SQL "SELECT COUNT(*) FROM activity_logs WHERE created_at >= NOW() - INTERVAL 10 MINUTE AND action IN ('login','login_failed','ticket_created','ticket_called','ticket_complete','ticket_skip','access_denied')")
check "Actions recorded in activity log ($n rows)" "[ $n -gt 10 ]"

echo; echo "RESULT: $pass passed, $fail failed"
