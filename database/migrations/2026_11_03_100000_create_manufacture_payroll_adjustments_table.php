<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 10:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('manufacture_payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('production_id')->index();
            $table->foreign('production_id')->references('id')->on('productions');
            $table->unsignedSmallInteger('user_id')->nullable()->index();
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->string('change');
            $table->date('pay_week')->index();
            $table->jsonb('before');
            $table->jsonb('after');
            $table->text('reason');
            $table->decimal('hours_delta', 10, 2)->default(0);
            $table->decimal('amount_delta', 12, 2)->default(0);
            $table->unsignedSmallInteger('changed_by_user_id')->nullable();
            $table->foreign('changed_by_user_id')->references('id')->on('users');
            $table->boolean('needs_approval')->default(false)->index();
            $table->unsignedSmallInteger('approved_by_user_id')->nullable();
            $table->foreign('approved_by_user_id')->references('id')->on('users');
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacture_payroll_adjustments');
    }
};
