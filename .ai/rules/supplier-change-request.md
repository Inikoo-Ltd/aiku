---
paths:
  - 'app/Actions/SupplyChain/SupplierChangeRequest/**'
---

# Supplier Change Request

## Approving a supplier suggestion runs in a queued job
Vika, 18 Nov 2026. A merge can take minutes and timed out as a web request. DecideSupplierChangeRequest checks the blockers at once (MergeSuppliers::blockers, DeactivateSupplier::openPurchaseOrderReferences) and returns the errors in the dialog. It then dispatches itself on the urgent queue (asJob, 1 try, 1800 s). The job writes its step to the cache (progressKey/putProgress: queued, checking, products done/total, moving, refreshing, saving, then done or failed with a message). The dialog polls grp.json.supplier_change_request.progress and reloads the dashboard when the step is done or failed. Keeping the supplier stays synchronous. Never run the approval inline in the request again. asController holds Cache::lock(decisionLockKey) around validation, queueing and rejection, so an approval is never queued twice; while a step is running (isRunning) no decision is accepted, rejection included. The job re-checks before starting that the suggestion is still pending and approvalErrors() (both suppliers active, plus the blockers) is empty, otherwise it ends as failed with the message.
