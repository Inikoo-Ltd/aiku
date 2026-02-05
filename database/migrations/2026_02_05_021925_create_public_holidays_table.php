<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->bigIncrements('id');
            $table->string('holidayable_type', 50)->nullable();
            $table->unsignedBigInteger('holidayable_id')->nullable();
            $table->string('name', 100);
            $table->date('holiday_date');
            $table->boolean('is_recurring')->default(false);
            $table->index(['holidayable_type', 'holidayable_id'], 'idx_holidayable');
            $table->index('holiday_date', 'idx_holiday_date');

            $table->unique(
                ['holidayable_type', 'holidayable_id', 'holiday_date'],
                'uniq_holiday_scope_date'
            );
            $table->timestampsTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
    }
};
