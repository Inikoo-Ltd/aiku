<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('test_email_recipient', function (Blueprint $table) {
            $table->id();
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('test_email_recipient');
    }
};
