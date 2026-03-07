<?php

namespace Database\Seeders;

use App\Models\Notifications\NotificationType;
use Illuminate\Database\Seeder;

class OrderingNotificationSeeder extends Seeder
{
    public function run(): void
    {
        NotificationType::updateOrCreate(
            ['slug' => 'order.state_update'],
            [
                'name' => 'Order State Updated',
                'category' => 'Ordering',
                'description' => 'Notify when an order state changes (e.g. from Processing to Shipped).',
                'available_channels' => ['database'],
                'default_channels' => ['database'],
            ]
        );
    }
}
