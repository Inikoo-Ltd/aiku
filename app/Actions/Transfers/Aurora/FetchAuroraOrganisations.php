<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Apr 2023 14:31:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\SysAdmin\Organisation\UpdateOrganisation;
use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Transfers\WithSaveMigrationHistory;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use App\Transfers\SourceOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchAuroraOrganisations
{
    use AsAction;
    use WithOrganisationSource;
    use WithSaveMigrationHistory;

    public string $commandSignature = 'fetch:aurora_organisations  {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, Organisation $organisation): Organisation
    {
        $organisationData = $organisationSource->fetchOrganisation($organisation);

        $organisation = UpdateOrganisation::make()->action(
            organisation: $organisation,
            modelData: $organisationData['organisation'],
            hydratorsDelay: 5,
            strict: false,
            audit: false
        );
        Organisation::enableAuditing();

        if (! $organisation->fetched_at) {
            $organisation->updateQuietly(
                [
                    'fetched_at' => now(),
                ]
            );

            $this->saveMigrationHistory(
                $organisation,
                Arr::except($organisationData['organisation'], ['source', 'source_id', 'fetched_at', 'last_fetched_at'])
            );
        }

        $sourceData = explode(':', $organisation->source_id);
        DB::connection('aurora')->table('Account Dimension')
            ->where('Account Key', $sourceData[1])
            ->update(['aiku_id' => $organisation->id]);

        $accountsServiceProviderData = Db::connection('aurora')->table('Payment Service Provider Dimension')
            ->select('Payment Service Provider Key')
            ->where('Payment Service Provider Block', 'Accounts')->first();

        if ($accountsServiceProviderData) {
            $accountsServiceProvider = $organisation->getAccountsServiceProvider();
            $accountsServiceProvider->update(
                [
                    'source_id' => $organisation->id.':'.$accountsServiceProviderData->{'Payment Service Provider Key'},
                ]
            );

            if ($accountsServiceProvider->fetched_at) {
                $accountsServiceProvider->update(
                    [
                        'last_fetched_at' => now(),
                    ]
                );
            } else {
                $accountsServiceProvider->update(
                    [
                        'fetched_at' => now(),
                    ]
                );
            }
        }

        /** @var Organisation $otherOrganisation */
        foreach (Organisation::where('type', OrganisationTypeEnum::SHOP)->where('id', '!=', $organisation->id)->get() as $otherOrganisation) {

            $orgPartner = $organisation->orgPartners()->where('partner_id', $otherOrganisation->id)->first();
            if ($orgPartner) {
                $supplierData = DB::connection('aurora')
                    ->table('Supplier Dimension')
                    ->select('Supplier Key')
                    ->where('partner_code', $otherOrganisation->slug)
                    ->first();

                if ($supplierData) {
                    $modelSources = Arr::get($orgPartner->sources, 'suppliers', []);
                    $modelSources[] = $organisation->id.':'.$supplierData->{'Supplier Key'};
                    $modelSources = array_unique($modelSources);
                    $sources['suppliers'] = $modelSources;
                    $orgPartner->updateQuietly(
                        [
                            'sources' => $sources,
                        ]
                    );
                }
            }
        }

        return $organisation;
    }

    /**
     * @throws \Exception
     */
    public function asCommand(Command $command): int
    {
        foreach (Organisation::all() as $organisation) {

            if ($organisation->type == OrganisationTypeEnum::SHOP and Arr::get($organisation->source, 'type') == 'Aurora') {

                $organisationSource = $this->getOrganisationSource($organisation);
                $organisationSource->initialisation($organisation, $command->option('db_suffix') ?? '');
                $organisation = $this->handle($organisationSource, $organisation);
                if ($organisation->created_at->lt($organisation->group->created_at)) {
                    $organisation->group->created_at = $organisation->created_at;
                    $organisation->group->save();
                }
            }
        }

        return 0;
    }
}
