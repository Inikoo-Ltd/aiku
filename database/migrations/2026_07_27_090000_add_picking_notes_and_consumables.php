<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 09:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->text('note_to_pickers')->nullable();
            $table->text('note_to_packers')->nullable();
            $table->jsonb('consumables')->nullable()->comment('[{"code": "IAL01", "quantity": 1}] the packer adds per product ordered');
        });
    }

    public function down(): void
    {
        Schema::table('org_stocks', function (Blueprint $table) {
            $table->dropColumn(['note_to_pickers', 'note_to_packers', 'consumables']);
        });
    }
};
