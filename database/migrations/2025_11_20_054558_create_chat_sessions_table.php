<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Nov 2025 10:35:14 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSessionClosedByTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
           $table->increments('id');
            $table->unsignedInteger('web_user_id')->index()->nullable();
            $table->foreign('web_user_id')->references('id')->on('web_users')->onUpdate('cascade')->onDelete('cascade');

            $table->ulid()->unique();
            $table->string('status')->default(ChatSessionStatusEnum::ACTIVE->value);
            $table->string('guest_identifier', 255)->nullable()->comment('Random alias we use to identify the guest');;
            $table->string('ai_model_version', 50)->nullable();

            $table->unsignedSmallInteger('language_id')->index()->default(68);
            $table->foreign('language_id')->references('id')->on('languages');

            $table->string('priority')->index()->default( ChatPriorityEnum::NORMAL->value);

            $table->unsignedSmallInteger('rating')->nullable();

            $table->string('closed_by')->nullable();

            $table->timestampTz('last_visitor_message_at')->nullable();
            $table->timestampTz('last_agent_message_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();


        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
