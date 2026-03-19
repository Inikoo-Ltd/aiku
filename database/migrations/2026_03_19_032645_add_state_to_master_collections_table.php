<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Mar 2026 11:27:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Enums\Catalogue\MasterCollection\MasterCollectionStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('master_collections', function (Blueprint $table) {
            $table->string('state')->index()->default(MasterCollectionStateEnum::ACTIVE->value)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('master_collections', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
