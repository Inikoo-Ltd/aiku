<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('invoice_e_invoice_transmissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->unsignedInteger('invoice_id')->index();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->string('provider');
            $table->uuid('idempotency_key')->unique();
            $table->string('tag', 50)->index();
            $table->string('transaction_id')->nullable()->index();
            $table->string('checksum')->nullable();
            $table->string('status')->index();
            $table->jsonb('error')->nullable();
            $table->text('xml')->nullable();
            $table->timestampTz('first_attempted_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('status_checked_at')->nullable();
            $table->timestampTz('final_at')->nullable();
            $table->string('alerted_status')->nullable();
            $table->timestampTz('alerted_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('e_invoice_api_calls', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('method', 8);
            $table->string('path');
            $table->jsonb('request')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestampTz('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('e_invoice_api_calls');
        Schema::dropIfExists('invoice_e_invoice_transmissions');
    }
};
