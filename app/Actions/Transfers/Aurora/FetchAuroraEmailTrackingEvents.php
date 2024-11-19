<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 12 Mar 2023 20:33:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Comms\EmailTrackingEvent\StoreEmailTrackingEvent;
use App\Actions\Comms\EmailTrackingEvent\UpdateEmailTrackingEvent;
use App\Models\Comms\EmailTrackingEvent;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraEmailTrackingEvents extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:email_tracking_events {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?EmailTrackingEvent
    {
        $emailTrackingEventData = $organisationSource->fetchEmailTrackingEvent($organisationSourceId);
        if ($emailTrackingEventData) {
            if (!$emailTrackingEventData['dispatchedEmail']) {
                return null;
            }

            if ($emailTrackingEvent = EmailTrackingEvent::where('source_id', $emailTrackingEventData['emailTrackingEvent']['source_id'])->first()) {
                try {
                    $emailTrackingEvent = UpdateEmailTrackingEvent::make()->action(
                        emailTrackingEvent: $emailTrackingEvent,
                        modelData: $emailTrackingEventData['emailTrackingEvent'],
                        hydratorsDelay: 60,
                        strict: false,
                    );
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $emailTrackingEventData['emailTrackingEvent'], 'EmailTrackingEvent', 'update');

                    return null;
                }
            } else {
                try {
                    $emailTrackingEvent = StoreEmailTrackingEvent::make()->action(
                        dispatchedEmail: $emailTrackingEventData['dispatchedEmail'],
                        modelData: $emailTrackingEventData['emailTrackingEvent'],
                        hydratorsDelay: 60,
                        strict: false,
                    );

                    $this->recordNew($organisationSource);
                    $sourceData = explode(':', $emailTrackingEvent->source_id);
                    DB::connection('aurora')->table('Email Tracking Event Dimension')
                        ->where('Email Tracking Event Key', $sourceData[1])
                        ->update(['aiku_id' => $emailTrackingEvent->id]);
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $emailTrackingEventData['emailTrackingEvent'], 'EmailTrackingEvent', 'store');

                    return null;
                }
            }


            return $emailTrackingEvent;
        }

        return null;
    }


    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Email Tracking Event Dimension')
            ->select('Email Tracking Event Key as source_id');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->orderBy('Email Tracking Event Date');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Email Tracking Event Dimension');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }
}
