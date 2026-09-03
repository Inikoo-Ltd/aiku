<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Step two of retiring these columns, safe only once the release that stopped writing them
     * is live everywhere. See 2026_09_03_220000, which made the column optional so that the
     * outgoing and incoming releases could both run against it during a deploy.
     *
     * amount was a copy of payments.amount, written at attach and never maintained. share was
     * 1 on every row. Nothing read either. The split allocations and the drifted rows were
     * exported before this ran, since the amount column was their only record.
     */
    public function up(): void
    {
        Schema::table('model_has_payments', function (Blueprint $table) {
            $table->dropColumn(['amount', 'share']);
        });
    }

    public function down(): void
    {
        Schema::table('model_has_payments', function (Blueprint $table) {
            $table->decimal('amount', 12)->default(0);
            $table->float('share')->default(1);
        });
    }
};
