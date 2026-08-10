<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Mon Aug 10 2026
 * Copyright (c) 2026, Eka Yudinata
 */

namespace Database\Seeders;

use App\Models\SysAdmin\TaskType;
use Illuminate\Database\Seeder;

class TaskTypeSeeder extends Seeder
{
    public function run(): void
    {
        $taskTypes = [
            [
                'code' => 'user-notification',
                'name' => 'User Notification',
                'description' => 'Notification sent to a user',
            ],
        ];

        foreach ($taskTypes as $taskType) {
            TaskType::updateOrCreate(['code' => $taskType['code']], $taskType);
        }
    }
}
