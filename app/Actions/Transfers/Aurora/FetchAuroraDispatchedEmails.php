<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Mar 2023 20:59:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\DispatchedEmail\UpdateDispatchedEmail;
use App\Models\Comms\DispatchedEmail;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraDispatchedEmails extends FetchAuroraAction
{
    public string $jobQueue = 'low-priority';

    public string $commandSignature = 'fetch:dispatched_emails {organisations?*} {--s|source_id=} {--N|only_new : Fetch only new} {--d|db_suffix=} {--w|with=* : Accepted values: events copies full}  {--D|days= : fetch last n days} {--O|order= : order asc|desc}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?DispatchedEmail
    {
        $dispatchedEmail = null;
        $dispatchedEmailData = $organisationSource->fetchDispatchedEmail($organisationSourceId);

        if ($dispatchedEmailData) {
            if ($dispatchedEmail = DispatchedEmail::where('source_id', $dispatchedEmailData['dispatchedEmail']['source_id'])
                ->first()) {
                try {
                    $dispatchedEmail = UpdateDispatchedEmail::make()->action(
                        dispatchedEmail: $dispatchedEmail,
                        modelData: $dispatchedEmailData['dispatchedEmail'],
                        strict: false,
                    );
                    $this->recordChange($organisationSource, $dispatchedEmail->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $dispatchedEmailData['dispatchedEmail'], 'DispatchedEmail', 'update');

                    return null;
                }
            } else {
                try {
                    $dispatchedEmail = StoreDispatchedEmail::make()->action(
                        parent: $dispatchedEmailData['parent'],
                        recipient: $dispatchedEmailData['recipient'],
                        modelData: $dispatchedEmailData['dispatchedEmail'],
                        hydratorsDelay: 60,
                        strict: false,
                    );

                    $this->recordNew($organisationSource);
                    $sourceData = explode(':', $dispatchedEmail->source_id);
                    DB::connection('aurora')->table('Email Tracking Dimension')
                        ->where('Email Tracking Key', $sourceData[1])
                        ->update(['aiku_id' => $dispatchedEmail->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $dispatchedEmailData['dispatchedEmail'], 'DispatchedEmail', 'store');

                    return null;
                }
            }
        }

        if ($dispatchedEmail && (in_array('events', $this->with) or in_array('full', $this->with))) {
            $sourceData = explode(':', $dispatchedEmail->source_id);

            foreach (
                DB::connection('aurora')
                    ->table('Email Tracking Event Dimension')
                    ->select('Email Tracking Event Key as source_id')
                    ->where('Email Tracking Event Tracking Key', $sourceData[1])
                    ->get() as $eventData
            ) {
                FetchAuroraEmailTrackingEvents::run($organisationSource, $eventData->source_id);
            }
        }

        if ($dispatchedEmail && (in_array('copies', $this->with) or in_array('full', $this->with))) {
            $sourceData = explode(':', $dispatchedEmail->source_id);

            foreach (
                DB::connection('aurora')
                    ->table('Email Tracking Email Copy')
                    ->select('Email Tracking Email Copy Key as source_id')
                    ->where('Email Tracking Email Copy Key', $sourceData[1])
                    ->get() as $eventData
            ) {
                FetchAuroraEmailCopies::run($organisationSource, $eventData->source_id);
            }
        }

        return $dispatchedEmail;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')->table('Email Tracking Dimension')->select('Email Tracking Key as source_id');
        $query = $this->commonSelectModelsToFetch($query);

        return $query->orderBy('Email Tracking Created Date', $this->orderDesc ? 'desc' : 'asc');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Email Tracking Dimension');
        $query = $this->commonSelectModelsToFetch($query);

        return $query->count();
    }

    public function commonSelectModelsToFetch($query)
    {
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->fromDays) {
            $query->where('Email Tracking Created Date', '>=', now()->subDays($this->fromDays)->format('Y-m-d'));
        }

        return $query;
    }
}
