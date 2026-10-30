<?php

use App\Actions\Goods\ProductDevelopment\SeedProductDevelopmentChecklistTemplates;
use App\Models\Goods\ProductDevelopmentChecklistTemplate;
use App\Models\SysAdmin\Group;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->unsignedInteger('net_volume')->nullable();
        });

        $groupIds = ProductDevelopmentChecklistTemplate::query()->distinct()->pluck('group_id');
        foreach (Group::whereIn('id', $groupIds)->get() as $group) {
            SeedProductDevelopmentChecklistTemplates::run($group);
            SeedProductDevelopmentChecklistTemplates::make()->backfill($group);
        }
    }

    public function down(): void
    {
        Schema::table('trade_units', function (Blueprint $table) {
            $table->dropColumn('net_volume');
        });
    }
};
