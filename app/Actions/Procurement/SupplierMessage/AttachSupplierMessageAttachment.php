<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierMessage;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\OrgAction;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\SupplierMessage;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AttachSupplierMessageAttachment extends OrgAction
{
    private const int MIN_REFERENCE_LENGTH_TO_SUGGEST = 5;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * A supplier's document belongs to the order and to the goods it covers, so a file put on a
     * purchase order also goes on its deliveries, and one put on a delivery also goes on its orders.
     *
     * @return Collection<int, PurchaseOrder|StockDelivery>
     */
    public function handle(SupplierMessage $supplierMessage, int $index, PurchaseOrder|StockDelivery $target, PurchaseOrderAttachmentScopeEnum $scope): Collection
    {
        $attachment = $supplierMessage->attachments[$index];

        $related = $target instanceof PurchaseOrder ? $target->stockDeliveries : $target->purchaseOrders;

        $path = tempnam(sys_get_temp_dir(), 'supplier-attachment-');

        try {
            file_put_contents($path, DownloadSupplierMessageAttachment::make()->content($supplierMessage, $attachment));

            $attachmentData = [
                'path'         => $path,
                'originalName' => $attachment['name'],
                'extension'    => pathinfo($attachment['name'], PATHINFO_EXTENSION),
                'caption'      => pathinfo($attachment['name'], PATHINFO_FILENAME),
                'scope'        => $scope->value,
            ];

            $media = SaveModelAttachment::make()->action($target, $attachmentData);

            foreach ($related as $model) {
                SaveModelAttachment::make()->action($model, $attachmentData);
            }
        } finally {
            @unlink($path);
        }

        $attachments                     = $supplierMessage->attachments;
        $attachments[$index]['attached_media_id'] = $media->id;
        $supplierMessage->update(['attachments' => $attachments]);

        return collect([$target])->concat($related);
    }

    /**
     * @return array<int, array{model_type: string, reference: string, route: array{name: string, parameters: array<int, string>}}>
     */
    public static function attachedTo(Organisation $organisation, ?int $mediaId): array
    {
        if (! $mediaId) {
            return [];
        }

        $links = DB::table('model_has_attachments')
            ->where('media_id', $mediaId)
            ->whereIn('model_type', ['PurchaseOrder', 'StockDelivery'])
            ->get(['model_type', 'model_id']);

        $purchaseOrders = PurchaseOrder::where('organisation_id', $organisation->id)
            ->whereIn('id', $links->where('model_type', 'PurchaseOrder')->pluck('model_id'))
            ->get(['id', 'slug', 'reference'])
            ->map(fn (PurchaseOrder $purchaseOrder) => [
                'model_type' => 'purchase_order',
                'reference'  => $purchaseOrder->reference,
                'route'      => ['name' => 'grp.org.procurement.purchase_orders.show', 'parameters' => [$organisation->slug, $purchaseOrder->slug]],
            ]);

        $stockDeliveries = StockDelivery::where('organisation_id', $organisation->id)
            ->whereIn('id', $links->where('model_type', 'StockDelivery')->pluck('model_id'))
            ->get(['id', 'slug', 'reference'])
            ->map(fn (StockDelivery $stockDelivery) => [
                'model_type' => 'stock_delivery',
                'reference'  => $stockDelivery->reference,
                'route'      => ['name' => 'grp.org.procurement.stock_deliveries.show', 'parameters' => [$organisation->slug, $stockDelivery->slug]],
            ]);

        return $purchaseOrders->concat($stockDeliveries)->values()->all();
    }

    /**
     * The counterpart's live orders and deliveries, newest first, as choices for the picker.
     *
     * @return array<int, array{value: string, label: string, reference: string}>
     */
    public static function targetOptions(SupplierMessage $supplierMessage): array
    {
        $counterpart = $supplierMessage->counterpart();

        if (! $counterpart) {
            return [];
        }

        $purchaseOrders = PurchaseOrder::where('parent_type', class_basename($counterpart))
            ->where('parent_id', $counterpart->id)
            ->whereNotIn('state', [PurchaseOrderStateEnum::CANCELLED, PurchaseOrderStateEnum::NOT_RECEIVED])
            ->orderByDesc('date')
            ->limit(50)
            ->get(['id', 'reference', 'state'])
            ->map(fn (PurchaseOrder $purchaseOrder) => [
                'value'     => 'purchase_order:'.$purchaseOrder->id,
                'label'     => __('Purchase order').' · '.$purchaseOrder->reference.' · '.PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value],
                'reference' => $purchaseOrder->reference,
            ]);

        $stockDeliveries = StockDelivery::where('parent_type', class_basename($counterpart))
            ->where('parent_id', $counterpart->id)
            ->whereNotIn('state', [StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED])
            ->orderByDesc('date')
            ->limit(50)
            ->get(['id', 'reference', 'state'])
            ->map(fn (StockDelivery $stockDelivery) => [
                'value'     => 'stock_delivery:'.$stockDelivery->id,
                'label'     => __('Stock delivery').' · '.$stockDelivery->reference.' · '.StockDeliveryStateEnum::labels()[$stockDelivery->state->value],
                'reference' => $stockDelivery->reference,
            ]);

        return $purchaseOrders->concat($stockDeliveries)->values()->all();
    }

    /**
     * The order the message is known to answer wins; otherwise the first order or delivery whose
     * reference appears in the file name, subject or text. Short references are skipped because
     * they turn up in any text by chance.
     *
     * @param  array<int, array{value: string, label: string, reference: string}>  $options
     */
    public static function suggestedTarget(SupplierMessage $supplierMessage, string $fileName, array $options): ?string
    {
        $knownOrder = 'purchase_order:'.$supplierMessage->purchase_order_id;

        if ($supplierMessage->purchase_order_id && in_array($knownOrder, array_column($options, 'value'), true)) {
            return $knownOrder;
        }

        $haystacks = array_map(
            fn (?string $text) => Str::lower((string) $text),
            [$fileName, $supplierMessage->subject, $supplierMessage->body_text]
        );

        foreach ($haystacks as $haystack) {
            foreach ($options as $option) {
                if (mb_strlen($option['reference']) >= self::MIN_REFERENCE_LENGTH_TO_SUGGEST && Str::contains($haystack, Str::lower($option['reference']))) {
                    return $option['value'];
                }
            }
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'target' => ['required', 'string', 'regex:/^(purchase_order|stock_delivery):\d+$/'],
            'scope'  => ['required', Rule::enum(PurchaseOrderAttachmentScopeEnum::class)],
        ];
    }

    public function findTarget(Organisation $organisation, string $target): PurchaseOrder|StockDelivery
    {
        [$type, $id] = explode(':', $target);

        $query = $type === 'purchase_order' ? PurchaseOrder::query() : StockDelivery::query();

        return $query->where('organisation_id', $organisation->id)->findOrFail($id);
    }

    public function asController(Organisation $organisation, SupplierMessage $supplierMessage, int $index, ActionRequest $request): Collection
    {
        $this->initialisation($organisation, $request);

        abort_unless($supplierMessage->organisation_id === $organisation->id, 404);
        abort_unless(isset($supplierMessage->attachments[$index]), 404);

        return $this->handle(
            $supplierMessage,
            $index,
            $this->findTarget($organisation, $this->validatedData['target']),
            PurchaseOrderAttachmentScopeEnum::from($this->validatedData['scope'])
        );
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }
}
