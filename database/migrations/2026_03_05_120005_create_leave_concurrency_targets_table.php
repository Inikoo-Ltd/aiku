<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('leave_concurrency_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_concurrency_rule_id')->constrained()->cascadeOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('role')->nullable();
            $table->timestampsTz();
            $table->index(['target_type', 'target_id']);
            $table->unique(['leave_concurrency_rule_id', 'target_type', 'target_id'], 'unique_rule_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_concurrency_targets');
    }
};
