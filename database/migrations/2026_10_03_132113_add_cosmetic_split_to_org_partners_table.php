<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_partners', function (Blueprint $table) {
            $table->boolean('split_cosmetics')->default(false);
            $table->foreignId('cosmetic_goods_out_location_id')->nullable()->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('org_partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cosmetic_goods_out_location_id');
            $table->dropColumn('split_cosmetics');
        });
    }
};
