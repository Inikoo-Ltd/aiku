<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('margin', 10, 4)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('commission_amount', 16)->default(0);
            $table->decimal('profit_amount', 16)->default(0);
            $table->decimal('margin', 10, 4)->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('commission_amount', 16)->default(0);
            $table->decimal('profit_amount', 16)->default(0);
            $table->decimal('margin', 10, 4)->nullable();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('commission_amount', 16)->default(0);
            $table->decimal('profit_amount', 16)->default(0);
            $table->decimal('margin', 10, 4)->nullable();
        });

        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->decimal('commission_amount', 16)->default(0);
            $table->decimal('margin', 10, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('commission_amount');
            $table->dropColumn('profit_amount');
            $table->dropColumn('margin');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('commission_amount');
            $table->dropColumn('profit_amount');
            $table->dropColumn('margin');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('commission_amount');
            $table->dropColumn('profit_amount');
            $table->dropColumn('margin');
        });

        Schema::table('invoice_transactions', function (Blueprint $table) {
            $table->dropColumn('commission_amount');
            $table->dropColumn('margin');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('margin');
        });
    }
};
