<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('staff_tasks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedInteger('number');
            $table->string('reference')->unique();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->unsignedInteger('requester_id')->index();
            $table->foreign('requester_id')->references('id')->on('users');
            $table->string('department')->nullable();
            $table->unsignedInteger('assignee_id')->nullable();
            $table->foreign('assignee_id')->references('id')->on('users');
            $table->string('status')->default(StaffTaskStatusEnum::TODO->value);
            $table->string('priority')->default(ChatPriorityEnum::NORMAL->value);
            $table->date('due_at')->nullable();
            $table->nullableMorphs('model');
            $table->unsignedInteger('staff_conversation_id')->nullable();
            $table->foreign('staff_conversation_id')->references('id')->on('staff_conversations')->nullOnDelete();
            $table->jsonb('data');
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['assignee_id', 'status']);
            $table->index(['department', 'status']);
            $table->index(['requester_id', 'status']);
        });

        DB::statement('CREATE SEQUENCE staff_task_number_seq');
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS staff_task_number_seq');
        Schema::dropIfExists('staff_tasks');
    }
};
