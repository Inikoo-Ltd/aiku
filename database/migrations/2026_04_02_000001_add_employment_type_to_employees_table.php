<?php

use App\Enums\HumanResources\Employee\EmploymentTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employment_type')->after('type')->nullable();
        });

        DB::table('employees')->where('type', 'part-time')->update(['employment_type' => 'part-time']);
        DB::table('employees')->where('type', 'full-time')->update(['employment_type' => 'full-time']);

        DB::table('employees')->whereNull('employment_type')->update(['employment_type' => 'full-time']);

        DB::table('employees')
            ->whereIn('type', ['part-time', 'full-time'])
            ->update(['type' => 'employee']);

        Schema::table('employees', function (Blueprint $table) {
            $table->string('employment_type')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        DB::table('employees')->update([
            'type' => DB::raw('employment_type')
        ]);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('employment_type');
        });
    }
};
