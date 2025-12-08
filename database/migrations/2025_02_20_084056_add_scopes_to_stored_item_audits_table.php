<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Feb 2025 17:19:34 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stored_item_audits', function (Blueprint $table) {
            $table->string('scope_type')->index()->comment('Fulfilment|Pallet')->nullable();
            $table->unsignedInteger('scope_id')->nullable();
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stored_item_audits', function (Blueprint $table) {
            $table->dropColumn('scope_type');
            $table->dropColumn('scope_id');
        });
    }
};
