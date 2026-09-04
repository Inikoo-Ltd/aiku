<?php

use App\Enums\Helpers\Audit\AuditUserTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The stats tables get one column per audit event, and `published` was added to the event enum
     * after these tables were created, so the hydrators wrote to columns that did not exist.
     *
     * @return array<string, array<int, string>>
     */
    private function columns(): array
    {
        $perUserType = array_map(
            fn (AuditUserTypeEnum $case) => "number_audits_user_type_{$case->snake()}_event_published",
            AuditUserTypeEnum::cases()
        );

        return [
            'group_sysadmin_stats' => array_merge(['number_audits_event_published'], $perUserType),
            'organisation_stats'   => array_merge(['number_audits_event_published'], $perUserType),
            'user_stats'           => ['number_audits_event_published'],
            'web_user_stats'       => ['number_audits_event_published'],
            'supplier_user_stats'  => ['number_audits_event_published'],
        ];
    }

    public function up(): void
    {
        foreach ($this->columns() as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                foreach ($columns as $column) {
                    if (!Schema::hasColumn($tableName, $column)) {
                        $table->unsignedBigInteger($column)->default(0);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns() as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
