#!/bin/sh
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" <<-EOSQL
    CREATE TABLE IF NOT EXISTS "benchmark" (
        "id" SERIAL PRIMARY KEY,
        "data" text NOT NULL,
        "created_at" timestamp without time zone DEFAULT now()
    );
EOSQL
