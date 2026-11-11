-- Decision-to-notification latency of the load test users, from the decision (the request's last update)
-- to the notification row. Both columns have one-second precision.
WITH latencies AS (
    SELECT TIMESTAMPDIFF(SECOND, travel_requests.updated_at, notifications.created_at) AS seconds
    FROM notifications
    JOIN users ON users.id = notifications.notifiable_id
    JOIN travel_requests
        ON travel_requests.id = UUID_TO_BIN(JSON_UNQUOTE(JSON_EXTRACT(notifications.data, '$.travel_request_id')))
    WHERE users.email LIKE 'load-%'
),
ranked AS (
    SELECT seconds, CUME_DIST() OVER (ORDER BY seconds) AS cumulative_share
    FROM latencies
)
SELECT
    COUNT(*) AS deliveries,
    MIN(CASE WHEN cumulative_share >= 0.50 THEN seconds END) AS p50_seconds,
    MIN(CASE WHEN cumulative_share >= 0.95 THEN seconds END) AS p95_seconds,
    MIN(CASE WHEN cumulative_share >= 0.99 THEN seconds END) AS p99_seconds,
    MAX(seconds) AS max_seconds
FROM ranked;
