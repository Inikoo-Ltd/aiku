<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('restricted_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('holiday_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('strictness', 32)->default('block');
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_superuser_override')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['organisation_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restricted_periods');
    }
};
