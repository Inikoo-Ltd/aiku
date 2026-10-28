<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('supplier_portal_contacts', function (Blueprint $table) {
            $table->string('designation')->nullable()->comment('what the person does at the supplier: sales manager, accounts, assistant, designer, other');
            $table->string('whatsapp', 64)->nullable();
            $table->string('wechat', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_portal_contacts', function (Blueprint $table) {
            $table->dropColumn(['designation', 'whatsapp', 'wechat']);
        });
    }
};
