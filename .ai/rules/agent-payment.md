---
paths:
  - 'app/Actions/SupplyChain/AgentPayment/**'
---

# Agent Payment

## Money the agent spends needs its receipt
Vika, 9 Nov 2026: StoreAgentLedgerEntry requires the receipt for every money-out type (charges and supplier payments) recorded from the UI; only transfers received and supplier refunds (isMoneyIn) and other_expense keep it optional (AgentPaymentTypeEnum::needsReceipt; other_expense because the agent sometimes asks for the money before paying, Vika 9 Nov 2026), and imports or other callers through action() skip it. The client approves money requests on that receipt: expense lines link it (grp.media.download, the client's users may open agent-owned media) and show an amber "No receipt" for older entries. Every form that writes a money-out ledger entry marks Receipt required or optional from the type's needs_receipt (AgentLedger, AgentContainerChargeDialog); AgentMoneyRequestExpenseDialog (always other_expense) marks it optional.
