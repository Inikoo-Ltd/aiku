<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('packaging_id')->nullable()->index();
            $table->foreign('packaging_id')->references('id')->on('packagings');
            $table->text('personalised_message')->nullable();
            $table->jsonb('insert_types')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['packaging_id']);
            $table->dropColumn(['packaging_id', 'personalised_message', 'insert_types']);
        });
    }
};
