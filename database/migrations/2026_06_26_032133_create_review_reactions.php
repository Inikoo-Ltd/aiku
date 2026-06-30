<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('review_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_id')->index();
            $table->foreign('review_id')->references('id')->on('reviews')->nullOnDelete();
            $table->string('target')->comment('Would be Enum (Review / Reply)');
            $table->unsignedBigInteger('customer_id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->string('type')->comment('Would be Enum (Like / Dislike)');
            $table->unique(['review_id', 'target', 'customer_id', 'type']);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('review_reactions');
    }
};
