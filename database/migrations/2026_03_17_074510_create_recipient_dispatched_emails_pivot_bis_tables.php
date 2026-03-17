<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Mar 2026 11:43:52 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('user_has_dispatched_emails', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->index();
            $table->foreign('user_id', 'wu_hde_user_id_foreign')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->unsignedBigInteger('dispatched_email_id');
            $table->foreign('dispatched_email_id', 'wu_hde_email_id_foreign')->references('id')->on('dispatched_emails')->onUpdate('cascade')->onDelete('cascade');
        });


    }

    public function down(): void
    {
        Schema::dropIfExists('user_has_dispatched_emails');
    }
};
