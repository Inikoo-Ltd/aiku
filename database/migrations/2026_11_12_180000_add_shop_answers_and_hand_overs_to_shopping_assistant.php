<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('shopping_assistant_messages', function (Blueprint $table) {
            $table->string('answer_kind', 32)->nullable()->index()->comment('products, shop_answer, family_description, tool, question, offer_person, off_topic, other; null on customer messages and on answers from before INI-245');
            $table->string('source_kind', 32)->nullable()->comment('kind of the first knowledge entry a shop_answer rests on: policy, shop_fact, guide, note, verified, learned');
            $table->jsonb('sources')->nullable()->comment('knowledge entries the answer cites, copied at answer time because their ids change: id, kind, source_type, source_id, title, url');
            $table->string('hand_over_offer', 32)->nullable()->index()->comment('why a person was offered under this answer: not_sure, order_question, asked_for_person');
            $table->string('staff_rating', 16)->nullable()->comment('wrong, set by the chat team from the hand-over');
            $table->unsignedSmallInteger('staff_rated_by_user_id')->nullable();
            $table->timestampTz('staff_rated_at')->nullable();
        });

        Schema::create('shopping_assistant_hand_overs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopping_assistant_conversation_id')->index();
            $table->foreign('shopping_assistant_conversation_id')->references('id')->on('shopping_assistant_conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_message_id')->nullable()->unique()->comment('the question passed to the team; one hand-over per question');
            $table->unsignedBigInteger('offer_message_id')->nullable()->comment('the assistant answer that offered a person, null when the customer used the Talk to a person link');
            $table->unsignedInteger('chat_session_id')->nullable()->index();
            $table->string('reason', 32)->index()->comment('not_sure, order_question, asked_for_person');
            $table->string('route', 16)->comment('live_chat, email_chat, web_chat');
            $table->boolean('in_hours')->comment('inside the shop chat hours when handed over');
            $table->boolean('agent_available')->comment('an agent of the shop was available when handed over');
            $table->text('question')->nullable();
            $table->timestampTz('created_at')->index();
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('shopping_assistant_conversation_id')->nullable()->index()->comment('set on a chat the AI shopping assistant handed over; such chats stay out of the chat coverage and reply time figures');
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropColumn('shopping_assistant_conversation_id');
        });

        Schema::dropIfExists('shopping_assistant_hand_overs');

        Schema::table('shopping_assistant_messages', function (Blueprint $table) {
            $table->dropColumn(['answer_kind', 'source_kind', 'sources', 'hand_over_offer', 'staff_rating', 'staff_rated_by_user_id', 'staff_rated_at']);
        });
    }
};
