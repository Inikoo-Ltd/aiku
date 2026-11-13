<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryFollowUp\StockDeliveryFeedbackTopicEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Enums\UI\Procurement\StockDeliveryTabsEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryFeedback;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use App\Notifications\StockDeliveryFeedbackNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Receiving staff tell a supplier how its part of a delivery arrived, once per supplier on the delivery: a second
 * submit gets the first feedback back and tells nobody again. It is only a message: stock, claims, discrepancy
 * outcomes and money are never touched, and nothing is ever rated by itself. The buyer is told in aiku; the
 * supplier reads it on its portal.
 */
class StoreStockDeliveryFeedback extends OrgAction
{
    use WithGoodsInBookInAuthorisation;

    public const array STATES = [
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
        StockDeliveryStateEnum::BOOKED_IN,
        StockDeliveryStateEnum::PLACED,
    ];

    private StockDelivery $stockDelivery;

    public function handle(StockDelivery $stockDelivery, User $author, array $modelData): StockDeliveryFeedback
    {
        $supplierId = Arr::get($modelData, 'supplier_id');

        $feedback = StockDeliveryFeedback::createOrFirst(['stock_delivery_id' => $stockDelivery->id, 'supplier_id' => $supplierId], [
            'group_id'        => $stockDelivery->group_id,
            'organisation_id' => $stockDelivery->organisation_id,
            'agent_id'        => $stockDelivery->agent_id,
            'org_partner_id'  => $stockDelivery->parent_type === 'OrgPartner' ? $stockDelivery->parent_id : null,
            'buyer_id'        => ResolveStockDeliveryBuyer::run($stockDelivery, null, $supplierId),
            'author_id'       => $author->id,
            'rating'          => (int) $modelData['rating'],
            'topics'          => array_values(array_unique(Arr::get($modelData, 'topics', []))),
            'comment'         => Arr::get($modelData, 'comment'),
        ]);

        if (!$feedback->wasRecentlyCreated) {
            return $feedback;
        }

        self::savePhotos($feedback, Arr::get($modelData, 'photos', []));
        $this->notifyBuyer($feedback, $author);

        return $feedback;
    }

    private function notifyBuyer(StockDeliveryFeedback $feedback, User $author): void
    {
        $buyer = $feedback->buyer;
        if (!$buyer || !$buyer->status || $buyer->id === $author->id) {
            return;
        }

        $stockDelivery = $feedback->stockDelivery;
        $title         = __(':stars stars for :supplier on delivery :reference', [
            'stars'     => $feedback->rating,
            'supplier'  => $feedback->supplier?->name ?? $stockDelivery->parent_name,
            'reference' => $stockDelivery->reference,
        ]);
        $route = [
            'name'       => 'grp.org.procurement.stock_deliveries.show',
            'parameters' => ['organisation' => $stockDelivery->organisation->slug, 'stockDelivery' => $stockDelivery->slug, 'tab' => StockDeliveryTabsEnum::FEEDBACK->value],
        ];

        NotifyUserOnChosenChannels::run(
            $buyer,
            UserNotificationEnum::DELIVERY_FEEDBACK,
            new StockDeliveryFeedbackNotification($title, (string) $feedback->comment, UserNotificationEnum::DELIVERY_FEEDBACK->value, (string) $feedback->id, $route),
            $title,
            (string) $feedback->comment,
            route($route['name'], $route['parameters'])
        );
    }

    /**
     * @param array<int, UploadedFile> $photos
     */
    public static function savePhotos(StockDeliveryFeedback $feedback, array $photos): void
    {
        foreach ($photos as $photo) {
            SaveModelAttachment::make()->action($feedback->stockDelivery, [
                'path'         => $photo->getPathName(),
                'originalName' => $photo->getClientOriginalName(),
                'extension'    => $photo->getClientOriginalExtension(),
                'scope'        => PurchaseOrderAttachmentScopeEnum::FEEDBACK->value,
                'sub_scope'    => 'feedback:'.$feedback->id,
                'caption'      => __('Delivery feedback'),
            ]);
        }
    }

    /**
     * @return Collection<int, Supplier>
     */
    public static function suppliersOn(StockDelivery $stockDelivery): Collection
    {
        return Supplier::whereIn('id', $stockDelivery->items()->join('supplier_products', 'supplier_products.id', 'stock_delivery_items.supplier_product_id')->select('supplier_products.supplier_id'))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'agent_id']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function feedbackRules(): array
    {
        return [
            'rating'   => ['required', 'integer', 'between:1,5'],
            'topics'   => ['sometimes', 'array'],
            'topics.*' => [Rule::enum(StockDeliveryFeedbackTopicEnum::class)],
            'comment'  => ['required_unless:rating,5', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function rules(): array
    {
        return [
            ...self::feedbackRules(),
            'supplier_id' => ['sometimes', 'nullable', 'integer'],
            'photos'      => ['sometimes', 'array', 'max:10'],
            'photos.*'    => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:20480'],
        ];
    }

    public function prepareForValidation(): void
    {
        if (!$this->has('supplier_id')) {
            $suppliers = self::suppliersOn($this->stockDelivery);
            $this->set('supplier_id', $suppliers->count() === 1 ? $suppliers->first()->id : null);
        }
    }

    public function afterValidator(Validator $validator): void
    {
        if (!in_array($this->stockDelivery->state, self::STATES, true)) {
            $validator->errors()->add('rating', __('Feedback can be given once the delivery is checked'));
        }

        $suppliers  = self::suppliersOn($this->stockDelivery)->pluck('id');
        $supplierId = $this->get('supplier_id');
        if ($suppliers->isNotEmpty() && !$suppliers->contains($supplierId)) {
            $validator->errors()->add('supplier_id', __('Choose the supplier this feedback is about'));
        }
        if ($suppliers->isEmpty() && $supplierId) {
            $validator->errors()->add('supplier_id', __('This delivery has no supplier to choose'));
        }
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): StockDeliveryFeedback
    {
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $request);

        return $this->handle($stockDelivery, $request->user(), $this->validatedData);
    }

    public function action(StockDelivery $stockDelivery, User $author, array $modelData): StockDeliveryFeedback
    {
        $this->asAction      = true;
        $this->stockDelivery = $stockDelivery;
        $this->initialisation($stockDelivery->organisation, $modelData);

        return $this->handle($stockDelivery, $author, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
