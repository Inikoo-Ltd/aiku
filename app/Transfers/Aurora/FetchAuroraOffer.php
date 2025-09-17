<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 19:51:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Utils\Abbreviate;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraOffer extends FetchAurora
{
    protected function parseModel(): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Deal Store Key'});


        $offerCampaign = $this->parseOfferCampaign($this->organisation->id.':'.$this->auroraModelData->{'Deal Campaign Key'});
        if (!$offerCampaign) {
            if ($shop->type == ShopTypeEnum::FULFILMENT) {
                return;
            }
            print "Offer Campaign not found ".$this->auroraModelData->{'Deal Campaign Key'}." \n";
            exit;
        }

        $status = false;
        if ($offerCampaign->shop->state == ShopStateEnum::CLOSED) {
            $state = OfferStateEnum::FINISHED;
        } else {
            $state = match ($this->auroraModelData->{'Deal Status'}) {
                'Waiting' => OfferStateEnum::IN_PROCESS,
                'Finish' => OfferStateEnum::FINISHED,
                'Suspended' => OfferStateEnum::SUSPENDED,
                default => OfferStateEnum::ACTIVE
            };

            if ($this->auroraModelData->{'Deal Status'} == 'Active') {
                $status = true;
            }
        }


        if ($status && in_array($this->auroraModelData->{'Deal Trigger'}, ['Product', 'Department', 'Family', 'Category', 'Customer']) and
            !$this->auroraModelData->{'Deal Trigger Key'}
        ) {
            print "No trigger key for ".$this->auroraModelData->{'Deal Trigger'}." \n";

            //exit;
            return;
        }


        $type = $this->auroraModelData->{'Deal Terms Type'};

        if ($type == '') {
            print "No offer type \n";

            return;
        }

        $trigger      = null;
        $trigger_type = null;

        $isLocked   = false;
        $sourceData = null;


        switch ($this->auroraModelData->{'Deal Trigger'}) {
            case 'Product':

                if ($this->auroraModelData->{'Deal Trigger Key'}) {
                    $trigger = $this->parseProduct($this->organisation->id.':'.$this->auroraModelData->{'Deal Trigger Key'});
                }
                if (!$trigger) {
                    $isLocked   = true;
                    $sourceData = [
                        'note' => 'Trigger product not found',
                    ];
                }
                break;
            case 'Department':
                if ($this->auroraModelData->{'Deal Trigger Key'}) {
                    $trigger = $this->parseDepartment($this->organisation->id.':'.$this->auroraModelData->{'Deal Trigger Key'});
                }
                if (!$trigger) {
                    $isLocked   = true;
                    $sourceData = [
                        'note' => 'Trigger department not found',
                    ];
                }
                break;
            case 'Family':
            case 'Category':
                if ($this->auroraModelData->{'Deal Trigger Key'}) {
                    $trigger = $this->parseFamily($this->organisation->id.':'.$this->auroraModelData->{'Deal Trigger Key'});
                }
                if (!$trigger) {
                    $isLocked   = true;
                    $sourceData = [
                        'note' => 'Trigger family not found',
                    ];
                }
                break;
            case 'Customer':
                if ($this->auroraModelData->{'Deal Trigger Key'}) {
                    $trigger = $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Deal Trigger Key'});
                }
                if (!$trigger) {
                    $isLocked   = true;
                    $sourceData = [
                        'note' => 'Trigger customer not found',
                    ];
                }
                break;
            case 'Customer List':
                if ($this->auroraModelData->{'Deal Trigger Key'}) {
                    $trigger = $this->parseQuery($this->organisation->id.':'.$this->auroraModelData->{'Deal Trigger Key'});
                }
                if (!$trigger) {
                    $isLocked   = true;
                    $sourceData = [
                        'note' => 'Customer List not found',
                    ];
                }

                break;
            case 'Order':
                $trigger = $offerCampaign->shop;

                break;

            default:
                dd($this->auroraModelData);
        }


        if ($state == OfferStateEnum::ACTIVE and $trigger_type == null and $trigger == null) {
            print "No trigger found for ".$this->auroraModelData->{'Deal Trigger'}."  ".$this->auroraModelData->{'Deal Trigger Key'}."  (Active Offer)  \n";
          //  exit;

            return;
        }

        $name = $this->auroraModelData->{'Deal Name'};
        if (!$name) {
            $name = $this->auroraModelData->{'Deal Name Label'};
        }

        $code = $this->cleanOfferCode($name);

        $code = Abbreviate::run($code, maximumLength: 32);
        $code = strtolower($code);

        if ($code != '') {
            $code = $code.'-';
        }
        $code = $code.$this->auroraModelData->{'Deal Key'};

        if ($name == '') {
            $name = $code;
        }


        $this->parsedData['trigger']        = $trigger;
        $this->parsedData['offer_campaign'] = $offerCampaign;
        $this->parsedData['offer']          = [
            'code'            => $code,
            'name'            => $name,
            'status'          => $status,
            'state'           => $state,
            'type'            => $type,
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Deal Key'},
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
            'start_at'        => $this->parseDatetime($this->auroraModelData->{'Deal Begin Date'}),
            'is_locked'       => $isLocked,
        ];

        if ($sourceData) {
            $this->parsedData['offer']['source_data'] = $sourceData;
        }

        if ($endAt = $this->parseDatetime($this->auroraModelData->{'Deal Expiration Date'})) {
            $this->parsedData['offer']['end_at'] = $endAt;
        }

        if ($trigger_type) {
            $this->parsedData['offer']['trigger_type'] = $trigger_type;
        }

        $createdBy = $this->auroraModelData->{'Deal Begin Date'};

        if ($createdBy) {
            $this->parsedData['offer']['created_by'] = $createdBy;
        }
        //dd($this->parsedData);
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Deal Dimension')
            ->where('Deal Key', $id)->first();
    }
}
