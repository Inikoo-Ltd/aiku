<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('product_development_samples', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('product_development_id')->index();
            $table->foreign('product_development_id')->references('id')->on('product_developments')->cascadeOnDelete();
            $table->unsignedInteger('product_development_item_id')->index();
            $table->foreign('product_development_item_id')->references('id')->on('product_development_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('status')->default('in_progress')->index();
            $table->jsonb('recipe')->nullable();
            $table->decimal('batch_size', 12, 3)->nullable();
            $table->string('batch_unit')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('staff_task_id')->nullable()->index();
            $table->foreign('staff_task_id')->references('id')->on('staff_tasks')->nullOnDelete();
            $table->unsignedInteger('made_by_id')->nullable();
            $table->foreign('made_by_id')->references('id')->on('users');
            $table->timestampTz('ready_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->unsignedInteger('reviewed_by_id')->nullable();
            $table->foreign('reviewed_by_id')->references('id')->on('users');
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['product_development_item_id', 'number']);
        });

        if (!Schema::hasColumn('product_development_stats', 'number_required_missing_production')) {
            Schema::table('product_development_stats', function (Blueprint $table) {
                $table->unsignedSmallInteger('number_required_missing_production')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_development_samples');
    }
};
