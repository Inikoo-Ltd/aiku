---
paths:
  - 'app/Actions/SupplyChain/SupplierShareLink/**'
---

# Supplier Share Link

## Supplier deposit answer: No deposit is deposit_amount 0, a request lives in data
Vika, 17 Nov 2026. At the deposit stage the supplier answers once from its portal (UpdateSupplierPortalPurchaseOrderDeposit), while the order is open, nothing is paid (isDepositPaid), deposit_amount is null and the deposit stage is not reached (ShowSupplierPortalPurchaseOrder::deposit). "No deposit needed" goes through UpdatePurchaseOrder deposit_amount 0, so a submitted order is agreed. "Request deposit 30%" is stored in purchase_orders.data.supplier_deposit_request (percentage, amount on cost_items, requested_at, supplier_portal_contact_id), never in deposit_amount: SyncPurchaseOrderDepositPaid rewrites that from payments on every total recalculation. NotifyPurchasingOfSupplierDepositRequest emails the buyer and org-supervisor procurement of the ordering org (as a price change), plus the agent org's org-supervisor procurement and procurement edit users.

## Portal requests have no permission team: set it before finding users by permission
Vika, 17 Nov 2026. Spatie teams is on (team = group_id) and only BindGroupInstance sets it, for a signed-in user. Supplier portal routes have none, so User::permission() finds nobody who holds the permission through a role (agent managers, procurement supervisors): only direct ids like the buyer got emails. Any recipient lookup reachable from the portal calls setPermissionsTeamId($model->group_id) first, as NotifyPurchasingOfSupplierDepositRequest::recipients and NotifyPurchasingOfSupplierProductAvailability::purchasingLeads do.
