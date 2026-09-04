<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\Helpers\Ticket\TicketStatusEnum;
use App\Enums\Helpers\Ticket\TicketTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('group_id')->index();
            $table->foreign('group_id')->references('id')->on('groups');
            $table->unsignedSmallInteger('organisation_id')->nullable()->index();
            $table->foreign('organisation_id')->references('id')->on('organisations');
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->string('type')->index();
            $table->unsignedInteger('number');
            $table->string('reference')->unique();
            $table->string('status')->index()->default(TicketStatusEnum::OPEN->value);
            $table->string('priority')->index()->default(ChatPriorityEnum::NORMAL->value);
            $table->string('subject');
            $table->text('description')->nullable();
            $table->nullableMorphs('reporter');
            $table->unsignedInteger('assignee_id')->nullable()->index();
            $table->foreign('assignee_id')->references('id')->on('users');
            $table->nullableMorphs('model');
            $table->jsonb('data');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ticket_id')->index();
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            $table->nullableMorphs('author');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestampsTz();
        });

        foreach (TicketTypeEnum::cases() as $type) {
            DB::statement('CREATE SEQUENCE '.$type->sequence());
        }
    }

    public function down(): void
    {
        foreach (TicketTypeEnum::cases() as $type) {
            DB::statement('DROP SEQUENCE IF EXISTS '.$type->sequence());
        }
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
    }
};
