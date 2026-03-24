<?php

namespace App\Http\Resources\Helpers;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 *
 * @property string $contact
 * @property string $phone_number
 * @property string $status
 */
class EmergencyFormFieldsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $resource = $this->resource;
        if (is_string($resource)) {
            $decoded = json_decode($resource, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $resource = $decoded;
            } else {
                return [
                    'contact' => $resource ?: null,
                    'phone_number' => null,
                    'address' => null,
                    'status' => null,
                ];
            }
        }

        return [
            'contact' => Arr::get($resource, 'contact'),
            'phone_number' => Arr::get($resource, 'phone_number'),
            'address' => Arr::get($resource, 'address'),
            'status' => Arr::get($resource, 'status')
        ];
    }

}
