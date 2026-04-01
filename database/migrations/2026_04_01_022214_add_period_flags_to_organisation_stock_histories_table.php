<?php



use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->boolean('is_week')->index()->default(false);
            $table->boolean('is_month')->index()->default(false);
            $table->boolean('is_year')->index()->default(false);
        });
    }


    public function down(): void
    {
        Schema::table('organisation_stock_histories', function (Blueprint $table) {
            $table->dropColumn(['is_week', 'is_month', 'is_year']);
        });
    }
};
