---
paths:
  - 'app/Actions/SupplyChain/AgentPayment/**'
---

# Agent Payment

## Money the agent spends needs its receipt
Vika, 9 Nov 2026: StoreAgentLedgerEntry requires the receipt for every money-out type (charges and supplier payments) recorded from the UI; only transfers received and supplier refunds (isMoneyIn) keep it optional, and imports or other callers through action() skip it. The client approves money requests on that receipt: expense lines link it (grp.media.download, the client's users may open agent-owned media) and show an amber "No receipt" for older entries. Every form that writes a money-out ledger entry (AgentLedger, AgentContainerChargeDialog, AgentMoneyRequestExpenseDialog) marks Receipt as required.
