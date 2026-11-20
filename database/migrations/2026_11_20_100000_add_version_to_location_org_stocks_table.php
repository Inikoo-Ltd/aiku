<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('location_org_stocks', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1);
        });

        DB::statement(<<<'SQL'
            CREATE FUNCTION location_org_stocks_bump_version() RETURNS trigger AS $$
            BEGIN
                NEW.version := OLD.version;
                IF ROW(NEW.location_id, NEW.quantity, NEW.type, NEW.picking_priority, NEW.settings, NEW.audited_at,
                       NEW.default_wholesale_picking_location, NEW.default_dropshipping_picking_location)
                   IS DISTINCT FROM ROW(OLD.location_id, OLD.quantity, OLD.type, OLD.picking_priority, OLD.settings, OLD.audited_at,
                       OLD.default_wholesale_picking_location, OLD.default_dropshipping_picking_location) THEN
                    NEW.version := OLD.version + 1;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement('CREATE TRIGGER location_org_stocks_bump_version BEFORE UPDATE ON location_org_stocks FOR EACH ROW EXECUTE FUNCTION location_org_stocks_bump_version()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS location_org_stocks_bump_version ON location_org_stocks');
        DB::statement('DROP FUNCTION IF EXISTS location_org_stocks_bump_version()');

        Schema::table('location_org_stocks', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
