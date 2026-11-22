<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use App\Enums\Accounting\ReceivedEInvoice\ReceivedEInvoiceMatchStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * An e-invoice or credit note a supplier sent us over Peppol, kept byte for byte as the access point delivered it.
 * The xml and pdf columns are bytea, written once through storeXml/storePdf and read back through xmlContent/pdfContent.
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $provider
 * @property string $provider_transaction_id
 * @property string|null $transmission_id
 * @property string|null $sender_peppol_id
 * @property string|null $sender_name
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property string|null $xml_sha256
 * @property string|null $pdf_sha256
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property string|null $document_type invoice|credit_note
 * @property string|null $document_number
 * @property \Illuminate\Support\Carbon|null $issue_date
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string|null $currency_code
 * @property string|null $net_amount
 * @property string|null $tax_amount
 * @property string|null $total_amount
 * @property string|null $payable_amount
 * @property string|null $supplier_name
 * @property string|null $supplier_vat_number
 * @property string|null $supplier_tax_number
 * @property string|null $supplier_company_number
 * @property string|null $buyer_reference
 * @property string|null $order_reference
 * @property string|null $billing_reference
 * @property string|null $parse_error
 * @property ReceivedEInvoiceMatchStateEnum $match_state
 * @property int|null $supplier_id
 * @property int|null $purchase_order_id
 * @property int|null $stock_delivery_id
 * @property array<string, mixed> $match_data
 * @property \Illuminate\Support\Carbon|null $matched_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Organisation $organisation
 * @property-read Supplier|null $supplier
 * @property-read PurchaseOrder|null $purchaseOrder
 * @property-read StockDelivery|null $stockDelivery
 * @mixin \Eloquent
 */
class ReceivedEInvoice extends Model
{
    protected $guarded = ['xml', 'pdf', 'xml_sha256', 'pdf_sha256'];

    protected $hidden = ['xml', 'pdf'];

    protected $attributes = [
        'match_data'  => '{}',
        'match_state' => 'unmatched',
    ];

    protected function casts(): array
    {
        return [
            'received_at'     => 'datetime',
            'acknowledged_at' => 'datetime',
            'issue_date'      => 'date',
            'due_date'        => 'date',
            'match_state'     => ReceivedEInvoiceMatchStateEnum::class,
            'match_data'      => 'array',
            'matched_at'      => 'datetime',
        ];
    }

    public function hasXml(): bool
    {
        return $this->xml_sha256 !== null;
    }

    public function hasPdf(): bool
    {
        return $this->pdf_sha256 !== null;
    }

    public function storeXml(string $bytes): void
    {
        $this->storeFile('xml', $bytes);
    }

    public function storePdf(string $bytes): void
    {
        $this->storeFile('pdf', $bytes);
    }

    public function xmlContent(): ?string
    {
        return $this->readFile('xml');
    }

    public function pdfContent(): ?string
    {
        return $this->readFile('pdf');
    }

    /**
     * Writes only into an empty column, so a document once stored is never replaced.
     */
    private function storeFile(string $column, string $bytes): void
    {
        $sha256 = hash('sha256', $bytes);
        DB::update(
            "update received_e_invoices set $column = decode(?, 'base64'), {$column}_sha256 = ?, updated_at = now() where id = ? and $column is null",
            [base64_encode($bytes), $sha256, $this->id]
        );
        $this->{$column.'_sha256'} = DB::table('received_e_invoices')->where('id', $this->id)->value($column.'_sha256');
        $this->syncOriginalAttribute($column.'_sha256');
    }

    private function readFile(string $column): ?string
    {
        $encoded = DB::table('received_e_invoices')->where('id', $this->id)->selectRaw("encode($column, 'base64') as encoded")->value('encoded');

        return $encoded === null ? null : base64_decode($encoded);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function stockDelivery(): BelongsTo
    {
        return $this->belongsTo(StockDelivery::class);
    }
}
