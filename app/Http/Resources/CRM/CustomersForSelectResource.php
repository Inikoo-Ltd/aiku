<?php

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $reference
 * @property string $name
 * @property string $email
 */
class CustomersForSelectResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'slug'      => $this->slug,
            'reference' => $this->reference,
            'name'      => $this->name,
            'email'     => $this->email,
        ];
    }
}
