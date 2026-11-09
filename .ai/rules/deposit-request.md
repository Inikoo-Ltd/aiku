---
paths:
  - 'app/Actions/SupplyChain/DepositRequest/**'
---

# Deposit Request

## Money requests: every amount on its own line, no wallet line
Vika, 9 Nov 2026: an agent's money request never asks for "the negative balance" (kind wallet is legacy, refused by StoreAgentMoneyRequest). Each amount is its own line: PO advance/balance (covered by what was asked only, closed POs ask what was paid beyond that), shipment charges, and expense (one ledger entry without shipment, or a supplier payment without PO, linked by agent_payment_id). The client's money held by the agent and never asked for (transfers outside a request and not against a shipment, supplier refunds, plus legacy wallet lines) is taken off by the server as one credit item with a NEGATIVE amount, so sum(items.amount) is always what the client sends; approving a request with nothing outstanding settles it. Agents add ad-hoc costs (bank charges) through StoreAgentMoneyRequestExpense, which writes an other_expense ledger entry with required description and optional receipt (the agent sometimes asks for the money before paying).
