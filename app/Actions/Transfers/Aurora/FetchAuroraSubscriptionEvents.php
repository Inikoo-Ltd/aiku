<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Dec 2024 14:48:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Comms\SubscriptionEvent\StoreSubscriptionEvent;
use App\Actions\Comms\SubscriptionEvent\UpdateSubscriptionEvent;
use App\Models\Comms\SubscriptionEvent;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraSubscriptionEvents extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:subscription_events {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?SubscriptionEvent
    {
        $subscriptionEventData = $organisationSource->fetchSubscriptionEvent($organisationSourceId);
        if ($subscriptionEventData) {
            if (! $subscriptionEventData['subscription_event']) {
                return null;
            }

            if ($subscriptionEvent = SubscriptionEvent::where('source_id', $subscriptionEventData['subscription_event']['source_id'])->first()) {
                try {
                    $subscriptionEvent = UpdateSubscriptionEvent::make()->action(
                        subscriptionEvent: $subscriptionEvent,
                        modelData: $subscriptionEventData['subscription_event'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                    );
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $subscriptionEventData['subscription_event'], 'Subscription_Event', 'update');

                    return null;
                }
            } else {
                // try {
                $subscriptionEvent = StoreSubscriptionEvent::make()->action(
                    parent: $subscriptionEventData['model'],
                    modelData: $subscriptionEventData['subscription_event'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                );

                $this->recordNew($organisationSource);
                $sourceData = explode(':', $subscriptionEvent->source_id);
                DB::connection('aurora')->table('History Dimension')
                    ->where('History Key', $sourceData[1])
                    ->update(['aiku_subscribe_event_id' => $subscriptionEvent->id]);
                //                } catch (Exception $e) {
                //                    $this->recordError($organisationSource, $e, $subscriptionEventData['subscription_event'], 'Subscription_Event', 'store');
                //
                //                    return null;
                //                }
            }

            return $subscriptionEvent;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('History Dimension')
            ->where(function (Builder $query) {
                $query->where('Direct Object', 'Customer')
                    ->whereIn('Indirect Object', [
                        'Customer Send Newsletter',
                        'Customer Send Email Marketing',
                        'Customer Send Basket Emails',
                    ]);
            })
            ->orWhere(function (Builder $query) {
                $query->where('Direct Object', 'Prospect')
                    ->whereIn('History Abstract', [
                        'Recipient opt out again',
                        'Recipient opt out',
                        'Not interested',
                        'Not interested status removed',
                        'Nemám zájem',
                        'Nezaujíma',
                        'No interesado',
                    ]);
            })
            ->select('History Key as source_id');

        if ($this->onlyNew) {
            $query->whereNull('aiku_subscribe_event_id');
        }

        return $query->orderBy('History Date');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('History Dimension')
            ->where(function (Builder $query) {
                $query->where('Direct Object', 'Customer')
                    ->whereIn('Indirect Object', [
                        'Customer Send Newsletter',
                        'Customer Send Email Marketing',
                        'Customer Send Basket Emails',
                    ]);
            })
            ->orWhere(function (Builder $query) {
                $query->where('Direct Object', 'Prospect')
                    ->whereIn('History Abstract', [
                        'Recipient opt out again',
                        'Recipient opt out',
                        'Not interested',
                        'Not interested status removed',
                        'Nemám zájem',
                        'Nezaujíma',
                        'No interesado',
                    ]);
            });

        if ($this->onlyNew) {
            $query->whereNull('aiku_subscribe_event_id');
        }

        return $query->count();
    }
}
