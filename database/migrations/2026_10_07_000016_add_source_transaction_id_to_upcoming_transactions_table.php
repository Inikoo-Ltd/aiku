<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('upcoming_transactions', function (Blueprint $table) {
            $table->unsignedInteger('source_transaction_id')->nullable()->index();
            $table->foreign('source_transaction_id')->references('id')->on('transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('upcoming_transactions', function (Blueprint $table) {
            $table->dropForeign(['source_transaction_id']);
            $table->dropColumn('source_transaction_id');
        });
    }
};
