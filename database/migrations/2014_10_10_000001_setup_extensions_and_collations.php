<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Jun 2023 03:22:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration {
    public function up(): void
    {
        DB::statement("create schema extensions;");
        DB::statement("grant usage on schema extensions to public;");
        DB::statement('CREATE EXTENSION pg_trgm;');
        DB::statement("grant execute on all functions in schema extensions to public;");
        DB::statement("alter default privileges in schema extensions grant execute on functions to public;");
        DB::statement("alter default privileges in schema extensions grant usage on types to public;");

        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent schema extensions;');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm schema extensions;');
        DB::statement('CREATE COLLATION und_ci_ai (PROVIDER = icu,DETERMINISTIC = FALSE,LOCALE = "und-u-kn-true-ks-level1");');
        DB::statement('CREATE COLLATION und_ns_ci (PROVIDER = icu,DETERMINISTIC = FALSE,LOCALE = "und-u-kn-true-ks-level2");');
        DB::statement('CREATE COLLATION und_ci (PROVIDER = icu,DETERMINISTIC = FALSE,LOCALE = "und-u-ks-level2");');
        DB::statement('CREATE COLLATION und_ns (PROVIDER = icu,LOCALE = "und-u-kn-true");');


        DB::statement(
            'CREATE OR REPLACE FUNCTION extensions.immutable_unaccent(regdictionary, text)
  RETURNS text
  LANGUAGE c IMMUTABLE PARALLEL SAFE STRICT AS
\'$libdir/unaccent\', \'unaccent_dict\';'
        );

        DB::statement(
            "CREATE OR REPLACE FUNCTION extensions.remove_accents(text)
  RETURNS text
  LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
  BEGIN ATOMIC
SELECT extensions.immutable_unaccent(regdictionary 'extensions.unaccent', $1);
END;"
        );

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_stat_statements');
    }

    public function down(): void {}
};
