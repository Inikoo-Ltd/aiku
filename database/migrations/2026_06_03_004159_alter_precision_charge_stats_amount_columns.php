<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('charge_stats', function (Blueprint $table) {
            $table->decimal('amount', 20)->default(0)->change();
            $table->decimal('org_amount', 20)->default(0)->change();
            $table->decimal('grp_amount', 20)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('charge_stats', function (Blueprint $table) {
            $table->decimal('amount')->change();
            $table->decimal('org_amount')->change();
            $table->decimal('grp_amount')->change();
        });
    }
};
