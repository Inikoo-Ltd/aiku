<?php

/*
 * Aurora is being decommissioned. Organisations leave it one at a time, and the day one
 * does, its staff start editing that data in aiku — so a late Aurora fetch stops being a
 * sync and becomes silent data loss.
 */

return [

    /*
     * Organisations that still treat Aurora as the source of truth. Everything else runs
     * on aiku and only accepts the fetchers listed below. Empty this once the last
     * organisation has migrated, then the whole Transfers/Aurora tree can go.
     */
    'following_organisations' => array_filter(array_map('trim', explode(',', (string)env('AURORA_FOLLOWING_ORGANISATIONS', 'aroma')))),

    /*
     * What an organisation that has already left Aurora still accepts, by fetcher short
     * name (FetchAurora<name>). Stocks, OrgStocks and TradeUnits appear here because new
     * ones must still arrive for purchase orders and supplier deliveries — the create only
     * rule that keeps Aurora off existing rows lives in WithFetchStock, not here.
     */
    'allowed_fetchers' => [
        'Agents',
        'ClockingMachines',
        'OrgStockMovements',
        'PurchaseOrgStockMovements',
        'PurchaseOrders',
        'PurchaseOrderTransactions',
        'StockDeliveries',
        'StockDeliveryItems',
        'Stocks',
        'SupplierProducts',
        'Suppliers',
        'Timesheets',
        'TradeUnits',
    ],

];
