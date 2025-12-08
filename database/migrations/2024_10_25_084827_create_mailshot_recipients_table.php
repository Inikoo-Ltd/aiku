<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Oct 2024 16:50:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailshot_recipients', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('mailshot_id')->index();
            $table->foreign('mailshot_id')->references('id')->on('mailshots')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedInteger('dispatched_email_id')->index();
            $table->foreign('dispatched_email_id')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
            $table->string('recipient_type');
            $table->unsignedInteger('recipient_id');
            $table->unsignedSmallInteger('channel')->index();
            $table->timestampsTz();
            $table->index(['recipient_type', 'recipient_id', 'mailshot_id']);
            $table->unique(['mailshot_id', 'dispatched_email_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailshot_recipients');
    }
};
