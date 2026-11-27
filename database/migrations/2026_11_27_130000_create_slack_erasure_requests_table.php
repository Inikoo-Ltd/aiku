<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('slack_erasure_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slack_user_id')->index();
            $table->foreignId('approved_by_user_id')->constrained('users');
            $table->string('ticket_reference')->nullable();
            $table->string('status')->comment('erasing, held or erased');
            $table->string('hold_reason')->nullable()->comment('dispute, investigation or legal_duty; never "just in case"');
            $table->dateTimeTz('held_until')->nullable();
            $table->unsignedInteger('messages_erased')->default(0);
            $table->unsignedInteger('files_erased')->default(0);
            $table->dateTimeTz('erased_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_erasure_requests');
    }
};
