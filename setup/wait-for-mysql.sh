#!/bin/bash
set -e

host="$1"
shift
cmd="$@"

until mysqladmin ping -h "$host" --silent; do
  echo "⏳ Waiting for MySQL at $host..."
  sleep 2
done

echo "✅ MySQL is up. Executing: $cmd"
exec $cmd
