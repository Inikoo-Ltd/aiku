<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('holiday_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->nullable()->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained()->nullable()->cascadeOnDelete();
            $table->string('label');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['organisation_id', 'is_active']);
            $table->index(['organisation_id', 'start_date', 'end_date']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('holiday_years');
    }
};
