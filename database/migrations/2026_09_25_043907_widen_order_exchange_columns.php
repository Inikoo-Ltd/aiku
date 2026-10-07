<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = [
        'orders',
        'transactions',
        'invoices',
        'invoice_transactions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if ($this->exchangeColumnsAreWide($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('grp_exchange', 22, 10)->nullable()->change();
                $table->decimal('org_exchange', 22, 10)->nullable()->change();
            });
        }
    }

    private function exchangeColumnsAreWide(string $tableName): bool
    {
        return collect(Schema::getColumns($tableName))
            ->whereIn('name', ['grp_exchange', 'org_exchange'])
            ->pluck('type')
            ->unique()
            ->values()
            ->all() === ['numeric(22,10)'];
    }
};
