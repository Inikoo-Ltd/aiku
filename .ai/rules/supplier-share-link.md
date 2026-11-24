---
paths:
  - 'app/Actions/SupplyChain/SupplierShareLink/**'
---

# Supplier Share Link

## Supplier deposit answer: No deposit is deposit_amount 0, a request lives in data
Vika, 17 Nov 2026. At the deposit stage the supplier answers once from its portal (UpdateSupplierPortalPurchaseOrderDeposit), while the order is open, nothing is paid (isDepositPaid), deposit_amount is null and the deposit stage is not reached (ShowSupplierPortalPurchaseOrder::deposit). "No deposit needed" goes through UpdatePurchaseOrder deposit_amount 0, so a submitted order is agreed. "Request deposit 30%" is stored in purchase_orders.data.supplier_deposit_request (percentage, amount on cost_items, requested_at, supplier_portal_contact_id), never in deposit_amount: SyncPurchaseOrderDepositPaid rewrites that from payments on every total recalculation. NotifyPurchasingOfSupplierDepositRequest emails the buyer and org-supervisor procurement of the ordering org (as a price change), plus the agent org's org-supervisor procurement and procurement edit users.

## Portal requests have no permission team: set it before finding users by permission
Vika, 17 Nov 2026. Spatie teams is on (team = group_id) and only BindGroupInstance sets it, for a signed-in user. Supplier portal routes have none, so User::permission() finds nobody who holds the permission through a role (agent managers, procurement supervisors): only direct ids like the buyer got emails. Any recipient lookup reachable from the portal calls setPermissionsTeamId($model->group_id) first, as NotifyPurchasingOfSupplierDepositRequest::recipients and NotifyPurchasingOfSupplierProductAvailability::purchasingLeads do.

## Portal compliance shows status only; every document is optional, so nothing is "missing"
INI-528 B3 (Vika, 24 Nov 2026). David decided every compliance document is optional for now, so GetSupplierPortalCompliance never reports a document as missing: each one is on_file, not_sent, waiting, expiring (30 days), expired, more_information or not_accepted, and only the last four count as needs_attention (the "Compliance needs attention" badge on the plan for next orders, compliance_attention). Once required documents exist, add the status there from GetPurchaseOrderCompliance::requiredDocuments. A trade unit is often bought from several suppliers, so the portal shows the status of a document but only the files the supplier sent itself (my_files, downloaded through grp.supplier_share.submission.file). The Compliance tab lists the products on open orders and in the plan; GetSupplierPortalOrderPlan::handle($supplier, withCompliance: false) avoids computing it twice. A compliance document sent from a portal purchase order carries purchase_order_id (validated to be an open order of the supplier with that product) and purchase_order_reference, which go on the trade unit attachment when accepted. StoreSupplierPortalSubmission answers JSON to non-Inertia JSON requests so portal dialogs can post with axios.
