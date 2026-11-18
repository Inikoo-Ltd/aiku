<?php

use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('whatsapp_outboxes', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('code')->index();
            $table->string('name');
            $table->string('state')->index()->default(OutboxStateEnum::SUSPENDED->value);
            $table->unsignedInteger('meta_message_template_id')->nullable()->index();
            $table->foreign('meta_message_template_id')->references('id')->on('meta_message_templates')->nullOnDelete();
            $table->dateTimeTz('last_sent_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->unique(['shop_id', 'code']);
        });

        Schema::table('meta_chat_messages', function (Blueprint $table) {
            $table->unsignedInteger('whatsapp_outbox_id')->nullable()->index();
            $table->foreign('whatsapp_outbox_id')->references('id')->on('whatsapp_outboxes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meta_chat_messages', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_outbox_id']);
            $table->dropColumn('whatsapp_outbox_id');
        });

        Schema::dropIfExists('whatsapp_outboxes');
    }
};
