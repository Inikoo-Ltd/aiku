<?php

/*
 * author Arya Permana - Kirin
 * created on 16-10-2024-11h-23m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Stubs\Migrations;

use Illuminate\Database\Schema\Blueprint;

trait HasBackInStockReminderStats
{
    public function getRemindersStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_reminders')->default(0);
        $table->unsignedSmallInteger('number_reminders_cancelled')->default(0);

        return $table;
    }

    public function getCustomersWhoRemindedStatsFields(Blueprint $table): Blueprint
    {
        $table->unsignedSmallInteger('number_customers_who_reminded')->default(0);
        $table->unsignedSmallInteger('number_customers_who_un_reminded')->default(0);

        return $table;
    }
}
