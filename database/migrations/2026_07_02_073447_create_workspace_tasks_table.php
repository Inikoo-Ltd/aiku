<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('workspace_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigner_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('workspace_tasks');
    }
};
