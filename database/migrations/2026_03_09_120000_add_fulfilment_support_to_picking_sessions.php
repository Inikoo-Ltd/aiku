<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('picking_sessions', function (Blueprint $table) {
            // Default to dropshipping for existing records to maintain backward compatibility
            $table->string('type')->default(PickingSessionTypeEnum::DROPSHIPPING->value);
            $table->unsignedInteger('number_pallet_returns')->default(0);
        });

        Schema::create('picking_session_has_pallet_returns', function (Blueprint $table) {
            $table->smallInteger('group_id');
            $table->smallInteger('organisation_id');
            $table->foreignId('picking_session_id')->references('id')->on('picking_sessions')
                ->nullOnDelete();
            $table->foreignId('pallet_return_id')->references('id')->on('pallet_returns')
                ->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::table('pallet_return_items', function (Blueprint $table) {
            $table->foreignId('picking_session_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pallet_return_items', function (Blueprint $table) {
            $table->dropForeign(['picking_session_id']);
            $table->dropColumn('picking_session_id');
        });

        Schema::dropIfExists('picking_session_has_pallet_returns');

        Schema::table('picking_sessions', function (Blueprint $table) {
            $table->dropColumn('number_pallet_returns');
            $table->dropColumn('type');
        });
    }
};
