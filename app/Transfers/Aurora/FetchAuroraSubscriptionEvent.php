<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Dec 2024 14:33:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\SubscriptionEvent\SubscriptionEventTypeEnum;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FetchAuroraSubscriptionEvent extends FetchAurora
{
    use WithParseUpdateHistory;

    protected function parseModel(): void
    {
        if ($this->auroraModelData->{'Direct Object'} == 'Customer') {
            $model = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'});
        } else {
            $model = $this->parseProspect($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'});
        }

        if (! $model) {
            return;
        }

        $origin = $this->getOrigin($model);

        $type = $this->getSubscriptionEventType($model);

        $outbox = $this->getOutbox($model);

        if (! $outbox) {
            return;
        }

        $this->parsedData['model'] = $model;
        $this->parsedData['subscription_event'] = [
            'outbox_id' => $outbox->id,
            'type' => $type,
            'origin_type' => $origin['type'],
            'origin_id' => $origin['id'],
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'History Key'},
            'created_at' => $this->parseDatetime($this->auroraModelData->{'History Date'}),
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    private function getOutbox(Customer|Prospect $model): ?Outbox
    {
        if ($model instanceof Prospect) {
            return $model->shop->outboxes()->where('code', OutboxCodeEnum::INVITE)->first();
        }

        return match ($this->auroraModelData->{'Indirect Object'}) {
            'Customer Send Newsletter' => $model->shop->outboxes()->where('code', OutboxCodeEnum::NEWSLETTER)->first(),
            'Customer Send Email Marketing' => $model->shop->outboxes()->where('code', OutboxCodeEnum::MARKETING)->first(),
            'Customer Send Basket Emails' => $model->shop->outboxes()->where('code', OutboxCodeEnum::BASKET_PUSH)->first(),
            default => null,
        };
    }

    private function getOrigin(Customer|Prospect $recipient): array
    {
        $origin = [
            'type' => null,
            'id' => null,
        ];

        if ($this->auroraModelData->{'User Key'} == 1 and $this->auroraModelData->{'Subject'} == 'Administrator') {
            $origin['type'] = 'User';
            $origin['id'] = null;

            return $origin;
        }

        if ($this->auroraModelData->{'User Key'} > 0 and $this->auroraModelData->{'Subject'} == 'Staff') {
            $user = $this->parseUser($this->organisation->id.':'.$this->auroraModelData->{'User Key'});
            if ($user) {
                $origin['type'] = 'User';
                $origin['id'] = $user->id;

                return $origin;
            }
        }

        if ($this->auroraModelData->{'User Key'} == 0 and
            $this->auroraModelData->{'Subject'} == 'Staff' and
            $this->auroraModelData->{'Subject Key'} == 0) {
            // Date inikoo CRM was implemented
            if (Carbon::parse($this->auroraModelData->{'History Date'})->lessThan('2011-04-04 07:57:36')) {
                return [
                    'type' => 'User',
                    'id' => null,
                ];
            }
        }

        if (in_array(
            $this->auroraModelData->{'History Abstract'},
            [
                'Recipient opt out',
                'Recipient opt out again',
            ]
        )) {
            $dispatchedEmail = $this->getDispatchedEmail($recipient);
            if ($dispatchedEmail) {
                return [
                    'type' => class_basename($dispatchedEmail),
                    'id' => $dispatchedEmail->id,
                ];
            }
            $dispatchedEmail = $this->getDispatchedEmail($recipient, 60);
            if ($dispatchedEmail) {
                return [
                    'type' => class_basename($dispatchedEmail),
                    'id' => $dispatchedEmail->id,
                ];
            }

            $dispatchedEmail = $this->getDispatchedEmail($recipient, 3600);
            if ($dispatchedEmail) {
                return [
                    'type' => class_basename($dispatchedEmail),
                    'id' => $dispatchedEmail->id,
                ];
            }
            $dispatchedEmail = $this->getDispatchedEmail($recipient, 86400);
            if ($dispatchedEmail) {
                return [
                    'type' => class_basename($dispatchedEmail),
                    'id' => $dispatchedEmail->id,
                ];
            }

            return [
                'type' => 'DispatchedEmail',
                'id' => null,
            ];
        }

        if (
            preg_match('/Updated by customer|Actualizado por cliente|Vom Kunden aktualiziert|Atualizado pelo cliente|Aktualizácia zákazníko|Zadáno Zákazníkem|Az ügyfél által frissíttve|Uaktualnione przez klienta/', $this->auroraModelData->{'History Details'})
        ) {
            return [
                'type' => class_basename($recipient),
                'id' => $recipient->id,
            ];
        }

        if ($this->auroraModelData->{'Subject'} == 'Customer') {
            $customer = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Subject Key'});
            if ($customer) {
                $origin['type'] = 'Customer';
                $origin['id'] = $customer->id;

                return $origin;
            }
        }

        if ($this->auroraModelData->{'User Key'} == 0 and
            $this->auroraModelData->{'Subject'} == 'Staff' and
            $this->auroraModelData->{'Subject Key'} == 0) {
            return [
                'type' => 'User',
                'id' => null,
            ];

        }

        if ($this->auroraModelData->{'Subject'} == 'Customer') {
            $customer = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Subject Key'});
            if ($customer) {
                $origin['type'] = 'Customer';
                $origin['id'] = $customer->id;

                return $origin;
            }
        }

        return $origin;
    }

    private function getDispatchedEmail($recipient, int $offset = 0): ?DispatchedEmail
    {
        $dispatchedEmail = null;
        $sourceData = explode(':', $recipient->source_id);

        $query = DB::connection('aurora')
            ->table('Email Tracking Event Dimension')
            ->leftJoin('Email Tracking Dimension', 'Email Tracking Key', '=', 'Email Tracking Event Tracking Key')
            ->where('Email Tracking Event Date', $offset == 0 ? '=' : '<=', $this->auroraModelData->{'History Date'})
            ->where('Email Tracking Event Type', 'Clicked')
            ->where('Email Tracking Recipient', class_basename($recipient))
            ->where('Email Tracking Recipient Key', $sourceData[1])
            ->where('Email Tracking Event Data', 'like', '%unsubscribe.php%');
        if ($offset > 0) {
            $date = Carbon::parse($this->auroraModelData->{'History Date'})->subSeconds($offset)->format('Y-m-d H:i:s');
            $query->where('Email Tracking Event Date', '>', $date);
        }

        if ($auroraTrackingData = $query->first()) {
            $dispatchedEmail = $this->parseDispatchedEmail($this->organisation->id.':'.$auroraTrackingData->{'Email Tracking Key'});
        }

        return $dispatchedEmail;
    }

    private function getSubscriptionEventType(Customer|Prospect $auditable): SubscriptionEventTypeEnum
    {
        if ($auditable instanceof Prospect) {
            if (in_array(
                $this->auroraModelData->{'History Abstract'},
                [
                    'Recipient opt out',
                    'Recipient opt out again',
                    'Not interested',
                    'No interesado',
                    'Nemám zájem',
                    'Nezaujíma',
                ]
            )) {
                return SubscriptionEventTypeEnum::UNSUBSCRIBE;
            } elseif ($this->auroraModelData->{'History Abstract'} == 'Not interested status removed') {
                return SubscriptionEventTypeEnum::SUBSCRIBE;
            } else {
                dd($this->auroraModelData);
            }
        }

        if (str_ends_with($this->auroraModelData->{'History Details'}, '"No" to "Yes"')) {
            return SubscriptionEventTypeEnum::SUBSCRIBE;
        }
        if (str_ends_with($this->auroraModelData->{'History Details'}, '"Yes" to "No"')) {
            return SubscriptionEventTypeEnum::UNSUBSCRIBE;
        }

        if (str_ends_with($this->auroraModelData->{'History Abstract'}, '(No)')) {
            return SubscriptionEventTypeEnum::UNSUBSCRIBE;
        }
        if (str_ends_with($this->auroraModelData->{'History Abstract'}, '(Yes)')) {
            return SubscriptionEventTypeEnum::SUBSCRIBE;
        }

        if (str_ends_with($this->auroraModelData->{'History Details'}, ' "Yes" à "No"')) {
            return SubscriptionEventTypeEnum::UNSUBSCRIBE;
        }
        if (str_ends_with($this->auroraModelData->{'History Details'}, ' "No" à "Yes"')) {
            return SubscriptionEventTypeEnum::SUBSCRIBE;
        }

        if (str_ends_with($this->auroraModelData->{'History Details'}, '"Yes" do "No"')) {
            return SubscriptionEventTypeEnum::UNSUBSCRIBE;
        }
        if (str_ends_with($this->auroraModelData->{'History Details'}, '"No" do "Yes"')) {
            return SubscriptionEventTypeEnum::SUBSCRIBE;
        }

        if (str_ends_with($this->auroraModelData->{'History Abstract'}, 'was changed to No')) {
            return SubscriptionEventTypeEnum::UNSUBSCRIBE;
        }

        $newValue = $this->parseHistoryUpdatedNewValues($auditable);
        $value = Arr::get($newValue, 'type');
        if (in_array($value, ['No', 'Nie'])) {
            $type = SubscriptionEventTypeEnum::UNSUBSCRIBE;
        } elseif (in_array($value, ['Yes', 'Áno'])) {
            $type = SubscriptionEventTypeEnum::SUBSCRIBE;
        } else {
            print_r($value);
            dd($this->auroraModelData);
        }

        return $type;
    }

    private function getField(): string
    {
        return 'type';
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('History Dimension')
            ->where('History Key', $id)->first();
    }
}
