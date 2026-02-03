<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {

            $table->decimal('latitude', 10, 7)->nullable()->after('country_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            $table->index(['latitude', 'longitude'], 'addresses_coords_idx');
            $table->index(['country_code', 'latitude', 'longitude'], 'addresses_country_coords_idx');
            $table->index(['locality', 'latitude', 'longitude'], 'addresses_locality_coords_idx');

            $table->index(['latitude'], 'addresses_lat_idx');
            $table->index(['longitude'], 'addresses_lng_idx');
        });


        DB::statement("COMMENT ON COLUMN addresses.latitude IS 'Latitude coordinate (WGS84)'");
        DB::statement("COMMENT ON COLUMN addresses.longitude IS 'Longitude coordinate (WGS84)'");

    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addresses_coords_idx');
            $table->dropIndex('addresses_country_coords_idx');
            $table->dropIndex('addresses_locality_coords_idx');
            $table->dropIndex('addresses_lat_idx');
            $table->dropIndex('addresses_lng_idx');
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
