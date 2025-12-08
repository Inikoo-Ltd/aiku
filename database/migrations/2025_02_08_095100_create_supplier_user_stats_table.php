<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Feb 2025 19:51:49 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Stubs\Migrations\HasSysAdminStats;
use App\Stubs\Migrations\HasUserStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use HasSysAdminStats;
    use HasUserStats;

    public function up(): void
    {
        Schema::create('supplier_user_stats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('supplier_user_id')->index();
            $table->foreign('supplier_user_id')->references('id')->on('supplier_users')->onUpdate('cascade')->onDelete('cascade');
            $table = $this->userStats($table);
            $table = $this->auditFieldsForNonSystem($table);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_user_stats');
    }
};
