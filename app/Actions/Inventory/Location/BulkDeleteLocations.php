<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026 13:20:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Location;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseSupervisorAuthorisation;
use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class BulkDeleteLocations extends OrgAction
{
    use WithWarehouseSupervisorAuthorisation;

    public static function confirmationPhrase(int $count): string
    {
        return 'DELETE '.$count.' '.($count === 1 ? 'LOCATION' : 'LOCATIONS');
    }

    /**
     * @param  array<int, int>  $locationIds
     *
     * @throws \Throwable
     */
    public function handle(Warehouse $warehouse, array $locationIds): int
    {
        /** @var Collection<int, Location> $locations */
        $locations = $warehouse->locations()->whereIn('id', $locationIds)->get();

        $notEmpty = $locations->reject(fn (Location $location) => DeleteLocation::isEmpty($location));

        if ($notEmpty->isNotEmpty()) {
            throw ValidationException::withMessages([
                'locations' => __('These locations still have stock or pallets, move it before deleting them: :codes', [
                    'codes' => $notEmpty->pluck('code')->implode(', ')
                ])
            ]);
        }

        DB::transaction(function () use ($locations) {
            foreach ($locations as $location) {
                DeleteLocation::make()->action($location);
            }
        });

        return $locations->count();
    }

    public function rules(): array
    {
        $locations = $this->get('locations');

        return [
            'locations'    => ['required', 'array', 'min:1'],
            'locations.*'  => [
                'integer',
                Rule::exists('locations', 'id')->where('warehouse_id', $this->warehouse->id)->whereNull('deleted_at')
            ],
            'confirmation' => ['required', 'string', Rule::in([self::confirmationPhrase(is_array($locations) ? count($locations) : 0)])],
        ];
    }

    public function getValidationMessages(): array
    {
        $locations = $this->get('locations');

        return [
            'confirmation.in' => __('Type :phrase to confirm.', ['phrase' => self::confirmationPhrase(is_array($locations) ? count($locations) : 0)]),
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Warehouse $warehouse, ActionRequest $request): int
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData['locations']);
    }

    public function htmlResponse(int $deleted): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Locations deleted'),
            'description' => trans_choice(':count location deleted|:count locations deleted', $deleted, ['count' => $deleted]),
        ]);
    }
}
