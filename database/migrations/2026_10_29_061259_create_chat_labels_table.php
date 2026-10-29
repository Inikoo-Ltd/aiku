<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('colour', 7);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestampsTz();
            $table->unique(['organisation_id', 'name']);
        });

        Schema::create('chat_label_chat_session', function (Blueprint $table) {
            $table->unsignedBigInteger('chat_label_id');
            $table->foreign('chat_label_id')->references('id')->on('chat_labels')->cascadeOnDelete();
            $table->unsignedInteger('chat_session_id')->index();
            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->cascadeOnDelete();
            $table->timestampTz('created_at')->nullable();
            $table->primary(['chat_label_id', 'chat_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_label_chat_session');
        Schema::dropIfExists('chat_labels');
    }
};
