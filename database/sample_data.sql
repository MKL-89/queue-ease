-- =====================================================================
--  QueueEase - OPTIONAL DEMONSTRATION DATA
--  Adds one week of past tickets so the dashboard charts and reports
--  have something to show during a demonstration.
--  Import AFTER dqms_db.sql. These rows are clearly marked as demo
--  data (reference codes start with "DEMO-") and are NOT real results.
--  Remove them at any time with:
--      DELETE FROM queue_tickets WHERE reference_code LIKE 'DEMO-%';
-- =====================================================================

USE dqms_db;

INSERT INTO customers (full_name, phone, email, is_guest)
VALUES ('Demo Walk-in Customer', '0799000000', NULL, 1);
SET @demo_customer := LAST_INSERT_ID();

INSERT INTO queue_tickets
    (ticket_number, reference_code, service_id, customer_id, counter_id, served_by,
     ticket_date, sequence_no, status, called_at, service_started_at, completed_at,
     cancelled_at, created_at)
SELECT
    CONCAT(x.prefix, LPAD(x.kn, 3, '0')),
    CONCAT('DEMO-', x.sid, x.dn, LPAD(x.kn, 3, '0')),
    x.sid,
    @demo_customer,
    IF(x.st = 'cancelled', NULL, x.counter_id),
    IF(x.st = 'cancelled', NULL, x.staff_id),
    DATE(x.created),
    x.kn,
    x.st,
    IF(x.st = 'cancelled', NULL, x.created + INTERVAL x.wait_min MINUTE),
    IF(x.st = 'served', x.created + INTERVAL (x.wait_min + 1) MINUTE, NULL),
    IF(x.st = 'served', x.created + INTERVAL (x.wait_min + 1 + x.avg_min + (x.kn % 4) - 1) MINUTE, NULL),
    IF(x.st = 'cancelled', x.created + INTERVAL 3 MINUTE, NULL),
    x.created
FROM (
    SELECT s.id AS sid, s.prefix, s.avg_service_minutes AS avg_min,
           d.n AS dn, k.n AS kn,
           (SELECT MIN(c.id) FROM counters c WHERE c.service_id = s.id) AS counter_id,
           (SELECT sc.user_id FROM staff_counters sc
             WHERE sc.counter_id = (SELECT MIN(c.id) FROM counters c WHERE c.service_id = s.id)
             ORDER BY sc.id LIMIT 1) AS staff_id,
           CASE WHEN (k.n * d.n) % 13 = 0 THEN 'skipped'
                WHEN (k.n + d.n + s.id) % 11 = 0 THEN 'cancelled'
                ELSE 'served' END AS st,
           4 + (k.n * 7 + d.n) % 18 AS wait_min,
           TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d.n DAY), '08:00:00')
               + INTERVAL (k.n * (10 + s.id * 3)) MINUTE AS created
    FROM services s
    CROSS JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
                UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7) d
    CROSS JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
                UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
                UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
                UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18) k
    WHERE k.n <= 8 + ((d.n * 5 + s.id * 3) % 11)
) x;

-- Demo star ratings for about two thirds of the served demo tickets
INSERT INTO service_feedback (ticket_id, rating, comment, created_at)
SELECT t.id,
       CASE WHEN t.id % 19 = 0 THEN 2 WHEN t.id % 7 = 0 THEN 3 WHEN t.id % 3 = 0 THEN 4 ELSE 5 END,
       CASE WHEN t.id % 10 = 0 THEN 'Quick and friendly service' ELSE NULL END,
       t.completed_at + INTERVAL 2 MINUTE
  FROM queue_tickets t
 WHERE t.reference_code LIKE 'DEMO-%' AND t.status = 'served' AND t.id % 3 <> 1;
