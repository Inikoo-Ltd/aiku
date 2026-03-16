<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Mar 2026 15:49:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ses_dispatched_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('dispatched_email_id')->index();
            $table->foreign('dispatched_email_id')->references('id')->on('dispatched_emails')->cascadeOnDelete();
            $table->string('ses_id', 80)->index();
            $table->dateTimeTz('send_at')->index();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('ses_dispatched_emails');
    }
};
