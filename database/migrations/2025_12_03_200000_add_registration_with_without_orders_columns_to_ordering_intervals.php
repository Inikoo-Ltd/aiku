<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 03 Dec 2025 15:24:49 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

use App\Stubs\Migrations\HasDateIntervalsStats;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    use HasDateIntervalsStats;

    public function up(): void
    {
        Schema::table('group_ordering_intervals', function (Blueprint $table) {
            $this->unsignedIntegerDateIntervals($table, [
                'registrations_with_orders',
                'registrations_without_orders',
            ]);
        });

        Schema::table('organisation_ordering_intervals', function (Blueprint $table) {
            $this->unsignedIntegerDateIntervals($table, [
                'registrations_with_orders',
                'registrations_without_orders',
            ]);
        });

        Schema::table('shop_ordering_intervals', function (Blueprint $table) {
            $this->unsignedIntegerDateIntervals($table, [
                'registrations_with_orders',
                'registrations_without_orders',
            ]);
        });

        Schema::table('master_shop_ordering_intervals', function (Blueprint $table) {
            $this->unsignedIntegerDateIntervals($table, [
                'registrations_with_orders',
                'registrations_without_orders',
            ]);
        });
    }

    public function down(): void
    {
        $tables = [
            'group_ordering_intervals',
            'organisation_ordering_intervals',
            'shop_ordering_intervals',
            'master_shop_ordering_intervals',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columnsToDrop = collect(\DB::select("
                    SELECT column_name FROM information_schema.columns
                    WHERE table_name = '$tableName'
                    AND (column_name LIKE '%registrations_with_orders%' OR column_name LIKE '%registrations_without_orders%')
                "))->pluck('column_name')->toArray();

                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
