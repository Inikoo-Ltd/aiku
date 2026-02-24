<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('email_bulk_run_recipients', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('email_bulk_run_id')->index();
            $table->foreign('email_bulk_run_id')->references('id')->on('email_bulk_runs')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('dispatched_email_id')->index();
            $table->foreign('dispatched_email_id')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
            $table->string('recipient_type');
            $table->unsignedInteger('recipient_id');
            $table->unsignedInteger('channel')->index();
            $table->timestampsTz();
            $table->index(['recipient_type', 'recipient_id', 'email_bulk_run_id']);
            $table->unique(['email_bulk_run_id', 'dispatched_email_id']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('email_bulk_run_recipients');
    }
};
