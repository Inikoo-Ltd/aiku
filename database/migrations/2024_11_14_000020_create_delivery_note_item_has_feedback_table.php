<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Nov 2024 19:42:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasGroupOrganisationRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasGroupOrganisationRelationship;

    public function up(): void
    {
        Schema::create('delivery_note_item_has_feedback', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedBigInteger('delivery_note_item_id')->nullable()->comment('original transaction');
            $table->foreign('delivery_note_item_id')->references('id')->on('delivery_note_items');
            $table->unsignedBigInteger('post_delivery_note_item_id')->nullable()->comment('Associated replacement transaction');
            $table->foreign('post_delivery_note_item_id')->references('id')->on('delivery_note_items');

            $table->timestampsTz();
            $table->datetimeTz('fetched_at')->nullable();
            $table->datetimeTz('last_fetched_at')->nullable();
            $table->string('source_id')->nullable()->index();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_feedbacks');
    }
};
