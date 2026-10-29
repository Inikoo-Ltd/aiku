<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-16 10:45
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentGoodsReceipt;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithAgentWarehouseAuthorisation;
use App\Models\Helpers\Media;
use App\Models\Procurement\AgentGoodsReceipt;
use App\Models\Procurement\AgentGoodsReceiptItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Throwable;

/**
 * The receipt note the agent hands to the supplier for one goods receipt: what arrived at the agent's warehouse, from
 * which purchase orders, and the rejects, signed by whoever delivered and whoever received.
 */
class PdfAgentGoodsReceiptNote extends OrgAction
{
    use WithAgentOrganisation;
    use WithAgentWarehouseAuthorisation;

    public function handle(AgentGoodsReceipt $receipt): string
    {
        $receipt->loadMissing([
            'supplier:id,name',
            'items.supplierProduct:id,code,name',
            'items.purchaseOrder:id,reference',
            'items.purchaseOrderTransaction:id,quantity_ordered',
        ]);

        $agentOrganisation = $receipt->agent->organisation;
        $agentOrganisation->loadMissing(['address', 'image']);

        return PDF::loadView('procurement.templates.pdf.agent-goods-receipt-note', [
            'receipt'      => $receipt,
            'agent'        => $agentOrganisation,
            'logo'         => $this->logo($agentOrganisation->image),
            'supplierName' => $receipt->supplier?->name,
            'lines'        => $this->lines($receipt->items),
        ])->output();
    }

    /**
     * @return Collection<int, array{purchase_order: ?string, code: ?string, name: ?string, ordered: float, received: float, rejected: ?float, remarks: ?string}>
     */
    private function lines(Collection $items): Collection
    {
        return $items
            ->sortBy(fn (AgentGoodsReceiptItem $item) => [$item->purchaseOrder?->reference, $item->supplierProduct?->code])
            ->values()
            ->map(fn (AgentGoodsReceiptItem $item) => [
                'purchase_order' => $item->purchaseOrder?->reference,
                'code'           => $item->supplierProduct?->code,
                'name'           => $item->supplierProduct?->name,
                'ordered'        => (float) $item->purchaseOrderTransaction?->quantity_ordered,
                'received'       => (float) $item->quantity,
                'rejected'       => $item->quantity_rejected === null ? null : (float) $item->quantity_rejected,
                'remarks'        => $item->reject_reason,
            ]);
    }

    /**
     * The file itself when it is on this server, else the picture from the image server; no logo when neither is there.
     */
    private function logo(?Media $image): ?string
    {
        if (!$image) {
            return null;
        }

        $path = $image->getPath();
        if ($path && file_exists($path)) {
            return $path;
        }

        try {
            return 'data:image/png;base64,'.$image->getBase64Image();
        } catch (Throwable) {
            return null;
        }
    }

    public function filename(AgentGoodsReceipt $receipt): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', __('Receipt note').'-'.$receipt->reference).'.pdf';
    }

    public function asController(Organisation $organisation, AgentGoodsReceipt $agentGoodsReceipt, ActionRequest $request): Response
    {
        abort_unless($agentGoodsReceipt->agent_id === $this->getOrganisationAgent($organisation)?->id, 404);
        $this->initialisation($organisation, $request);

        return response($this->handle($agentGoodsReceipt), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($agentGoodsReceipt).'"');
    }
}
