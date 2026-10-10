<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Helpers\Media;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\Response as ImageResponse;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;

class PdfPurchaseOrder extends OrgAction
{
    use WithProcurementAuthorisation;

    private const array PDF_RELATIONS = ['organisation.address', 'currency', 'parent', 'agent', 'purchaseOrderTransactions.supplierProduct.currency', 'purchaseOrderTransactions.orgStock'];

    private const array IMAGE_RELATIONS = ['purchaseOrderTransactions.supplierProduct.image', 'purchaseOrderTransactions.orgStock.tradeUnits.image'];

    private const int IMAGE_EDGE = 160;

    private const int IMAGE_CONCURRENCY = 10;

    public bool $withImages = false;

    /**
     * @var array<string, string|null>
     */
    private array $deliveryAddresses = [];

    public function handle(PurchaseOrder $purchaseOrder): string
    {
        return PDF::loadView('procurement.templates.pdf.purchase-order', $this->viewData($purchaseOrder))->output();
    }

    /**
     * One PDF for the supplier orders of an agent order, a section per supplier order.
     *
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     */
    public function handleMany(Collection $purchaseOrders): string
    {
        (new EloquentCollection($purchaseOrders->all()))->loadMissing(self::PDF_RELATIONS);

        return PDF::loadView('procurement.templates.pdf.purchase-orders', [
            'reference'      => $purchaseOrders->first()->agent_order_reference ?? $purchaseOrders->first()->reference,
            'purchaseOrders' => $purchaseOrders->map(fn (PurchaseOrder $purchaseOrder) => $this->viewData($purchaseOrder))->all(),
        ])->output();
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing($this->withImages ? [...self::PDF_RELATIONS, ...self::IMAGE_RELATIONS] : self::PDF_RELATIONS);

        $counterparty = match (true) {
            $purchaseOrder->isAgentOrder()                => $purchaseOrder->agent,
            $purchaseOrder->parent instanceof OrgSupplier => $purchaseOrder->parent->supplier,
            $purchaseOrder->parent instanceof OrgAgent    => $purchaseOrder->parent->agent,
            $purchaseOrder->parent instanceof OrgPartner  => $purchaseOrder->parent->partner,
            default                                       => null,
        };

        $lines = $purchaseOrder->purchaseOrderTransactions
            ->reject(fn ($transaction) => (float)$transaction->quantity_ordered <= 0)
            ->sortBy(fn ($transaction) => $transaction->supplierProduct?->code)
            ->values();

        return [
            'purchaseOrder'   => $purchaseOrder,
            'organisation'    => $purchaseOrder->organisation,
            'counterparty'    => $counterparty,
            'deliveryAddress' => $this->deliveryAddress($purchaseOrder),
            'lines'           => $lines,
            'images'          => $this->withImages ? $this->lineImages($lines) : null,
            'totals'          => $lines->groupBy(fn ($transaction) => $transaction->supplierProduct?->currency?->code ?? $purchaseOrder->currency->code)
                ->map(fn ($transactions) => $transactions->sum(fn ($transaction) => (float)$transaction->net_amount)),
        ];
    }

    /**
     * Each line's product image as a small embedded JPEG, keyed by line id. Read through imgproxy, as
     * Media::getBase64Image does, so it works whether the original is on local disk or in object
     * storage and a page of lines does not embed every full-size original. A line whose image
     * cannot be fetched is left out and prints without one.
     *
     * @param  Collection<int, PurchaseOrderTransaction>  $lines
     * @return array<int, string>
     */
    private function lineImages(Collection $lines): array
    {
        $urls = $lines
            ->mapWithKeys(fn (PurchaseOrderTransaction $line) => [$line->id => $this->lineImage($line)])
            ->filter()
            ->map(fn (Media $media) => GetImgProxyUrl::run($media->getImage()->resize(self::IMAGE_EDGE, self::IMAGE_EDGE)->extension('jpg')));

        if ($urls->isEmpty()) {
            return [];
        }

        $responses = Http::pool(fn ($pool) => $urls->map(fn (string $url, int $lineId) => $pool->as((string) $lineId)->timeout(15)->get($url))->all(), self::IMAGE_CONCURRENCY);

        return collect($responses)
            ->filter(fn ($response) => $response instanceof ImageResponse && $response->successful() && $response->body() !== '')
            ->mapWithKeys(fn ($response, $lineId) => [(int) $lineId => 'data:image/jpeg;base64,'.base64_encode($response->body())])
            ->all();
    }

    private function lineImage(PurchaseOrderTransaction $line): ?Media
    {
        return $line->supplierProduct?->image
            ?? $line->orgStock?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image)?->image;
    }

    private function deliveryAddress(PurchaseOrder $purchaseOrder): ?string
    {
        $override = Arr::get($purchaseOrder->data, 'delivery_address');

        $key = $purchaseOrder->organisation_id.'|'.$override;

        if (! array_key_exists($key, $this->deliveryAddresses)) {
            $this->deliveryAddresses[$key] = ResolvePurchaseOrderDeliveryAddress::run($purchaseOrder->organisation, $override);
        }

        return $this->deliveryAddresses[$key];
    }

    public function filenameFor(string $reference): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $reference).'.pdf';
    }

    public function filename(PurchaseOrder $purchaseOrder): string
    {
        return $this->filenameFor($purchaseOrder->reference);
    }

    public function inAgentOrder(Organisation $organisation, OrgAgent $orgAgent, string $agentOrderReference, ActionRequest $request): Response
    {
        abort_unless($orgAgent->organisation_id === $organisation->id, 404);
        $this->initialisation($organisation, $request);
        $this->withImages = $request->boolean('with_images');

        $purchaseOrders = PurchaseOrder::inAgentOrder($orgAgent->organisation_id, $orgAgent->agent_id, $agentOrderReference)
            ->with(self::PDF_RELATIONS)
            ->orderBy('parent_code')
            ->get();
        abort_if($purchaseOrders->isEmpty(), 404);

        return response($this->handleMany($purchaseOrders), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filenameFor($agentOrderReference).'"');
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        abort_unless($purchaseOrder->organisation_id === $organisation->id || $organisation->type === OrganisationTypeEnum::AGENT, 404);
        $this->initialisation($organisation, $request);
        $this->withImages = $request->boolean('with_images');

        return response($this->handle($purchaseOrder), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($purchaseOrder).'"');
    }
}
