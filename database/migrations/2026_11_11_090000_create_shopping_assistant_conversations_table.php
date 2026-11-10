<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('shopping_assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedSmallInteger('website_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->unsignedInteger('web_user_id')->nullable();
            $table->unsignedBigInteger('website_visitor_id')->nullable();
            $table->unsignedInteger('chat_session_id')->nullable()->comment('human chat the conversation was handed over to');
            $table->string('entry_point')->index()->comment('profile, search, chat, command');
            $table->string('language_code', 12);
            $table->string('status')->index()->comment('open, handed_over, closed');
            $table->unsignedInteger('messages_count')->default(0);
            $table->unsignedInteger('model_calls')->default(0);
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->string('model')->nullable();
            $table->timestampTz('handed_over_at')->nullable();
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_assistant_conversations');
    }
};
