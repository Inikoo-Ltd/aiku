<?php

/*
 * author Arya Permana - Kirin
 * created on 03-03-2026
 * github: https://github.com/KirinZero0
 * copyright 2026
 */

use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->unsignedInteger('number_picking_sessions')->default(0);

            foreach (PickingSessionStateEnum::cases() as $case) {
                $table->unsignedInteger('number_picking_sessions_state_'.$case->snake())->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_stats', function (Blueprint $table) {
            $table->dropColumn('number_picking_sessions');

            foreach (PickingSessionStateEnum::cases() as $case) {
                $table->dropColumn('number_picking_sessions_state_'.$case->snake());
            }
        });
    }
};
