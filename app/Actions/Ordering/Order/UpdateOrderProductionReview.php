<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 20:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderProductionReview extends OrgAction
{
    public function handle(Order $order, bool $reviewed, User $reviewer): Order
    {
        $order->update([
            'production_reviewed_at' => $reviewed ? now() : null,
            'production_reviewed_by' => $reviewed ? $reviewer->id : null,
        ]);

        return $order;
    }

    public static function isUsedBy(Organisation $organisation): bool
    {
        return $organisation->productions->isNotEmpty();
    }

    public static function canReview(User $user, Shop $shop): bool
    {
        $permissions = ["orders.$shop->id.edit"];
        foreach ($shop->organisation->productions->pluck('id') as $productionId) {
            $permissions[] = "productions_operations.$productionId.orchestrate";
        }

        return $user->authTo($permissions);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (!self::isUsedBy($this->organisation)) {
            return false;
        }

        if (!isset($this->shop)) {
            return true;
        }

        return self::canReview($request->user(), $this->shop);
    }

    public function rules(): array
    {
        return [
            'reviewed'    => ['required', 'boolean'],
            'order_ids'   => ['sometimes', 'array', 'max:500'],
            'order_ids.*' => ['integer', Rule::exists('orders', 'id')->where('organisation_id', $this->organisation->id)],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData['reviewed'], $request->user());
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): int
    {
        $this->initialisation($organisation, $request);

        $orders = Order::where('organisation_id', $organisation->id)->whereIn('id', Arr::get($this->validatedData, 'order_ids', []))->get();

        foreach ($orders->pluck('shop')->unique('id') as $shop) {
            abort_unless(self::canReview($request->user(), $shop), 403);
        }

        foreach ($orders as $order) {
            $this->handle($order, $this->validatedData['reviewed'], $request->user());
        }

        return $orders->count();
    }
}
