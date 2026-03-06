<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('master_shops', function (Blueprint $table) {
            $table->float('price_rrp_warning_ratio')->default(0);
        });
    }


    public function down(): void
    {
        Schema::table('master_shops', function (Blueprint $table) {
            $table->dropColumn(['price_rrp_warning_ratio']);
        });
    }
};
