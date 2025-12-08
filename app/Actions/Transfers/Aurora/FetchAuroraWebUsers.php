<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 04 Nov 2022 14:51:04 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\CRM\WebUser\UpdateWebUser;
use App\Models\CRM\WebUser;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraWebUsers extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:web_users {organisations?*} {--s|source_id=} {--S|shop= : Shop slug} {--N|only_new : Fetch only new} {--d|db_suffix=} {--r|reset}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?WebUser
    {
        if ($webUserData = $organisationSource->fetchWebUser($organisationSourceId)) {
            $customer = $webUserData['customer'];

            if ($customer and ! $customer->trashed()) {
                if ($webUser = WebUser::withTrashed()->where('source_id', $webUserData['webUser']['source_id'])
                    ->first()) {
                    try {
                        $webUser = UpdateWebUser::make()->action(
                            $webUser,
                            $webUserData['webUser'],
                            hydratorsDelay: $this->hydratorsDelay,
                            strict: false,
                            audit: false
                        );
                        $this->recordChange($organisationSource, $webUser->wasChanged());
                    } catch (Exception $e) {
                        $this->recordError($organisationSource, $e, $webUserData['webUser'], 'WebUser', 'update');

                        return null;
                    }
                } else {
                    try {
                        $webUser = StoreWebUser::make()->action(
                            $customer,
                            $webUserData['webUser'],
                            hydratorsDelay: $this->hydratorsDelay,
                            strict: false,
                            audit: false
                        );

                        WebUser::enableAuditing();
                        $this->saveMigrationHistory(
                            $webUser,
                            Arr::except($webUserData['webUser'], ['fetched_at', 'last_fetched_at', 'source_id'])
                        );
                        $this->recordNew($organisationSource);

                        $sourceData = explode(':', $webUser->source_id);
                        DB::connection('aurora')->table('Website User Dimension')
                            ->where('Website User Key', $sourceData[1])
                            ->update(['aiku_id' => $webUser->id]);
                    } catch (Exception|Throwable $e) {
                        $this->recordError($organisationSource, $e, $webUserData['webUser'], 'WebUser', 'store');

                        return null;
                    }
                }

                return $webUser;
            }
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Website User Dimension')
            ->select('Website User Key as source_id')
            ->orderBy('source_id');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->website->source_id);
            $query->where('Website User Website Key', $sourceData[1]);
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Website User Dimension');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }
        if ($this->shop) {
            $sourceData = explode(':', $this->shop->website->source_id);
            $query->where('Website User Website Key', $sourceData[1]);
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Website User Dimension')->update(['aiku_id' => null]);
    }
}
