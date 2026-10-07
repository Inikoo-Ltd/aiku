<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('website_dialog_dismissals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_dialog_id');
            $table->foreign('website_dialog_id')->references('id')->on('website_dialogs')->cascadeOnDelete();
            $table->unsignedInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->string('version')->nullable();
            $table->timestampsTz();
            $table->unique(['website_dialog_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_dialog_dismissals');
    }
};
