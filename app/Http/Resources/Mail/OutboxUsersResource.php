<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 05-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Mail;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $slug
 * @property string $name
 * @property string $price
 * @property string $description
 * @property mixed $type
 * @property mixed $auto_assign_asset
 * @property mixed $auto_assign_asset_type
 * @property mixed $currency_code
 * @property mixed $unit
 * @property mixed $state
 * @property int $quantity
 * @property int $pallet_delivery_id
 * @property mixed $auto_assign_trigger
 * @property mixed $auto_assign_subject
 * @property mixed $auto_assign_subject_type
 * @property bool $auto_assign_status
 * @property mixed $is_auto_assign
 * @property mixed $historic_asset_id
 */
class OutboxUsersResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'contact_name' => $this->contact_name,
            'disabled' => $this->email ? false : true,
        ];
    }
}
