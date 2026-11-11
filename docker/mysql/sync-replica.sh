#!/bin/sh
# Rebuilds the replica from a consistent dump of the primary and starts GTID replication from there.
# Safe to run again: the replica is wiped first. Run through `make db-replica-sync`.
set -eu

compose="${COMPOSE:-docker compose}"
root_password="${DB_ROOT_PASSWORD:-root}"
database="${DB_DATABASE:-travel_request}"
app_user="${DB_USERNAME:-travel_request}"
app_password="${DB_PASSWORD:-secret}"
replication_password="${DB_REPLICATION_PASSWORD:-replication-secret}"

primary() { $compose exec -T mysql mysql -uroot -p"$root_password" "$@"; }
replica() { $compose exec -T mysql-replica mysql -uroot -p"$root_password" "$@"; }

primary -e "CREATE USER IF NOT EXISTS 'replicator'@'%' IDENTIFIED BY '$replication_password';
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'%';
GRANT REPLICATION CLIENT ON *.* TO '$app_user'@'%';"

replica -e "STOP REPLICA; RESET REPLICA ALL;
SET GLOBAL super_read_only = OFF; SET GLOBAL read_only = OFF;
RESET BINARY LOGS AND GTIDS;
DROP DATABASE IF EXISTS \`$database\`;"

$compose exec -T mysql mysqldump -uroot -p"$root_password" --databases "$database" \
    --single-transaction --set-gtid-purged=ON --triggers --routines --events \
    | $compose exec -T mysql-replica mysql -uroot -p"$root_password"

replica -e "SET SQL_LOG_BIN = 0;
CREATE USER IF NOT EXISTS '$app_user'@'%' IDENTIFIED BY '$app_password';
GRANT SELECT ON \`$database\`.* TO '$app_user'@'%';
GRANT REPLICATION CLIENT ON *.* TO '$app_user'@'%';
SET SQL_LOG_BIN = 1;
CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'mysql', SOURCE_PORT = 3306, SOURCE_USER = 'replicator',
    SOURCE_PASSWORD = '$replication_password', SOURCE_AUTO_POSITION = 1, GET_SOURCE_PUBLIC_KEY = 1;
START REPLICA;
SET PERSIST read_only = ON; SET PERSIST super_read_only = ON;"

echo "Replica synced from the primary and replicating $database."
