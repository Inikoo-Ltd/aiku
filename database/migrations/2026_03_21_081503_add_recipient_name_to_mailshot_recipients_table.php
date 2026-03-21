<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Mar 2026 16:16:13 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('mailshot_recipients', function (Blueprint $table) {
            $table->string('recipient_name')->nullable();
        });
    }


    public function down(): void
    {
        Schema::table('mailshot_recipients', function (Blueprint $table) {
            $table->dropColumn('recipient_name');
        });
    }
};
