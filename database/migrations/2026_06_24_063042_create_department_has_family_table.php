<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('department_has_family', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('department_id')->index();
            $table->foreign('department_id')->references('id')->on('product_categories');
            $table->unsignedBigInteger('family_id')->index();
            $table->foreign('family_id')->references('id')->on('product_categories');
            $table->unsignedInteger('position');
            $table->unique(['department_id', 'family_id']);
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('department_has_family');
    }
};
