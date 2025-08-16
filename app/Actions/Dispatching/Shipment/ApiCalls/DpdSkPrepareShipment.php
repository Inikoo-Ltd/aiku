<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Feb 2023 16:47:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Models\Helpers\Country;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use ReflectionException;
use Yasumi\Yasumi;

class DpdSkPrepareShipment
{
    use AsAction;
    use WithAttributes;
    public function handle($shipper, $request, $pickUp, $shipTo, $parcelsData, $cash_on_delivery): array
    {
        foreach ($parcelsData as $key => $value) {


            $parcelsData[$key]['depth'] = (int) max(round($value['depth'], 0), 1);
            $parcelsData[$key]['height'] = (int) max(round($value['height'], 0), 1);
            $parcelsData[$key]['width'] = (int) max(round($value['width'], 0), 1);

            $weight = round($value['weight'], 1);
            if ($weight > 31.5) {
                $weight = '31.5';
            } elseif ($weight < 0.1) {
                $weight = '0.1';
            }

            $parcelsData[$key]['weight'] = $weight;

        }

        try {
            $pickup_date = new Carbon(Arr::get($pickUp, 'date'));
        } catch (Exception $e) {
            $pickup_date = new Carbon();
        }

        $pickUpTimeWindow = [];

        if (Arr::get($pickUp, 'start')) {
            $pickUpTimeWindow['beginning'] = str_replace(':', '', Arr::get($pickUp, 'start'));
        }
        if (Arr::get($pickUp, 'end')) {
            $pickUpTimeWindow['end'] = str_replace(':', '', Arr::get($pickUp, 'end'));
        }
        if ($pickUpTimeWindow == []) {
            $pickUpTimeWindow['end'] = '1600';
        }

        if (Arr::get($shipTo, 'organization') != '') {
            $type       = 'b2b';
            $name       = Arr::get($shipTo, 'organization');
            $nameDetail = Arr::get($shipTo, 'contact');
        } else {
            $type       = 'b2c';
            $name       = Arr::get($shipTo, 'contact');
            $nameDetail = '';
        }

        $country = (new Country())->where('code', $shipTo['country_code'])->first();

        $services = [];


        if (!empty($cash_on_delivery)) {

            $order_id = preg_replace("/[^0-9]/", "", $request->get('reference'));
            if ($order_id == '') {
                $order_id = rand(1, 100);
            }

            if (Arr::get($cash_on_delivery, 'accept_card', 'No') == 'Yes') {
                $paymentMethod = 1;
            } else {
                $paymentMethod = 0;
            }

            $services = [
                'cod' => [
                    'amount'         => $cash_on_delivery['amount'],
                    'currency'       => $cash_on_delivery['currency'],
                    'bankAccount'    => [
                        'id' => $shipper->data['bankID'],
                    ],
                    'variableSymbol' => $order_id,
                    'paymentMethod'  => $paymentMethod,
                ]
            ];
        }


        $postcode = trim(Arr::get($shipTo, 'sorting_code').' '.Arr::get($shipTo, 'postal_code'));


        if (!in_array(
            $country->code,
            [
                'GB',
                'NL',
                'IE'
            ]
        )) {
            $postcode = preg_replace("/[^0-9]/", '', $postcode);
        }


        $reference = preg_replace("/[^A-Za-z0-9]/", '', $request->get('reference'));


        $street       = str_replace('&', ' ', Arr::get($shipTo, 'address_line_1'));
        $streetDetail = str_replace('&', '', Arr::get($shipTo, 'address_line_2'));


        $street       = str_replace('²', '2', $street);
        $streetDetail = str_replace('²', '2', $streetDetail);


        $street       = str_replace("'", '', $street);
        $streetDetail = str_replace("'", '', $streetDetail);

        $street       = str_replace('`', '', $street);
        $streetDetail = str_replace('`', '', $streetDetail);

        $street       = str_replace('"', '', $street);
        $streetDetail = str_replace('"', '', $streetDetail);

        $street       = str_replace('Ø', 'ø', $street);
        $streetDetail = str_replace('Ø', 'ø', $streetDetail);


        $streetDetail = Str::limit($streetDetail, 35, '');


        $phone = trim(Arr::get($shipTo, 'phone'));
        if (!preg_match('/^\+/', $phone) && $phone != '') {
            $phone = '+'.$phone;
        }

        $pickup_date = $this->get_pick_up_date($pickup_date);

        return array(
            'reference'        => $reference,
            'delisId'          => $shipper->data['delisId'],
            'note'             => Str::limit(strip_tags($request->get('note')), 35, ''),
            'product'          => $request->get('shipping_product', 1),
            'pickup'           => array(
                'date'       => $pickup_date->format('Ymd'),
                'timeWindow' => $pickUpTimeWindow
            ),
            'addressSender'    => array(
                'id' => $shipper->data['pickupID'],
            ),
            'addressRecipient' => array(
                'type'         => $type,
                'name'         => Str::limit($name, 47),
                'nameDetail'   => $nameDetail,
                'street'       => $street,
                'streetDetail' => $streetDetail,
                'zip'          => $postcode,
                'country'      => $country->code_iso_numeric,
                'city'         => Arr::get($shipTo, 'locality'),
                'phone'        => $phone,
                'email'        => Arr::get($shipTo, 'email'),

            ),
            'parcels'          => ['parcel' => $parcelsData],
            'services'         => $services
        );
    }

    private function get_pick_up_date(Carbon $pickup_date): Carbon
    {
        if ($pickup_date->isWeekend() || $this->is_bank_holiday($pickup_date)) {
            return $this->get_pick_up_date($pickup_date->addDay());
        }
        return $pickup_date;
    }

    private function is_bank_holiday($date): bool
    {
        $formatted_date = $date->format('Y-m-d');
        try {
            $holidays = Yasumi::create('Slovakia', $date->format('Y'));
            foreach ($holidays as $day) {
                if ($day == $formatted_date && in_array(
                    $day->getType(),
                    [
                            'bank',
                            'official'
                        ]
                )) {
                    return true;
                }
            }
            return false;
        } catch (ReflectionException $e) {
            return false;
        }
    }
}
