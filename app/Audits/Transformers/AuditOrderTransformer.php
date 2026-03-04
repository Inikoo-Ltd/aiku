<?php

namespace App\Audits\Transformers;

use illuminate\Support\Arr;
use App\Models\Ordering\Transaction;

class AuditOrderTransformer {
    public static function transform(array $data) : array
    {
        if(Arr::has($data, 'new_values.collection_address_id')){
            $oldAddressId = Arr::get($data, 'old_values.collection_address_id', []);
            $newAddressId = Arr::get($data, 'new_values.collection_address_id', []);

            $data['old_values']['collection_address'] = is_null($oldAddressId);
            $data['new_values']['collection_address'] = is_null($newAddressId);

            unset($data['old_values']['collection_address_id']);
            unset($data['new_values']['collection_address_id']);
        }

        if(Arr::has($data, 'new_values.internal_notes')){
            $oldNotes = Arr::get($data, 'old_values.internal_notes', []);
            $newNotes = Arr::get($data, 'new_values.internal_notes', []);

            $data['old_values']['private_notes'] = $oldNotes;
            $data['new_values']['private_notes'] = $newNotes;

            unset($data['old_values']['internal_notes']);
            unset($data['new_values']['internal_notes']);
        }

        if(Arr::has($data, 'new_values.discretionary_offers_data')){
            $oldOffers = Arr::get($data, 'old_values.discretionary_offers_data', []);
            $newOffers = Arr::get($data, 'new_values.discretionary_offers_data', []);

            if(is_string($oldOffers)) $oldOffers = json_decode($oldOffers, true) ?? [];
            if(is_string($newOffers)) $newOffers = json_decode($newOffers, true) ?? [];

            $changeItemsIds = [];
            $allKeys = array_unique(array_merge(array_keys($oldOffers), array_keys($newOffers)));

            foreach($allKeys as $key){
                $oldPct = floatval($oldOffers[$key]['percentage'] ?? 0);
                $newPct = floatval($newOffers[$key]['percentage'] ?? 0);

                if($oldPct !== $newPct) {
                    $changeItemsIds[] = $key;
                }
            }

            $formatOffers = function ($offers, $changeItemsIds){
                $formatted = [];

                foreach($changeItemsIds as $itemId){
                    $percentage = isset($offers[$itemId]) ? floatval($offers[$itemId]['percentage'] ?? 0) * 100 : 0;
                    $transaction = Transaction::find($itemId);

                    $itemCode = $transaction ? ($transaction->code ?? $transaction->model?->code ?? $itemId) : $itemId;

                    $formatted[] = "Item {$itemCode}: {$percentage}% off";
                }

                return empty($formatted) ? '0% off' : implode(', ', $formatted);
            };

            $data['old_values']['Discretionary Offers'] = $formatOffers($oldOffers, $changeItemsIds);
            $data['new_values']['Discretionary Offers'] = $formatOffers($newOffers, $changeItemsIds);

            unset($data['old_values']['discretionary_offers_data']);
            unset($data['new_values']['discretionary_offers_data']);
        }

        return $data;
    }
}