<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Nov 2024 19:41:05 Central Indonesia Time, Sanur, Bali, Indonesia
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
        Schema::create('invoice_transaction_has_feedback', function (Blueprint $table) {
            $table->id();
            $table = $this->groupOrgRelationship($table);
            $table->unsignedSmallInteger('shop_id')->index();
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->unsignedBigInteger('invoice_transaction_id')->nullable()->comment('original transaction');
            $table->foreign('invoice_transaction_id')->references('id')->on('invoice_transactions')->cascadeOnDelete();
            $table->unsignedBigInteger('post_invoice_transaction_id')->nullable()->comment('Associated refund transaction');
            $table->foreign('post_invoice_transaction_id')->references('id')->on('invoice_transactions');

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
