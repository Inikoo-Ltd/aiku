<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDeliveryFollowUp;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsInBookInAuthorisation;
use App\Enums\GoodsIn\StockDeliveryFollowUp\StockDeliveryItemFeedbackIssueEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\GoodsIn\StockDeliveryItemFeedback;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * A note on one line of a checked delivery (rotten, broken, wrong item...), one per line, put in the delivery's
 * feedback round and meant for that line's supplier, found from the line's supplier product. Before the round is
 * sent it waits and goes out with it; after, its buyer is told at once. Never touches stock, claims or money.
 */
class StoreStockDeliveryItemFeedback extends OrgAction
{
    use WithGoodsInBookInAuthorisation;

    private StockDeliveryItem $stockDeliveryItem;

    public function handle(StockDeliveryItem $stockDeliveryItem, User $author, array $modelData): StockDeliveryItemFeedback
    {
        $stockDelivery = $stockDeliveryItem->stockDelivery;
        $round         = StoreStockDeliveryFeedback::round($stockDelivery, $author);
        $supplier      = $stockDeliveryItem->supplierProduct?->supplier;

        $note = StockDeliveryItemFeedback::createOrFirst(['stock_delivery_item_id' => $stockDeliveryItem->id], [
            'group_id'                   => $stockDelivery->group_id,
            'organisation_id'            => $stockDelivery->organisation_id,
            'stock_delivery_id'          => $stockDelivery->id,
            'stock_delivery_feedback_id' => $round->id,
            'supplier_id'                => $supplier?->id,
            'agent_id'                   => $supplier?->agent_id ?? $stockDelivery->agent_id,
            'buyer_id'                   => ResolveStockDeliveryBuyer::run($stockDelivery, $stockDeliveryItem->org_stock_id, $supplier?->id),
            'author_id'                  => $author->id,
            'issue'                      => $modelData['issue'],
            'comment'                    => Arr::get($modelData, 'comment'),
        ]);

        if (!$note->wasRecentlyCreated) {
            return $note;
        }

        self::savePhotos($note, Arr::get($modelData, 'photos', []));

        if ($round->refresh()->isSent()) {
            NotifyStockDeliveryFeedback::run($round, $author, collect([$note->load('stockDeliveryItem.orgStock')]), withGeneral: false);
        }

        return $note;
    }

    /**
     * @param array<int, UploadedFile> $photos
     */
    public static function savePhotos(StockDeliveryItemFeedback $note, array $photos): void
    {
        foreach ($photos as $photo) {
            SaveModelAttachment::make()->action($note->stockDelivery, [
                'path'         => $photo->getPathName(),
                'originalName' => $photo->getClientOriginalName(),
                'extension'    => $photo->getClientOriginalExtension(),
                'scope'        => PurchaseOrderAttachmentScopeEnum::FEEDBACK->value,
                'sub_scope'    => self::photoSubScope($note->id),
                'caption'      => __('Delivery feedback'),
            ]);
        }
    }

    public static function photoSubScope(int $noteId): string
    {
        return 'feedback_item:'.$noteId;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function noteRules(): array
    {
        return [
            'issue'   => ['required', Rule::enum(StockDeliveryItemFeedbackIssueEnum::class)],
            'comment' => ['required_if:issue,'.StockDeliveryItemFeedbackIssueEnum::OTHER->value, 'nullable', 'string', 'max:5000'],
        ];
    }

    public function rules(): array
    {
        return [
            ...self::noteRules(),
            'photos'   => ['sometimes', 'array', 'max:10'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:20480'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!in_array($this->stockDeliveryItem->stockDelivery->state, StoreStockDeliveryFeedback::STATES, true)) {
            $validator->errors()->add('issue', __('Feedback can be given once the delivery is checked'));
        }
    }

    public function asController(StockDeliveryItem $stockDeliveryItem, ActionRequest $request): StockDeliveryItemFeedback
    {
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $request);

        return $this->handle($stockDeliveryItem, $request->user(), $this->validatedData);
    }

    public function action(StockDeliveryItem $stockDeliveryItem, User $author, array $modelData): StockDeliveryItemFeedback
    {
        $this->asAction          = true;
        $this->stockDeliveryItem = $stockDeliveryItem;
        $this->initialisation($stockDeliveryItem->organisation, $modelData);

        return $this->handle($stockDeliveryItem, $author, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
