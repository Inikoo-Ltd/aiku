<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Mar 2023 20:59:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Comms\Mailshot\StoreMailshot;
use App\Actions\Comms\Mailshot\UpdateMailshot;
use App\Models\Comms\Mailshot;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraMailshots extends FetchAuroraAction
{
    use WithAuroraParsers;

    public string $commandSignature = 'fetch:mailshots {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Mailshot
    {
        $this->organisationSource = $organisationSource;
        $mailshotData = $organisationSource->fetchMailshot($organisationSourceId);
        if (! $mailshotData) {
            return null;
        }

        if ($mailshot = Mailshot::where('source_id', $mailshotData['mailshot']['source_id'])->first()) {
            try {
                $mailshot = UpdateMailshot::make()->action(
                    mailshot: $mailshot,
                    modelData: $mailshotData['mailshot'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                $this->recordChange($organisationSource, $mailshot->wasChanged());
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $mailshotData['mailshot'], 'Mailshot', 'update');

                return null;
            }
        } else {
            try {
                $mailshot = StoreMailshot::make()->action(
                    outbox: $mailshotData['outbox'],
                    modelData: $mailshotData['mailshot'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                Mailshot::enableAuditing();
                $this->saveMigrationHistory(
                    $mailshot,
                    Arr::except($mailshotData['mailshot'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $mailshot->source_id);
                DB::connection('aurora')->table('Email Campaign Dimension')
                    ->where('Email Campaign Key', $sourceData[1])
                    ->update(['aiku_id' => $mailshot->id]);
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $mailshotData['mailshot'], 'Mailshot', 'store');

                return null;
            }
        }

        $organisation = $organisationSource->getOrganisation();

        if ($mailshotData['source_template_id']) {
            $email = $this->parseEmail($organisation->id.':'.$mailshotData['source_template_id']);

            if ($email) {
                $mailshot->update(
                    [
                        'email_id' => $email->id,
                    ]
                );
            }
        }

        return $mailshot;
    }

    public function getModelsQuery(): Builder
    {
        // enum('Newsletter','Marketing','GR Reminder','AbandonedCart','Invite Mailshot','OOS Notification','Invite Full Mailshot')
        $query = DB::connection('aurora')
            ->table('Email Campaign Dimension')
            ->whereIn('Email Campaign Type', ['Newsletter', 'Marketing', 'Invite Full Mailshot', 'AbandonedCart']);

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->select('Email Campaign Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Email Campaign Dimension')
            ->whereIn('Email Campaign Type', ['Newsletter', 'Marketing', 'Invite Full Mailshot', 'AbandonedCart']);
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }
}
