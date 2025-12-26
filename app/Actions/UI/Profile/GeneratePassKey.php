<?php

namespace App\Actions\UI\Profile;

use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;

class GeneratePassKey
{
    use AsAction;

    public function handle()
    {
        $generatePassKeyOptionsAction = app(GeneratePasskeyRegisterOptionsAction::class);

        return $generatePassKeyOptionsAction->execute(auth()->user());
    }

    public function asController()
    {
        return $this->handle();
    }
}
