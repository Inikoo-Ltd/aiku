<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 05 Dec 2025 14:26:14 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Accounting;

use App\Enums\Accounting\Intrastat\IntrastatDeliveryTermsEnum;
use App\Enums\Accounting\Intrastat\IntrastatNatureOfTransactionEnum;
use App\Enums\Accounting\Intrastat\IntrastatTransportModeEnum;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class IntrastatExportTimeSeriesRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                        => $this->id,
            'date'                      => $this->date ? Carbon::parse($this->date)->format('Y-m-d') : null,
            'tariff_code'               => $this->tariff_code,
            'country'                   => [
                'id'   => $this->country_id,
                'name' => $this->country_name,
                'code' => $this->country_code,
            ],
            'tax_category'              => [
                'id'   => $this->tax_category_id,
                'name' => $this->tax_category_name ?? __('N/A'),
            ],
            'delivery_note_type'        => $this->delivery_note_type,
            'quantity'                  => number_format($this->quantity, 2),
            'value_org_currency'        => number_format($this->value_org_currency, 2),
            'currency_code'             => $this->currency_code,
            'weight'                    => number_format($this->weight / 1000, 2),
            'delivery_notes_count'      => $this->delivery_notes_count,
            'products_count'            => $this->products_count,
            'invoices_count'            => $this->invoices_count,
            'partner_tax_numbers'       => $this->partner_tax_numbers,
            'valid_tax_numbers_count'   => $this->valid_tax_numbers_count,
            'invalid_tax_numbers_count' => $this->invalid_tax_numbers_count,
            'mode_of_transport'         => $this->formatEnum($this->mode_of_transport, IntrastatTransportModeEnum::class),
            'delivery_terms'            => $this->formatEnum($this->delivery_terms, IntrastatDeliveryTermsEnum::class),
            'nature_of_transaction'     => $this->formatEnum($this->nature_of_transaction, IntrastatNatureOfTransactionEnum::class),
        ];
    }

    private function formatEnum($value, string $enumClass): array
    {
        if ($value === null) {
            return [
                'value' => null,
                'label' => null,
            ];
        }

        // If it's already an enum instance
        if ($value instanceof $enumClass) {
            return [
                'value' => $value->value,
                'label' => $value->label(),
            ];
        }

        // If it's a string, convert to enum
        if (is_string($value)) {
            $enum = $enumClass::tryFrom($value);
            if ($enum) {
                return [
                    'value' => $enum->value,
                    'label' => $enum->label(),
                ];
            }
        }

        // Fallback
        return [
            'value' => $value,
            'label' => $value,
        ];
    }
}
