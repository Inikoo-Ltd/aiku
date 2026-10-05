<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('customer_has_packagings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->unsignedInteger('packaging_id')->nullable()->index();
            $table->foreign('packaging_id')->references('id')->on('packagings');
            $table->decimal('price', 12, 2)->nullable()->comment('Customer specific price, null means use packaging price');
            $table->text('personalised_message')->nullable();
            $table->jsonb('data');
            $table->timestampsTz();
            $table->unique(['customer_id', 'packaging_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_has_packagings');
    }
};
