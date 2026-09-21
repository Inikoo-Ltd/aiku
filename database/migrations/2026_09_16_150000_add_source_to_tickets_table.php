<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('source_type')->nullable();
            $table->unsignedInteger('source_id')->nullable();
            $table->index(['source_type', 'source_id']);
            $table->string('source_channel', 16)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropIndex(['source_channel']);
            $table->dropColumn(['source_type', 'source_id', 'source_channel']);
        });
    }
};
