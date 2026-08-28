<?php

use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use App\Stubs\Migrations\HasSoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;
    use HasSoftDeletes;

    public function up(): void
    {
        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->increments('id');
            $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->nullable()->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade');
            $table->string('slug')->unique()->collation('und_ns');
            $table->string('name')->index();

            $table->unsignedInteger('meta_message_template_id')->nullable()->index();
            $table->foreign('meta_message_template_id')->references('id')->on('meta_message_templates')->onUpdate('cascade');

            $table->string('state')->index()->default(WhatsappCampaignStateEnum::IN_PROCESS->value);
            $table->string('type')->index();

            $table->dateTimeTz('ready_at')->nullable();
            $table->dateTimeTz('scheduled_at')->nullable();
            $table->dateTimeTz('start_sending_at')->nullable();
            $table->dateTimeTz('sent_at')->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->dateTimeTz('stopped_at')->nullable();

            $table->jsonb('recipients_recipe')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);

            $table->unsignedSmallInteger('publisher_id')->nullable();
            $table->foreign('publisher_id')->references('id')->on('users');

            $table->jsonb('data')->nullable();
            $table->timestampsTz();
            $this->softDeletes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaigns');
    }
};
