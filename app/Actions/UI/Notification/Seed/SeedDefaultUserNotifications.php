<?php

namespace App\Actions\UI\Notification\Seed;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\Notifications\NotificationType;
use App\Models\Notifications\UserNotificationSetting;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedDefaultUserNotifications
{
    use AsAction;

    public $commandSignature = 'notification:seed-default-user-notifications {--force : Force overwrite existing settings} {--reset : Truncate table and reset ID}';
    public $commandDescription = 'Seed default user notification settings for users with employees';

    public function handle(Command $command): void
    {
        $command->info('Seeding default user notification settings...');
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
            $command->warn('Force mode enabled. Existing global settings will be reset to default.');
        }

        $users = User::query()
            ->whereHas('employees', function ($query) {
                $query->where('state', EmployeeStateEnum::WORKING);
            })
            ->with(['notificationSettings'])
            ->get();

        $notificationTypes = NotificationType::all();

        if ($notificationTypes->isEmpty()) {
            $command->error('No notification types found. Please seed notification types first.');
            return;
        }

        $bar = $command->getOutput()->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            foreach ($notificationTypes as $type) {
                $query = $user->notificationSettings()
                    ->where('notification_type_id', $type->id)
                    ->whereNull('scope_type')
                    ->whereNull('scope_id');

                if ($force) {
                    $query->delete();
                }

                $exists = $query->exists();

                if (!$exists) {
                    UserNotificationSetting::create([
                        'user_id' => $user->id,
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

        if ($users->isNotEmpty()) {
            $validUserIds = $users->pluck('id')->toArray();

            $deletedCount = UserNotificationSetting::query()
                ->whereNull('scope_type')
                ->whereNull('scope_id')
                ->whereNotIn('user_id', $validUserIds)
                ->delete();

            if ($deletedCount > 0) {
                $command->info("Cleaned up {$deletedCount} stale notification settings for invalid users.");
            }
        }

        $command->info('Default user notification settings seeded and synchronized successfully.');
    }
}
