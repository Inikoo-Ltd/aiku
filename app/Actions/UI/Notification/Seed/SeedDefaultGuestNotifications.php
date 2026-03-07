<?php

namespace App\Actions\UI\Notification\Seed;

use App\Models\Notifications\NotificationType;
use App\Models\Notifications\UserNotificationSetting;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedDefaultGuestNotifications
{
    use AsAction;

    public $commandSignature = 'notification:seed-default-guest-notifications {--force : Force overwrite existing settings} {--reset : Truncate table and reset ID}';
    public $commandDescription = 'Seed default user notification settings for active guests';

    public function handle(Command $command): void
    {
        $command->info('Seeding default guest notification settings...');
        $force = $command->option('force');
        $reset = $command->option('reset');

        if ($reset) {
            if (!$command->confirm('This will TRUNCATE the user_notification_settings table and DELETE ALL DATA. Are you sure?')) {
                return;
            }

            $connection = DB::connection()->getDriverName();

            if ($connection === 'pgsql') {
                DB::statement('SET session_replication_role = replica;');
                UserNotificationSetting::truncate();
                DB::statement('SET session_replication_role = origin;');
            } elseif ($connection === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');
                UserNotificationSetting::truncate();
                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                UserNotificationSetting::truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            $command->warn('Table truncated and ID reset.');
        } elseif ($force) {
            $command->warn('Force mode enabled. Existing global settings for guests will be reset to default.');
        }


        $guests = User::query()
            ->whereHas('guests', function ($query) {
                $query->where('guests.status', true);
            })
            ->with(['notificationSettings'])
            ->get();

        $notificationTypes = NotificationType::all();

        if ($notificationTypes->isEmpty()) {
            $command->error('No notification types found. Please seed notification types first.');
            return;
        }

        $bar = $command->getOutput()->createProgressBar($guests->count());
        $bar->start();

        foreach ($guests as $guest) {
            foreach ($notificationTypes as $type) {
                $query = $guest->notificationSettings()
                    ->where('notification_type_id', $type->id)
                    ->whereNull('scope_type')
                    ->whereNull('scope_id');

                if ($force) {
                    $query->delete();
                }

                $exists = $query->exists();

                if (!$exists) {
                    UserNotificationSetting::create([
                        'user_id' => $guest->id,
                        'notification_type_id' => $type->id,
                        'scope_type' => null,
                        'scope_id' => null,
                        'is_enabled' => true,
                        'channels' => $type->default_channels ?? ['database'],
                        'filters' => [],
                    ]);
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $command->newLine();


        if ($guests->isNotEmpty()) {
            $validGuestIds = $guests->pluck('id')->toArray();

            $allGuestUserIds = User::whereHas('guests')->pluck('id')->toArray();

            $deletedCount = UserNotificationSetting::query()
                ->whereNull('scope_type')
                ->whereNull('scope_id')
                ->whereIn('user_id', $allGuestUserIds)
                ->whereNotIn('user_id', $validGuestIds)
                ->delete();

            if ($deletedCount > 0) {
                $command->info("Cleaned up {$deletedCount} stale notification settings for inactive guests.");
            }
        }

        $command->info('Default guest notification settings seeded and synchronized successfully.');
    }
}
