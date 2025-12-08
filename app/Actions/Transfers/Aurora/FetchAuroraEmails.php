<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Nov 2024 17:14:28 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Comms\Email\StoreEmail;
use App\Actions\Comms\Email\UpdateEmail;
use App\Models\Comms\Email;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraEmails extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:emails {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Email
    {
        $emailData = $organisationSource->fetchEmail($organisationSourceId);
        if (! $emailData) {
            return null;
        }

        if ($email = Email::where('source_id', $emailData['email']['source_id'])
            ->first()) {
            try {
                $email = UpdateEmail::make()->action(
                    email: $email,
                    modelData: $emailData['email'],
                    hydratorsDelay: 120,
                    strict: false,
                    audit: false
                );
                $this->recordChange($organisationSource, $email->wasChanged());
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $emailData['email'], 'Email', 'update');

                return null;
            }
        } else {

            try {
                $email = StoreEmail::make()->action(
                    parent: $emailData['parent'],
                    emailTemplate: null,
                    modelData: $emailData['email'],
                    hydratorsDelay: 120,
                    strict: false,
                    audit: false
                );

                Email::enableAuditing();
                $this->saveMigrationHistory(
                    $email,
                    Arr::except($emailData['email'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $email->source_id);
                DB::connection('aurora')->table('Email Template Dimension')
                    ->where('Email Template Key', $sourceData[1])
                    ->update(['aiku_id' => $email->id]);
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $emailData['email'], 'Email', 'store');

                return null;
            }
        }

        return $email;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Email Template Dimension')
            ->select('Email Template Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Email Template Dimension')->count();
    }
}
