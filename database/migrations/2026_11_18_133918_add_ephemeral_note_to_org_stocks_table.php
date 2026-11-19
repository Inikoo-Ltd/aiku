<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->string('ephemeral_note', 500)->nullable();
            $table->unsignedInteger('ephemeral_note_user_id')->nullable();
            $table->dateTimeTz('ephemeral_note_set_at')->nullable();
            $table->dateTimeTz('ephemeral_note_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropColumn(['ephemeral_note', 'ephemeral_note_user_id', 'ephemeral_note_set_at', 'ephemeral_note_expires_at']);
        });
    }
};
