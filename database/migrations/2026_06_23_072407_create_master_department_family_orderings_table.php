<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('master_department_family_orderings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('master_department_id')->index();
            $table->foreign('master_department_id')->references('id')->on('master_product_categories');
            $table->unsignedBigInteger('master_family_id')->index();
            $table->foreign('master_family_id')->references('id')->on('master_product_categories');
            $table->unsignedInteger('position');
            $table->unique(['master_department_id', 'master_family_id']);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_department_family_orderings');
    }
};
