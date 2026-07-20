#!/usr/bin/env bash

set -euo pipefail

if [[ $# -ne 2 || ! $1 =~ ^-?[0-9]+$ || ! $2 =~ ^-?[0-9]+$ ]]; then
    echo "Uso: $0 X Y" >&2
    echo "Ejemplo: $0 -25 40" >&2
    exit 1
fi

x=$1
y=$2

if (( x < -100 || x > 100 || y < -100 || y > 100 )); then
    echo "Error: X e Y deben estar entre -100 y 100." >&2
    exit 1
fi

script_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
project_dir=$(cd -- "$script_dir/.." && pwd)
env_file="$project_dir/.env"

if [[ ! -f $env_file ]]; then
    echo "Error: no se encontró $env_file." >&2
    exit 1
fi

db_password=${TRAVIAN_DB_PASSWORD:-$(sed -n 's/^MARIADB_PASSWORD=//p' "$env_file" | tail -n 1)}

if [[ -z $db_password ]]; then
    echo "Error: MARIADB_PASSWORD no está definido en $env_file." >&2
    exit 1
fi

cd "$project_dir"

{
    printf 'SET @x = %d;\nSET @y = %d;\n' "$x" "$y"
    sed '/^SET @x = /d; /^SET @y = /d' "$script_dir/find_best_15c.sql"
} | docker compose exec -T db \
    mariadb -utravian -p"$db_password" travian_t4
