<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Helpers\Ticket\TicketProjectStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ticket_projects', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->index()->default(TicketProjectStatusEnum::ACTIVE->value);
            $table->unsignedInteger('owner_id')->nullable()->index();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->date('start_date');
            $table->date('target_date')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('ticket_project_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('ticket_project_id');
            $table->foreign('ticket_project_id')->references('id')->on('ticket_projects')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestampsTz();
            $table->unique(['ticket_project_id', 'user_id']);
        });

        Schema::create('ticket_project_milestones', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ticket_project_id')->index();
            $table->foreign('ticket_project_id')->references('id')->on('ticket_projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestampTz('done_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
        });

        Schema::create('ticket_project_updates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ticket_project_id')->index();
            $table->foreign('ticket_project_id')->references('id')->on('ticket_projects')->cascadeOnDelete();
            $table->unsignedInteger('author_id')->nullable();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
            $table->string('health')->nullable();
            $table->text('body');
            $table->timestampsTz();
        });

        foreach (['tickets', 'staff_tasks'] as $workTable) {
            Schema::table($workTable, function (Blueprint $table) {
                $table->unsignedInteger('ticket_project_id')->nullable()->index();
                $table->foreign('ticket_project_id')->references('id')->on('ticket_projects')->nullOnDelete();
                $table->unsignedInteger('ticket_project_milestone_id')->nullable()->index();
                $table->foreign('ticket_project_milestone_id')->references('id')->on('ticket_project_milestones')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['tickets', 'staff_tasks'] as $workTable) {
            Schema::table($workTable, function (Blueprint $table) {
                $table->dropForeign(['ticket_project_milestone_id']);
                $table->dropForeign(['ticket_project_id']);
                $table->dropColumn(['ticket_project_milestone_id', 'ticket_project_id']);
            });
        }
        Schema::dropIfExists('ticket_project_updates');
        Schema::dropIfExists('ticket_project_milestones');
        Schema::dropIfExists('ticket_project_members');
        Schema::dropIfExists('ticket_projects');
    }
};
