<?php

namespace App\Actions\Comms\Ses;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncSesCredentialsToSettings
{
    use AsAction;

    public string $commandSignature = 'ses:sync-credentials-to-settings';

    public function handle(): void
    {
        $data = [
            'email.provider.access_id'  => config('services.ses.key'),
            'email.provider.access_key' => config('services.ses.secret'),
            'email.provider.region'     => config('services.ses.region'),
        ];

        foreach ([Group::class, Organisation::class, Shop::class] as $modelClass) {
            $modelClass::query()->each(function ($model) use ($data) {
                $settings = $model->settings;
                foreach ($data as $key => $value) {
                    data_set($settings, $key, $value);
                }
                $model->update(['settings' => $settings]);
            });
        }
    }

    public function asCommand(Command $command): int
    {
        $this->handle();
        $command->info('SES credentials synced into Group/Organisation/Shop settings.');

        return 0;
    }
}
