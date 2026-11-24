<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        foreach (['chat_assignments', 'meta_chat_assignments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('claimed_via')->nullable()->index()->comment('HELP-4896 ChatAssignmentClaimedViaEnum: the button or reply that took the conversation; null before 24 Nov 2026 and for transfers');
            });
        }
    }

    public function down(): void
    {
        foreach (['chat_assignments', 'meta_chat_assignments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('claimed_via');
            });
        }
    }
};
