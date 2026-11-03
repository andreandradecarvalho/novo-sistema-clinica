#!/bin/sh
set -eu
# This script runs only on the new, isolated local Compose volume.
case "$MARIADB_USER" in *[!a-zA-Z0-9_]*) exit 1;; esac
export MYSQL_PWD="$MARIADB_ROOT_PASSWORD"
mariadb --user=root <<SQL
CREATE DATABASE IF NOT EXISTS novo_sistema_clinica_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON novo_sistema_clinica_testing.* TO '$MARIADB_USER'@'%';
SQL
