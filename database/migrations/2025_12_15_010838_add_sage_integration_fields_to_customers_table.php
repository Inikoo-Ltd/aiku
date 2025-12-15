<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'is_credit_customer')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('is_credit_customer')->default(false)->comment('Sage credit customer flag');
            });
        }

        if (!Schema::hasColumn('customers', 'accounting_reference')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('accounting_reference', 255)->nullable()->default(null)->comment('Sage customer number');
                $table->index('accounting_reference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'is_credit_customer')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('is_credit_customer');
            });
        }

        if (Schema::hasColumn('customers', 'accounting_reference')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex(['accounting_reference']);
                $table->dropColumn('accounting_reference');
            });
        }
    }
};
