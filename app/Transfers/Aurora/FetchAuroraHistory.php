<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 11:58:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\AgentSupplierPurchaseOrder;
use App\Transfers\Aurora\History\Parsers\ParseBarcodeHistory;
use App\Transfers\Aurora\History\Parsers\ParseCategoryHistory;
use App\Transfers\Aurora\History\Parsers\ParseChargeHistory;
use App\Transfers\Aurora\History\Parsers\ParseCustomerClientHistory;
use App\Transfers\Aurora\History\Parsers\ParseCustomerHistory;
use App\Transfers\Aurora\History\Parsers\ParseDealHistory;
use App\Transfers\Aurora\History\Parsers\ParseDeliveryNoteHistory;
use App\Transfers\Aurora\History\Parsers\ParseInvoiceHistory;
use App\Transfers\Aurora\History\Parsers\ParseLocationHistory;
use App\Transfers\Aurora\History\Parsers\ParseMarketingHistory;
use App\Transfers\Aurora\History\Parsers\ParseOrderHistory;
use App\Transfers\Aurora\History\Parsers\ParsePartHistory;
use App\Transfers\Aurora\History\Parsers\ParseProductHistory;
use App\Transfers\Aurora\History\Parsers\ParseProspectHistory;
use App\Transfers\Aurora\History\Parsers\ParsePurchaseOrderHistory;
use App\Transfers\Aurora\History\Parsers\ParseShippingZoneHistory;
use App\Transfers\Aurora\History\Parsers\ParseStaffUserHistory;
use App\Transfers\Aurora\History\Parsers\ParseSupplierAgentHistory;
use App\Transfers\Aurora\History\Parsers\ParseSupplierDeliveryHistory;
use App\Transfers\Aurora\History\Parsers\ParseSupplierPartHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraHistory extends FetchAurora
{
    public const array PARSERS = [
        'Customer' => ParseCustomerHistory::class,
        'Prospect' => ParseProspectHistory::class,
        'Product' => ParseProductHistory::class,
        'Location' => ParseLocationHistory::class,
        'Warehouse Area' => ParseLocationHistory::class,
        'Order' => ParseOrderHistory::class,
        'Delivery Note' => ParseDeliveryNoteHistory::class,
        'Invoice' => ParseInvoiceHistory::class,
        'Purchase Order' => ParsePurchaseOrderHistory::class,
        'Agent Supplier Purchase Order' => ParsePurchaseOrderHistory::class,
        'Supplier Delivery' => ParseSupplierDeliveryHistory::class,
        'Part' => ParsePartHistory::class,
        'Supplier Part' => ParseSupplierPartHistory::class,
        'Barcode' => ParseBarcodeHistory::class,
        'Category' => ParseCategoryHistory::class,
        'Family' => ParseCategoryHistory::class,
        'Department' => ParseCategoryHistory::class,
        'Staff' => ParseStaffUserHistory::class,
        'User' => ParseStaffUserHistory::class,
        'Website User' => ParseStaffUserHistory::class,
        'Email Campaign' => ParseMarketingHistory::class,
        'Deal Campaign' => ParseMarketingHistory::class,
        'Deal' => ParseDealHistory::class,
        'Deal Component' => ParseDealHistory::class,
        'Shipping Zone' => ParseShippingZoneHistory::class,
        'Shipping Zone Schema' => ParseShippingZoneHistory::class,
        'Supplier' => ParseSupplierAgentHistory::class,
        'Agent' => ParseSupplierAgentHistory::class,
        'Charge' => ParseChargeHistory::class,
        'Customer Client' => ParseCustomerClientHistory::class,
    ];

    protected function parseModel(): void
    {
        $directObject = (string) $this->auroraModelData->{'Direct Object'};
        $parser       = self::PARSERS[$directObject] ?? null;
        if (!$parser) {
            return;
        }

        if ($directObject == 'Category') {
            $this->enrichCategoryRow();
        }

        $classification = $parser::classify($this->auroraModelData);
        if ($classification['handling'] !== 'import') {
            $this->markSkippedInAurora();

            return;
        }

        $auditable = $this->resolveAuditable($directObject, $classification['auditable_type'] ?? null);
        if (!$auditable) {
            return;
        }

        $event  = $classification['event'];
        $values = $parser::extractValues($this->auroraModelData, $event, $classification['field'], $classification['auditable_type'] ?? null);

        if ($event == 'updated' && count($values['old_values']) == 0 && count($values['new_values']) == 0) {
            $this->markSkippedInAurora();

            return;
        }

        $data = $values['data'];
        if ($uploadSourceId = $this->getUploadSourceId($data)) {
            $upload = $this->parseUpload($this->organisation->id.':'.$uploadSourceId);
            if ($upload) {
                data_set($data, 'upload_id', $upload->id);
            }
            unset($data['upload_source_id']);
        }

        if (!($auditable->organisation_id ?? null)) {
            data_set($data, 'source_organisation', $this->organisation->slug);
        }

        $user = $this->parseUserFromHistory();

        $this->parsedData['auditable'] = $auditable;
        $this->parsedData['history']   = [
            'created_at'      => $this->auroraModelData->{'History Date'},
            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'History Key'},
            'fetched_at'      => now(),
            'last_fetched_at' => now(),
            'event'           => $event,
            'tags'            => $this->auditableTags($auditable),
            'new_values'      => $values['new_values'],
            'old_values'      => $values['old_values'],
            'data'            => $data,
        ];

        if ($user) {
            $this->parsedData['history']['user_type'] = class_basename($user);
            $this->parsedData['history']['user_id']   = $user->id;
        }
    }

    protected function auditableTags(Model $auditable): array
    {
        if (method_exists($auditable, 'generateTags')) {
            $tags = $auditable->generateTags();
            if (count($tags) > 0) {
                return $tags;
            }
        }

        return [Str::kebab(class_basename($auditable))];
    }

    protected function enrichCategoryRow(): void
    {
        $category = DB::connection('aurora')
            ->table('Category Dimension')
            ->where('Category Key', $this->auroraModelData->{'Direct Object Key'})
            ->first();

        $this->auroraModelData->aikuCategoryScope   = $category?->{'Category Scope'};
        $this->auroraModelData->aikuCategorySubject = $category?->{'Category Subject'};
    }

    protected function resolveAuditable(string $directObject, ?string $auditableType): ?Model
    {
        $key = $this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'};

        if ($auditableType) {
            return $this->resolveByAuditableType($directObject, $auditableType, $key);
        }

        return match ($directObject) {
            'Customer' => $this->parseCustomer($key),
            'Prospect' => $this->parseProspect($key),
            'Product' => $this->parseProduct($key),
            'Location' => $this->parseLocation($key, $this->organisationSource),
            'Warehouse Area' => $this->parseWarehouseArea($key),
            'Order' => $this->parseOrder($key),
            'Delivery Note' => $this->parseDeliveryNote($key),
            'Invoice' => $this->parseInvoice($key),
            'Purchase Order' => PurchaseOrder::where('source_id', $key)->first(),
            'Agent Supplier Purchase Order' => AgentSupplierPurchaseOrder::where('source_id', $key)->first(),
            'Supplier Delivery' => StockDelivery::where('source_id', $key)->first(),
            'Barcode' => $this->parseBarcode($key),
            'Charge' => $this->parseCharge($key),
            'Customer Client' => $this->parseCustomerClient($key),
            default => null,
        };
    }

    protected function resolveByAuditableType(string $directObject, string $auditableType, string $key): ?Model
    {
        return match ($auditableType) {
            'TradeUnit' => TradeUnit::withTrashed()->where('source_id', $key)->first(),
            'OrgStock' => $this->parseOrgStock($key),
            'SupplierProduct' => $this->parseSupplierProduct($key),
            'ProductCategory' => match ($directObject) {
                'Family' => $this->parseFamily($key),
                'Department' => $this->parseDepartment($key),
                default => $this->parseFamily($key) ?? $this->parseDepartment($key) ?? $this->parseCollection($key),
            },
            'StockFamily' => StockFamily::withTrashed()->where('source_id', $key)->first(),
            'Employee' => $this->parseEmployee($key),
            'User' => $this->parseUser($key),
            'WebUser' => $this->parseWebUser($key),
            'Mailshot' => $this->parseMailshot($key),
            'OfferCampaign' => $this->parseOfferCampaign($key),
            'Offer' => $this->parseOffer($key),
            'OfferComponent' => $this->parseOfferAllowance($key),
            'ShippingZone' => $this->parseShippingZone($key),
            'ShippingZoneSchema' => $this->parseShippingZoneSchema($key),
            'Supplier' => $this->parseSupplier($key),
            'Agent' => $this->parseAgent($key),
            'OrgSupplier' => $this->resolveOrgSupplier($key),
            'OrgAgent' => $this->resolveOrgAgent($key),
            default => null,
        };
    }

    protected function resolveOrgSupplier(string $key): ?OrgSupplier
    {
        $supplier = $this->parseSupplier($key);
        if (!$supplier) {
            return null;
        }

        return OrgSupplier::where('supplier_id', $supplier->id)
            ->where('organisation_id', $this->organisation->id)
            ->first();
    }

    protected function resolveOrgAgent(string $key): ?OrgAgent
    {
        $agent = $this->parseAgent($key);
        if (!$agent) {
            return null;
        }

        return OrgAgent::where('agent_id', $agent->id)
            ->where('organisation_id', $this->organisation->id)
            ->first();
    }

    protected function getUploadSourceId(array $data): ?int
    {
        if (isset($data['upload_source_id'])) {
            return (int) $data['upload_source_id'];
        }

        if (preg_match('/change_view\(\'upload\/(\d+)/', (string) $this->auroraModelData->{'History Abstract'}, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    protected static array $skippedBuffer = [];

    protected function markSkippedInAurora(): void
    {
        self::$skippedBuffer[] = $this->auroraModelData->{'History Key'};

        if (count(self::$skippedBuffer) >= 1000) {
            self::flushSkipped();
        }
    }

    public static function flushSkipped(): void
    {
        if (count(self::$skippedBuffer) == 0) {
            return;
        }

        DB::connection('aurora')
            ->table('History Dimension')
            ->whereIn('History Key', self::$skippedBuffer)
            ->update(['aiku_id' => 0]);

        self::$skippedBuffer = [];
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('History Dimension')
            ->where('History Key', $id)->first();
    }
}
