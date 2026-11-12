<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankConnection;

use App\Actions\Accounting\BankConnection\Wise\WithWiseApi;
use App\Enums\Accounting\BankConnection\BankConnectionProviderEnum;
use App\Models\Accounting\BankConnection;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreBankConnection
{
    use AsAction;
    use WithWiseApi;

    public string $commandSignature = 'bank-connections:create {organisation : slug} {provider} {name} {--external-id= : bank-side account id, e.g. the Wise business profile id}';

    /**
     * @param array{provider: BankConnectionProviderEnum, name: string, credentials: string, external_id?: string|null} $modelData
     */
    public function handle(Organisation $organisation, array $modelData): BankConnection
    {
        if ($modelData['provider'] === BankConnectionProviderEnum::WISE) {
            $modelData['external_id'] = $this->wiseBusinessProfileId($modelData['credentials'], $modelData['external_id'] ?? null);
        }

        return BankConnection::create(array_merge($modelData, [
            'group_id'        => $organisation->group_id,
            'organisation_id' => $organisation->id,
        ]));
    }

    private function wiseBusinessProfileId(#[\SensitiveParameter] string $token, ?string $profileId): string
    {
        $businessProfiles = collect($this->wiseGet($token, '/v2/profiles') ?? [])
            ->where('type', 'BUSINESS')
            ->when($profileId, fn ($profiles) => $profiles->where('id', (int)$profileId));

        if ($businessProfiles->count() !== 1) {
            throw ValidationException::withMessages([
                'external_id' => 'The token must reach exactly one matching Wise business profile, found '.$businessProfiles->count(),
            ]);
        }

        return (string)$businessProfiles->first()['id'];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $credentials  = (string)$command->secret('API token');

        $bankConnection = $this->handle($organisation, [
            'provider'    => BankConnectionProviderEnum::from($command->argument('provider')),
            'name'        => $command->argument('name'),
            'credentials' => trim($credentials),
            'external_id' => $command->option('external-id'),
        ]);

        $command->info("Bank connection $bankConnection->id created for profile $bankConnection->external_id");

        return 0;
    }
}
