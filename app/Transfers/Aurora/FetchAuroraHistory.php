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
use Illuminate\Database\Eloquent\SoftDeletes;
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

    protected const array BLANK_OBJECT_SNIFFS = [
        '/^Website user .+ created/i' => 'Website User',
        "/^Customer's client created/i" => 'Customer Client',
        '/^Supplier delivery /i' => 'Supplier Delivery',
        "/^Supplier Part's /i" => 'Supplier Part',
        "/^Deal Component's /i" => 'Deal Component',
        '/^Barcode record /i' => 'Barcode',
    ];

    protected function parseModel(): void
    {
        $directObject = (string) $this->auroraModelData->{'Direct Object'};

        if ($directObject === '') {
            $directObject = $this->sniffBlankDirectObject();
            if ($directObject === null) {
                $this->markSkippedInAurora();

                return;
            }
            $this->auroraModelData->{'Direct Object'} = $directObject;
        }

        $parser = self::PARSERS[$directObject] ?? null;
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
            $upload = $this->lookup(\App\Models\Helpers\Upload::class, $this->organisation->id.':'.$uploadSourceId);
            if ($upload) {
                data_set($data, 'upload_id', $upload->id);
                unset($data['upload_source_id']);
            } else {
                data_set($data, 'upload_source_id', $uploadSourceId);
            }
        }

        if (!($auditable->organisation_id ?? null)) {
            data_set($data, 'source_organisation', $this->organisation->slug);
        }

        $user = $this->lookupActor();

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

    protected function lookupActor(): Model|null
    {
        static $actorCache = [];

        $subject    = (string) ($this->auroraModelData->{'Subject'} ?? '');
        $subjectKey = (int) ($this->auroraModelData->{'Subject Key'} ?? 0);
        $cacheKey   = $this->organisation->id.'|'.$subject.'|'.$subjectKey;

        if (array_key_exists($cacheKey, $actorCache)) {
            return $actorCache[$cacheKey];
        }

        $actorCache[$cacheKey] = $this->resolveActorLookupOnly($subject, $subjectKey);

        return $actorCache[$cacheKey];
    }

    protected function resolveActorLookupOnly(string $subject, int $subjectKey): Model|null
    {
        if ($subjectKey <= 0) {
            return null;
        }

        $key = $this->organisation->id.':'.$subjectKey;

        if ($subject == 'Staff') {
            $employee = $this->lookup(\App\Models\HumanResources\Employee::class, $key);
            if ($employee) {
                $userHasModel = DB::table('user_has_models')
                    ->where('model_id', $employee->id)
                    ->where('model_type', 'Employee')
                    ->first();
                if ($userHasModel) {
                    return \App\Models\SysAdmin\User::withTrashed()->find($userHasModel->user_id);
                }
            }

            return \App\Models\SysAdmin\User::withTrashed()
                ->where('group_id', $this->organisation->group_id)
                ->whereJsonContains('sources->parents', $key)
                ->first() ?? $this->lookupUser($key);
        }

        if ($subject == 'Customer') {
            $webUserKey = DB::connection('aurora')
                ->table('Website User Dimension')
                ->where('Website User Customer Key', $subjectKey)
                ->orderBy('Website User Key')
                ->value('Website User Key');
            if ($webUserKey) {
                return $this->lookup(\App\Models\CRM\WebUser::class, $this->organisation->id.':'.$webUserKey);
            }
        }

        return null;
    }

    protected function sniffBlankDirectObject(): ?string
    {
        $abstract = (string) $this->auroraModelData->{'History Abstract'};

        foreach (self::BLANK_OBJECT_SNIFFS as $pattern => $directObject) {
            if (preg_match($pattern, $abstract)) {
                return $directObject;
            }
        }

        return null;
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

    protected function lookup(string $class, string $key, string $column = 'source_id'): ?Model
    {
        $query = in_array(SoftDeletes::class, class_uses_recursive($class), true)
            ? $class::withTrashed()
            : $class::query();

        return $query->where($column, $key)->first();
    }

    protected function resolveAuditable(string $directObject, ?string $auditableType): ?Model
    {
        $key = $this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'};

        if ($auditableType) {
            return $this->resolveByAuditableType($directObject, $auditableType, $key);
        }

        return match ($directObject) {
            'Customer' => $this->lookup(\App\Models\CRM\Customer::class, $key),
            'Prospect' => $this->lookup(\App\Models\CRM\Prospect::class, $key),
            'Product' => $this->lookup(\App\Models\Catalogue\Product::class, $key),
            'Location' => $this->lookup(\App\Models\Inventory\Location::class, $key),
            'Warehouse Area' => $this->lookup(\App\Models\Inventory\WarehouseArea::class, $key),
            'Order' => $this->lookup(\App\Models\Ordering\Order::class, $key),
            'Delivery Note' => $this->lookup(\App\Models\Dispatching\DeliveryNote::class, $key),
            'Invoice' => $this->lookup(\App\Models\Accounting\Invoice::class, $key),
            'Purchase Order' => $this->lookup(PurchaseOrder::class, $key),
            'Agent Supplier Purchase Order' => $this->lookup(AgentSupplierPurchaseOrder::class, $key),
            'Supplier Delivery' => $this->lookup(StockDelivery::class, $key),
            'Barcode' => $this->lookup(\App\Models\Helpers\Barcode::class, $key),
            'Charge' => $this->lookup(\App\Models\Billables\Charge::class, $key),
            'Customer Client' => $this->lookup(\App\Models\Dropshipping\CustomerClient::class, $key),
            default => null,
        };
    }

    protected function resolveByAuditableType(string $directObject, string $auditableType, string $key): ?Model
    {
        return match ($auditableType) {
            'TradeUnit' => $this->resolveTradeUnit($key),
            'OrgStock' => $this->lookup(\App\Models\Inventory\OrgStock::class, $key),
            'SupplierProduct' => $this->lookup(\App\Models\SupplyChain\SupplierProduct::class, $key),
            'ProductCategory' => match ($directObject) {
                'Family' => $this->lookupProductCategory('source_family_id', $key),
                'Department' => $this->lookupProductCategory('source_department_id', $key),
                default => $this->lookupProductCategory('source_family_id', $key)
                    ?? $this->lookupProductCategory('source_department_id', $key)
                    ?? $this->lookup(\App\Models\Catalogue\Collection::class, $key),
            },
            'StockFamily' => $this->lookup(StockFamily::class, $key),
            'Employee' => $this->lookup(\App\Models\HumanResources\Employee::class, $key),
            'User' => $this->lookupUser($key),
            'WebUser' => $this->lookup(\App\Models\CRM\WebUser::class, $key),
            'Mailshot' => $this->lookup(\App\Models\Comms\Mailshot::class, $key),
            'OfferCampaign' => $this->lookup(\App\Models\Discounts\OfferCampaign::class, $key),
            'Offer' => $this->lookup(\App\Models\Discounts\Offer::class, $key),
            'OfferComponent' => $this->lookup(\App\Models\Discounts\OfferAllowance::class, $key),
            'ShippingZone' => $this->lookup(\App\Models\Billables\ShippingZone::class, $key),
            'ShippingZoneSchema' => $this->lookup(\App\Models\Billables\ShippingZoneSchema::class, $key),
            'Supplier' => $this->lookup(\App\Models\SupplyChain\Supplier::class, $key),
            'Agent' => $this->lookup(\App\Models\SupplyChain\Agent::class, $key),
            'OrgSupplier' => $this->resolveOrgSupplier($key),
            'OrgAgent' => $this->resolveOrgAgent($key),
            default => null,
        };
    }

    protected function lookupProductCategory(string $column, string $key): ?Model
    {
        return \App\Models\Catalogue\ProductCategory::withTrashed()->where($column, $key)->first();
    }

    protected function lookupUser(string $key): ?Model
    {
        return \App\Models\SysAdmin\User::withTrashed()->where('source_id', $key)->first()
            ?? \App\Models\SysAdmin\User::withTrashed()
                ->where('group_id', $this->organisation->group_id)
                ->whereJsonContains('sources->users', $key)
                ->first();
    }

    protected function resolveTradeUnit(string $key): ?TradeUnit
    {
        $tradeUnit = TradeUnit::withTrashed()->where('source_id', $key)->first();
        if ($tradeUnit) {
            return $tradeUnit;
        }

        return $this->lookup(\App\Models\Inventory\OrgStock::class, $key)?->stock?->tradeUnits()->first();
    }

    protected function resolveOrgSupplier(string $key): ?OrgSupplier
    {
        $supplier = $this->lookup(\App\Models\SupplyChain\Supplier::class, $key);
        if (!$supplier) {
            return null;
        }

        return OrgSupplier::where('supplier_id', $supplier->id)
            ->where('organisation_id', $this->organisation->id)
            ->first();
    }

    protected function resolveOrgAgent(string $key): ?OrgAgent
    {
        $agent = $this->lookup(\App\Models\SupplyChain\Agent::class, $key);
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
