<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryFollowUp\StockDeliveryFeedbackTopicEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryFeedback;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Receiving staff send the feedback round of a delivery, once per delivery: the stars, topics, comment and photos are
 * for whoever sent it (the agent of a consolidated container, else its only supplier or partner), and the notes
 * already put on single lines go out with it to their own suppliers. A second submit gets the round back and tells
 * nobody again. It is only a message: stock, claims, discrepancy outcomes and money are never touched. The buyers are
 * told in aiku; suppliers read their part on their portal, the agent on the delivery.
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
        $roundId = self::round($stockDelivery, $author)->id;
        $sent    = DB::transaction(function () use ($roundId, $author, $modelData) {
            $round = StockDeliveryFeedback::lockForUpdate()->findOrFail($roundId);
            if ($round->isSent()) {
                return null;
            }

            $round->update([
                'author_id' => $author->id,
                'rating'    => (int) $modelData['rating'],
                'topics'    => array_values(array_unique(Arr::get($modelData, 'topics', []))),
                'comment'   => Arr::get($modelData, 'comment'),
                'sent_at'   => now(),
            ]);

            return $round;
        });

        if (!$sent) {
            return StockDeliveryFeedback::findOrFail($roundId);
        }

        self::savePhotos($sent, Arr::get($modelData, 'photos', []));
        NotifyStockDeliveryFeedback::run($sent, $author, $sent->items()->with('stockDeliveryItem.orgStock')->get(), withGeneral: true);

        return $sent->refresh();
    }

    /**
     * The delivery's round, made as a draft (no stars, not sent) the first time anyone gives feedback on it.
     */
    public static function round(StockDelivery $stockDelivery, User $author): StockDeliveryFeedback
    {
        $supplierId = self::addressedSupplierId($stockDelivery);

        return StockDeliveryFeedback::createOrFirst(['stock_delivery_id' => $stockDelivery->id], [
            'group_id'        => $stockDelivery->group_id,
            'organisation_id' => $stockDelivery->organisation_id,
            'supplier_id'     => $supplierId,
            'agent_id'        => $stockDelivery->agent_id,
            'org_partner_id'  => $stockDelivery->parent_type === 'OrgPartner' ? $stockDelivery->parent_id : null,
            'buyer_id'        => ResolveStockDeliveryBuyer::run($stockDelivery, null, $supplierId),
            'author_id'       => $author->id,
        ]);
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
     * The supplier the general part is for: the delivery's own supplier on a direct delivery, even when some lines
     * come from another supplier, else the only supplier of its lines; null leaves it to the agent or partner.
     */
    public static function addressedSupplierId(StockDelivery $stockDelivery): ?int
    {
        if ($stockDelivery->supplier_id && !$stockDelivery->agent_id) {
            return $stockDelivery->supplier_id;
        }
        $suppliers = self::suppliersOn($stockDelivery);

        return $suppliers->count() === 1 ? $suppliers->first()->id : null;
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
            'photos'      => ['sometimes', 'array', 'max:10'],
            'photos.*'    => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:20480'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!in_array($this->stockDelivery->state, self::STATES, true)) {
            $validator->errors()->add('rating', __('Feedback can be given once the delivery is checked'));
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
