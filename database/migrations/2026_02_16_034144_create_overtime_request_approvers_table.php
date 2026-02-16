<?php

/*
 * Author: Your Name <you@example.com>
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('overtime_request_approvers', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('overtime_request_id')->index();
            $table->foreign('overtime_request_id')
                ->references('id')
                ->on('overtime_requests')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unsignedSmallInteger('approver_employee_id')->index();
            $table->foreign('approver_employee_id')
                ->references('id')
                ->on('employees')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->string('role', 32)->default('manager');

            $table->string('decision', 32)->default('pending');
            $table->text('decision_note')->nullable();
            $table->dateTimeTz('decided_at')->nullable();

            $table->timestampsTz();

            $table->index(['approver_employee_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_request_approvers');
    }
};
