<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_task_collaborators', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('staff_task_id');
            $table->foreign('staff_task_id')->references('id')->on('staff_tasks')->onDelete('cascade');
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedInteger('added_by_id')->nullable();
            $table->foreign('added_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['staff_task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_task_collaborators');
    }
};
