<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('leave_approval_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('leave_id')->index();
            $table->unsignedMediumInteger('approver_id')->index();
            $table->unsignedInteger('sequence_number');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('comments')->nullable();
            $table->dateTimeTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->softDeletes();

            $table->foreign('leave_id')
                ->references('id')
                ->on('leaves')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('approver_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index(['leave_id', 'sequence_number']);
            $table->index(['approver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_approval_records');
    }
};
