<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->decimal('grp_exchange', 22, 10)->nullable()->change();
            $table->decimal('org_exchange', 22, 10)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->decimal('grp_exchange', 16, 4)->nullable()->change();
            $table->decimal('org_exchange', 16, 4)->nullable()->change();
        });
    }
};
