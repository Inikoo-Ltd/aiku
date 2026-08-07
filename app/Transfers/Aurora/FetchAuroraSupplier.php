<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraSupplier extends FetchAurora
{
    use WithAuroraImages;

    protected function parseModel(): void
    {

        if ($this->auroraModelData->{'aiku_ignore'} == 'Yes') {
            return;
        }


        $agentData = Db::connection('aurora')->table('Agent Supplier Bridge')
            ->leftJoin('Agent Dimension', 'Agent Supplier Agent Key', '=', 'Agent Key')
            ->select('Agent Code', 'Agent Key')
            ->where('Agent Supplier Supplier Key', $this->auroraModelData->{'Supplier Key'})->first();


        $agent = null;
        if ($agentData) {
            $agent = $this->parseAgent(
                $this->organisation->id.':'.$agentData->{'Agent Key'}
            );
            if (!$agent) {
                print "agent not found ".$agentData->{'Agent Supplier Agent Key'}." \n";

                return;
            }
        }

        if ($agent) {
            $this->parsedData['parent'] = $agent;
        } else {
            $this->parsedData['parent'] = $this->organisation->group;
        }


        $status = true;

        $archivedAt = $this->parseDatetime($this->auroraModelData->{'Supplier Valid To'});
        if ($this->auroraModelData->{'Supplier Type'} == 'Archived') {
            $status     = false;
            $archivedAt = null;
        }
        $phone = $this->auroraModelData->{'Supplier Main Plain Mobile'};
        if ($phone == '') {
            $phone = $this->auroraModelData->{'Supplier Main Plain Telephone'};
        }


        $name = $this->auroraModelData->{'Supplier Nickname'};
        if (!$name) {
            $name = $this->auroraModelData->{'Supplier Name'};
        }

        $sourceSlug = Str::kebab(strtolower($this->auroraModelData->{'Supplier Code'}));

        $code = preg_replace('/\s/', '-', $this->auroraModelData->{'Supplier Code'});
        $code = preg_replace('/&/', 'and', $code);
        $code = preg_replace('/\s|\?|\.|\'/', '', $code);
        $code = preg_replace('/-?\(.+\)/', '', $code);


        $scopeType = 'Group';
        $scopeId   = $this->organisation->group_id;

        $this->parsedData['supplier'] =
            [
                'name'            => $name,
                'code'            => $code,
                'company_name'    => $this->auroraModelData->{'Supplier Company Name'},
                'contact_name'    => $this->auroraModelData->{'Supplier Main Contact Name'},
                'email'           => $this->auroraModelData->{'Supplier Main Plain Email'},
                'phone'           => $phone,
                'currency_id'     => $this->parseCurrencyID($this->auroraModelData->{'Supplier Default Currency Code'}),
                'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Supplier Key'},
                'source_slug'     => $sourceSlug,
                'created_at'      => $this->parseDatetime($this->auroraModelData->{'Supplier Valid From'}),
                'deleted_at'      => $archivedAt,
                'address'         => $this->parseAddress(prefix: 'Supplier Contact', auAddressData: $this->auroraModelData),
                'archived_at'     => $archivedAt,
                'status'          => $status,
                'fetched_at'      => now(),
                'last_fetched_at' => now(),
                'scope_type'      => $scopeType,
                'scope_id'        => $scopeId
            ];

        $this->parsedData['supplier'] = array_merge(
            $this->parsedData['supplier'],
            $this->parseSupplierSettings()
        );

        $this->parsePhoto();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSupplierSettings(): array
    {
        $metadata = json_decode($this->auroraColumn('Supplier Metadata') ?? '', true) ?: [];

        $deliveryType = match ($this->auroraColumn('Supplier Purchase Order Type')) {
            'Container' => 'container',
            'Parcel'    => 'parcel',
            default     => null,
        };

        $settings = [
            'delivery_type'                   => $deliveryType,
            'production_waiting_time'         => $this->parseNumber($this->auroraColumn('Supplier Average Production Days')),
            'delivery_time'                   => $this->parseNumber($this->auroraColumn('Supplier Average Delivery Days')),
            'default_product_allow_on_demand' => $this->auroraColumn('Supplier On Demand') == 'Yes',
            'default_product_country_origin'  => $this->parseOriginCountryID($this->auroraColumn('Supplier Products Origin Country Code')),
            'payment_terms'                   => Arr::get($metadata, 'payment_terms'),
            'minimum_order'                   => $this->parseNumber(Arr::get($metadata, 'minimum_order_amount')),
            'cooling_period'                  => $this->parseNumber(Arr::get($metadata, 'cooling_order_interval_days')),
            'order_number_prefix'             => $this->parseOrderNumberPrefix($this->auroraColumn('Supplier Order Public ID Format')),
        ];

        if ($deliveryType == 'container') {
            $settings['incoterm']       = $this->auroraColumn('Supplier Default Incoterm');
            $settings['port_of_export'] = $this->auroraColumn('Supplier Default Port of Export');
            $settings['port_of_import'] = $this->auroraColumn('Supplier Default Port of Import');
        }

        return array_filter($settings, fn ($value) => $value !== null && $value !== '');
    }

    private function auroraColumn(string $column): mixed
    {
        return $this->auroraModelData->{$column} ?? null;
    }

    private function parseNumber($value): int|float|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * Aurora keeps 2 or 3 alpha country codes here, anything else is dropped rather than
     * aborting the whole migration the way parseCountryID would.
     */
    private function parseOriginCountryID(?string $countryCode): int|null
    {
        if (!$countryCode || !preg_match('/^[a-zA-Z]{2,3}$/', $countryCode)) {
            return null;
        }

        return Country::withTrashed()
            ->where(strlen($countryCode) == 2 ? 'code' : 'iso3', $countryCode)
            ->value('id');
    }

    /**
     * Aurora stores a sprintf pattern such as "AWSUP%05d", aiku only keeps the literal prefix.
     */
    private function parseOrderNumberPrefix(?string $format): ?string
    {
        if (!$format) {
            return null;
        }

        $prefix = trim(preg_replace('/%[\d.\-+\']*[a-zA-Z]/', '', $format));

        return Str::limit($prefix, 16, '') ?: null;
    }

    private function parsePhoto(): void
    {
        $profile_images            = $this->getModelImagesCollection(
            'Supplier',
            $this->auroraModelData->{'Supplier Key'}
        )->map(function ($auroraImage) {
            return $this->fetchImage($auroraImage);
        });
        $this->parsedData['photo'] = $profile_images->toArray();
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Supplier Dimension')
            ->where('Supplier Key', $id)->first();
    }
}
