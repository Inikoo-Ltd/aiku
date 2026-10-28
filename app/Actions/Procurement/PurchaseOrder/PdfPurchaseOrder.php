<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\SupplierAgreedTerm\GetPurchaseOrderAgreedTerms;
use App\Actions\Procurement\SupplierAgreedTerm\UI\GetPurchaseOrderAgreement;
use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\Helpers\Media;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\Response as ImageResponse;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfPurchaseOrder extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithAgentOrganisation;

    private const array PDF_RELATIONS = ['organisation.address', 'currency', 'parent', 'agent', 'purchaseOrderTransactions.supplierProduct.currency', 'purchaseOrderTransactions.orgStock'];

    private const array IMAGE_RELATIONS = ['purchaseOrderTransactions.supplierProduct.image', 'purchaseOrderTransactions.orgStock.tradeUnits.image'];

    private const int IMAGE_EDGE = 160;

    private const int IMAGE_CONCURRENCY = 10;

    public bool $withImages = false;

    public bool $forAgent = false;

    /**
     * @var array<string, string|null>
     */
    private array $deliveryAddresses = [];

    /**
     * @var array<int, Collection>
     */
    private array $agreedTerms = [];

    /**
     * @var array<int, Collection>
     */
    private array $agreedNotes = [];

    /**
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     */
    private function loadAgreements(Collection $purchaseOrders): void
    {
        $this->agreedTerms = GetPurchaseOrderAgreedTerms::make()->forMany($purchaseOrders, forDocument: true);
        $this->agreedNotes = GetPurchaseOrderAgreement::make()->documentNotesForMany($purchaseOrders);
    }

    public function handle(PurchaseOrder $purchaseOrder): string
    {
        $this->loadAgreements(collect([$purchaseOrder]));

        return PDF::loadView('procurement.templates.pdf.purchase-order', $this->viewData($purchaseOrder))->output();
    }

    /**
     * A cancelled line is printed only on an order that is itself cancelled.
     *
     * @return Collection<int, PurchaseOrderTransaction>
     */
    public function lines(PurchaseOrder $purchaseOrder): Collection
    {
        $isCancelled = $purchaseOrder->state === PurchaseOrderStateEnum::CANCELLED;

        return $purchaseOrder->purchaseOrderTransactions
            ->reject(fn (PurchaseOrderTransaction $transaction) => (float)$transaction->quantity_ordered <= 0
                || (!$isCancelled && $transaction->state === PurchaseOrderTransactionStateEnum::CANCELLED))
            ->sortBy(fn (PurchaseOrderTransaction $transaction) => $transaction->supplierProduct?->code)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing($this->withImages ? [...self::PDF_RELATIONS, ...self::IMAGE_RELATIONS] : self::PDF_RELATIONS);

        $isAgentCopy = $this->forAgent && $purchaseOrder->isAgentOrder();

        $counterparty = match (true) {
            $isAgentCopy                                  => $purchaseOrder->parent->supplier,
            $purchaseOrder->isAgentOrder()                => $purchaseOrder->agent,
            $purchaseOrder->parent instanceof OrgSupplier => $purchaseOrder->parent->supplier,
            $purchaseOrder->parent instanceof OrgAgent    => $purchaseOrder->parent->agent,
            $purchaseOrder->parent instanceof OrgPartner  => $purchaseOrder->parent->partner,
            default                                       => null,
        };

        $lines = $this->lines($purchaseOrder);

        return [
            'purchaseOrder'   => $purchaseOrder,
            'isAgentCopy'     => $isAgentCopy,
            'organisation'    => $isAgentCopy ? $purchaseOrder->agent->organisation : $purchaseOrder->organisation,
            'counterparty'    => $counterparty,
            'deliveryAddress' => $isAgentCopy ? ResolvePurchaseOrderDeliveryAddress::run($purchaseOrder->agent->organisation) : $this->deliveryAddress($purchaseOrder),
            'lines'           => $lines,
            'images'          => $this->withImages ? $this->lineImages($lines) : null,
            'agreedTerms'     => $this->agreedTerms[$purchaseOrder->id] ?? collect(),
            'agreedNotes'     => $this->agreedNotes[$purchaseOrder->id] ?? collect(),
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
    public function lineImages(Collection $lines): array
    {
        return $this->mediaImages(
            $lines->mapWithKeys(fn (PurchaseOrderTransaction $line) => [$line->id => $this->lineImage($line)])->filter(),
            self::IMAGE_EDGE
        );
    }

    /**
     * Images shrunk through imgproxy, fetched together, as data URIs keyed like the media given. Keys whose fetch
     * failed are left out.
     *
     * @param  Collection<int, Media>  $media
     * @return array<int, string>
     */
    public function mediaImages(Collection $media, int $edge): array
    {
        $urls = $media->map(fn (Media $image) => GetImgProxyUrl::run($image->getImage()->resize($edge, $edge)->extension('jpg')));

        if ($urls->isEmpty()) {
            return [];
        }

        $responses = Http::pool(fn ($pool) => $urls->map(fn (string $url, int $key) => $pool->as((string) $key)->timeout(15)->get($url))->all(), self::IMAGE_CONCURRENCY);

        return collect($responses)
            ->filter(fn ($response) => $response instanceof ImageResponse && $response->successful() && $response->body() !== '')
            ->mapWithKeys(fn ($response, $key) => [(int) $key => 'data:image/jpeg;base64,'.base64_encode($response->body())])
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

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);
        $this->authorizeProcurementRecord($purchaseOrder);
        $this->withImages = $request->boolean('with_images');
        $this->forAgent   = $this->organisationAgentSupplies($purchaseOrder);

        return response($this->handle($purchaseOrder), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($purchaseOrder).'"');
    }
}
