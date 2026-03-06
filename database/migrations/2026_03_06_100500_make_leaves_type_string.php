<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE leaves DROP CONSTRAINT IF EXISTS leaves_type_check');
            DB::statement("ALTER TABLE leaves ALTER COLUMN type TYPE VARCHAR(32) USING type::VARCHAR(32)");

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE leaves MODIFY COLUMN type VARCHAR(32) NOT NULL DEFAULT 'annual'");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE leaves DROP CONSTRAINT IF EXISTS leaves_type_check');
            DB::statement('ALTER TABLE leaves ADD CONSTRAINT leaves_type_check CHECK (type::text = ANY (ARRAY[\'annual\'::character varying, \'medical\'::character varying, \'unpaid\'::character varying, \'halfday-morning\'::character varying, \'halfday-afternoon\'::character varying, \'training\'::character varying, \'leave-of-absence\'::character varying, \'compassionate\'::character varying, \'parental\'::character varying, \'sabbatical\'::character varying]::text[]))');

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE leaves MODIFY COLUMN type ENUM('annual','medical','unpaid','halfday-morning','halfday-afternoon','training','leave-of-absence','compassionate','parental','sabbatical') NOT NULL DEFAULT 'annual'");
        }
    }
};
