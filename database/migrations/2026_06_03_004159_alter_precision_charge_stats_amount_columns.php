<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('charge_stats', function (Blueprint $table) {
            $table->decimal('amount', 20, 2)->change();
            $table->decimal('org_amount', 20, 2)->change();
            $table->decimal('grp_amount', 20, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('charge_stats', function (Blueprint $table) {
            $table->decimal('amount', 8, 2)->change();
            $table->decimal('org_amount', 8, 2)->change();
            $table->decimal('grp_amount', 8, 2)->change();
        });
    }
};
