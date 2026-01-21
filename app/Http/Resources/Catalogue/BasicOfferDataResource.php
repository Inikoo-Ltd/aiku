<?php

/*
 * author Louis Perez
 * created on 19-01-2026-16h-50m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;

/**
 * @property int $shop_id
 * @property int $offer_campaign_id
 * @property string $slug
 * @property string $code
 * @property string $data
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 *
 */
class BasicOfferDataResource extends JsonResource
{
    public function toArray($request): array
    {
        $offer = $this;
        
        $allowances      = [];
        $offerAllowances = $offer->offerAllowances->where('status', true);
        foreach ($offerAllowances as $offerAllowance) {
            if($offerAllowance && $offerAllowance->class) {
                $allowances[] = [
                    'class' => $offerAllowance->class->value,
                    'type'  => $offerAllowance->type->value,
                    'label' => $offerAllowance->type == OfferAllowanceType::PERCENTAGE_OFF ? percentage($offerAllowance->data['percentage_off'], 1) : ''
                ];
            }
        }

        $offerData = [
            'state'      => $offer->state->value,
            'duration'   => $offer->duration->value,
            'label'      => $offer->label ?? $offer->name,
            'allowances' => $allowances,
            'note'       => ''
        ];

        if ($offer->duration->value == OfferDurationEnum::INTERVAL) {
            $offerData['start_at'] = $offer->start_at;
            $offerData['end_at']   = $offer->end_at;
        }
        preg_match('/percentage_off:([0-9]*\.?[0-9]+)/', $this->allowance_signature, $matches);
        
        return $offerData;
    }
}
