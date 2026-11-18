<?php

use App\Enums\Comms\WhatsappBulkRun\WhatsappBulkRunStateEnum;
use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('whatsapp_bulk_runs', function (Blueprint $table) {
            $table->increments('id');
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedInteger('whatsapp_outbox_id')->index();
            $table->foreign('whatsapp_outbox_id')->references('id')->on('whatsapp_outboxes')->cascadeOnDelete();
            $table->string('state')->index()->default(WhatsappBulkRunStateEnum::IN_PROCESS->value);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('number_sent')->default(0);
            $table->unsignedInteger('number_failed')->default(0);
            $table->dateTimeTz('recipients_prepared_at')->nullable();
            $table->dateTimeTz('start_sending_at')->nullable();
            $table->dateTimeTz('sent_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_bulk_runs');
    }
};
