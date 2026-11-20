<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('mobile_offline_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid');
            $table->unsignedSmallInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->string('platform', 8);
            $table->string('app_version', 32)->nullable();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestampsTz();
            $table->unique(['device_uuid', 'user_id']);
            $table->index('updated_at');
        });

        Schema::create('mobile_offline_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->unsignedSmallInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('personal_access_token_id')->nullable();
            $table->uuid('device_uuid')->nullable();
            $table->unsignedBigInteger('device_sequence')->nullable();
            $table->string('platform', 8)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->string('operation', 32);
            $table->string('kind', 8)->nullable();
            $table->string('subject_type', 32)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('parent_type', 32)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->jsonb('touched_rows')->nullable();
            $table->jsonb('payload');
            $table->decimal('requested_quantity', 16, 6)->nullable();
            $table->decimal('applied_quantity', 16, 6)->nullable();
            $table->dateTimeTz('seen_at')->nullable();
            $table->dateTimeTz('performed_at')->nullable();
            $table->dateTimeTz('synced_at');
            $table->boolean('counted_since')->default(false);
            $table->string('state', 16);
            $table->string('outcome_reason', 32)->nullable();
            $table->jsonb('server_snapshot')->nullable();
            $table->unsignedSmallInteger('conflicting_user_id')->nullable();
            $table->foreign('conflicting_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('blocked_by_id')->nullable()->index();
            $table->unsignedInteger('staff_task_id')->nullable();
            $table->foreign('staff_task_id')->references('id')->on('staff_tasks')->nullOnDelete();
            $table->string('resolution', 16)->nullable();
            $table->unsignedSmallInteger('resolved_by_user_id')->nullable();
            $table->foreign('resolved_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->dateTimeTz('resolved_at')->nullable();
            $table->dateTimeTz('expired_at')->nullable()->index();
            $table->unsignedBigInteger('rollback_of_id')->nullable();
            $table->unsignedSmallInteger('rolled_back_by_user_id')->nullable();
            $table->foreign('rolled_back_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->dateTimeTz('rolled_back_at')->nullable();
            $table->string('rollback_reason')->nullable();
            $table->timestampsTz();
            $table->unique(['device_uuid', 'user_id', 'device_sequence']);
            $table->index(['device_uuid', 'user_id', 'state']);
            $table->index('synced_at');
            $table->index(['warehouse_id', 'state', 'synced_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['parent_type', 'parent_id']);
        });

        Schema::table('mobile_offline_operations', function (Blueprint $table) {
            $table->foreign('blocked_by_id')->references('id')->on('mobile_offline_operations')->nullOnDelete();
            $table->foreign('rollback_of_id')->references('id')->on('mobile_offline_operations')->nullOnDelete();
        });

        Schema::create('mobile_offline_operation_effects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mobile_offline_operation_id')->index();
            $table->foreign('mobile_offline_operation_id')->references('id')->on('mobile_offline_operations')->cascadeOnDelete();
            $table->string('model_type', 32);
            $table->unsignedBigInteger('model_id');
            $table->string('effect', 8);
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->dateTimeTz('created_at')->nullable();
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_offline_operation_effects');
        Schema::dropIfExists('mobile_offline_operations');
        Schema::dropIfExists('mobile_offline_devices');
    }
};
