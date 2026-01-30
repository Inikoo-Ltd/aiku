<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 29 Jan 2026 09:29:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Helpers\Address;

use App\Actions\OrgAction;
use App\Services\GeocoderService;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\JsonResponse;

class GetGeocode extends OrgAction
{
    use AsAction;

    public function handle(array $validatedData): ?array
    {
        $geocoder = new GeocoderService();

        if (isset($validatedData['latitude'], $validatedData['longitude'])) {
            $lat = (float) $validatedData['latitude'];
            $lng = (float) $validatedData['longitude'];

            $result = $geocoder->reverseGeocode($lat, $lng);

            if ($result) {
                $result['latitude'] = $lat;
                $result['longitude'] = $lng;
            }

            return $result;
        }

        return $geocoder->geocode($validatedData['location']);
    }

    public function rules(): array
    {
        return [
            'location'  => 'required_without_all:latitude,longitude|nullable|string',
            'latitude'  => 'required_without:location|nullable|numeric',
            'longitude' => 'required_without:location|nullable|numeric',
        ];
    }

    public function asController(ActionRequest $request): ?array
    {
        if (is_array($request)) {
            $this->setRawAttributes($request);
        } else {
            $this->fillFromRequest($request);
        }
        return $this->handle($this->validateAttributes());
    }

    public function jsonResponse(?array $result): JsonResponse
    {
        return response()->json($result ?? []);
    }
}
