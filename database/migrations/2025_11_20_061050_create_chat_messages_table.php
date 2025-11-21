<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 21 Nov 2025 11:12:23 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {

            $table->id();


            $table->unsignedInteger('chat_session_id')->index()->nullable();
            $table->foreign('chat_session_id')->references('id')->on('chat_sessions')->nullOnDelete();

            $table->string('message_type')->index()->default(ChatMessageTypeEnum::TEXT->value);

            $table->string('sender_type');
            $table->unsignedInteger('sender_id')->nullable();
            // Proper composite index for polymorphic sender reference
            $table->index(['sender_type', 'sender_id'], 'chat_messages_sender_idx');

            $table->text('message_text')->nullable();


            $table->unsignedInteger('media_id')->index()->nullable();
            $table->foreign('media_id')->references('id')->on('media')->nullOnDelete();


            $table->boolean('is_read')->index()->default(false);
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();


            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
