<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            if (!Schema::hasColumn('pallets', 'is_virtual')) {
                $table->boolean('is_virtual')->default(false)->index();
            }
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS pallets_virtual_customer_location_unique ON pallets (fulfilment_customer_id, location_id) WHERE is_virtual AND deleted_at IS NULL');

        if (!$this->hasLocationConstraint()) {
            DB::statement('ALTER TABLE pallets ADD CONSTRAINT pallets_virtual_needs_location CHECK (NOT is_virtual OR location_id IS NOT NULL)');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if ($this->hasLocationConstraint()) {
            DB::statement('ALTER TABLE pallets DROP CONSTRAINT pallets_virtual_needs_location');
        }

        DB::statement('DROP INDEX IF EXISTS pallets_virtual_customer_location_unique');

        Schema::table('pallets', function (Blueprint $table) {
            if (Schema::hasColumn('pallets', 'is_virtual')) {
                $table->dropColumn('is_virtual');
            }
        });
    }

    private function hasLocationConstraint(): bool
    {
        return DB::selectOne("SELECT 1 FROM pg_constraint WHERE conname = 'pallets_virtual_needs_location'") !== null;
    }
};
