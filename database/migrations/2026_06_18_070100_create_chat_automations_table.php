<?php

use App\Enums\CRM\Livechat\ChatAutomationTriggerEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_automations', function (Blueprint $table) {
            $table->smallIncrements('id');

            $table->unsignedInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops')->onUpdate('cascade')->onDelete('cascade');

            $table->string('name');
            $table->string('trigger_type')->index()->default(ChatAutomationTriggerEnum::WELCOME->value);
            $table->boolean('is_enabled')->index()->default(true);

            $table->text('message');
            $table->jsonb('conditions')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('send_once')->default(true);

            $table->jsonb('stats')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_automations');
    }
};
