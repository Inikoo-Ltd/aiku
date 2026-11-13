---
paths:
  - 'app/Actions/SupplyChain/AgentPayment/**'
---

# Agent Payment

## Money the agent spends needs its receipt
Vika, 9 Nov 2026: StoreAgentLedgerEntry requires the receipt for every money-out type (charges and supplier payments) recorded from the UI; only transfers received and supplier refunds (isMoneyIn) and other_expense keep it optional (AgentPaymentTypeEnum::needsReceipt; other_expense because the agent sometimes asks for the money before paying, Vika 9 Nov 2026), and imports or other callers through action() skip it. The client approves money requests on that receipt: expense lines link it (grp.media.download, the client's users may open agent-owned media) and show an amber "No receipt" for older entries. Every form that writes a money-out ledger entry marks Receipt required or optional from the type's needs_receipt (AgentLedger, AgentContainerChargeDialog); AgentMoneyRequestExpenseDialog (always other_expense) marks it optional.

## Agent supplier payments set the PO deposit and balance dates
Vika, 12 Nov 2026: on an agent order the agent's supplier payments in its ledger (supplier_deposit/balance/payment with purchase_order_id) are what pays the supplier, so StoreAgentLedgerEntry, UpdateAgentLedgerEntry and DeleteAgentPayment call SyncPurchaseOrderPaidFromAgentLedger: the first dated payment sets deposit_paid_at (and confirms a submitted order), the one that covers cost_items sets balance_paid_at. Take ledgerDatesFor() BEFORE changing the rows and pass it to runForOrders(): a date that is empty or equal to the ledger's previous date follows the ledger (so a delete takes it back), a date the buyer marked by hand is kept. ImportAgentLedgerExcel writes AgentPayment::create directly and does not sync. The agent records these from the PO page too (AgentPurchaseOrderMoney "Pay supplier", pay_form in ShowPurchaseOrder::getAgentMoney).
