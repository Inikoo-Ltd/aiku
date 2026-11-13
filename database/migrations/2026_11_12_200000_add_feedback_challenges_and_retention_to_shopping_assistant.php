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
            $table->timestampTz('feedback_at')->nullable()->comment('when the customer last set feedback (thumbs up or down) on this answer');
        });

        Schema::table('shopping_assistant_conversations', function (Blueprint $table) {
            $table->timestampTz('challenges_checked_at')->nullable()->index()->comment('when the AI last read the conversation for customer challenges; read again when last_message_at is later');
            $table->timestampTz('wiped_at')->nullable()->index()->comment('when what the customer wrote and was told was wiped, by the retention period or by anonymising the customer');
        });

        Schema::create('shopping_assistant_challenges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopping_assistant_conversation_id')->index();
            $table->foreign('shopping_assistant_conversation_id')->references('id')->on('shopping_assistant_conversations')->cascadeOnDelete();
            $table->string('type', 32)->index()->comment('unmet_need, out_of_stock, price, moq_carton, shipping, website, other');
            $table->string('topic')->comment('short English label shared by conversations about the same thing, e.g. shungite jewellery');
            $table->text('quote')->nullable()->comment("the customer's own words, in their language; wiped with the conversation");
            $table->timestampTz('created_at')->index();
            $table->index(['type', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_assistant_challenges');

        Schema::table('shopping_assistant_conversations', function (Blueprint $table) {
            $table->dropColumn(['challenges_checked_at', 'wiped_at']);
        });

        Schema::table('shopping_assistant_messages', function (Blueprint $table) {
            $table->dropColumn('feedback_at');
        });
    }
};
