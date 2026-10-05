<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 18:17:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ci_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('github_run_id')->unique();
            $table->unsignedSmallInteger('run_attempt')->default(1);
            $table->string('workflow')->nullable()->index();
            $table->string('branch')->nullable();
            $table->string('head_sha', 40)->nullable();
            $table->text('head_message')->nullable();
            $table->string('actor')->nullable();
            $table->string('status')->nullable();
            $table->string('conclusion')->nullable();
            $table->string('html_url')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('jobs')->default('{}');
            $table->jsonb('deploy_tasks')->default('[]');
            $table->timestampsTz();
            $table->index(['workflow', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_runs');
    }
};
