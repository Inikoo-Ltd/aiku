<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_has_dispatched_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('model_id');
            $table->string('model_type');
            $table->unsignedBigInteger('dispatched_email_id')->index();
            $table->foreign('dispatched_email_id')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedSmallInteger('outbox_id')->index();
            $table->foreign('outbox_id')->references('id')->on('outboxes')->onUpdate('cascade')->onDelete('cascade');
            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->unique();
            $table->index(['model_id', 'model_type']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_dispatched_emails');
    }
};
