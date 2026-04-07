<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('leave_approvers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('User name diambil dari users table');
            $table->unsignedMediumInteger('user_id')->index();
            $table->unsignedInteger('sequence_number')->comment('Level approval: 1, 2, 3');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedMediumInteger('organisation_id')->index();
            $table->timestampsTz();
            $table->softDeletes();

            $table->foreign('organisation_id')
                ->references('id')
                ->on('organisations')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index(['organisation_id', 'sequence_number', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_approvers');
    }
};
