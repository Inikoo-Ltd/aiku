<?php

/*
 * author Arya Permana - Kirin
 * created on 14-10-2024-11h-23m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $ulid
 * @property string $reference
 * @property string $name
 * @property string $contact_name
 * @property string $company_name
 * @property string $email
 * @property string $phone
 * @property string $created_at
 * @property string $updated_at
 */
class ProductFavouritesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'contact_name' => $this->contact_name,
            'reference' => $this->reference,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
