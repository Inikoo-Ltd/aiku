import Foundation

enum SystemSyncSampleData {
    static let offlineSince = "10:09"

    static let actions: [SystemQueuedAction] = [
        SystemQueuedAction(
            id: "q1", record: "AR24811", summary: "Picked 6 × SKO-00733",
            detail: "A-03-02 · 10:12 · based on version of 10:05", icon: .deliveryNote, conflictReference: "AR24811"
        ),
        SystemQueuedAction(
            id: "q2", record: "AR24811", summary: "Not picked 2 × SKO-00953",
            detail: "Out of stock · 10:14", icon: .deliveryNote, conflictReference: "AR24811"
        ),
        SystemQueuedAction(
            id: "q3", record: "SKO-00733", summary: "Audit A-03-02",
            detail: "30 → 28 · Damaged stock · 10:20", icon: .check, conflictReference: "A-03-02"
        ),
        SystemQueuedAction(
            id: "q4", record: "SDaw-0093", summary: "Checked 108 × SKO-00412",
            detail: "10:31", icon: .order
        ),
    ]

    static let history: [SystemSyncRecord] = [
        SystemSyncRecord(title: "12 actions uploaded", detail: "Version check passed · 09:58", chip: Chip("Synced", .success), icon: .check),
    ]

    static let conflicts: [SystemConflict] = [
        SystemConflict(
            reference: "AR24811",
            kind: "delivery note",
            title: "Delivery note AR24811",
            headerTitle: "AR24811",
            summary: "Sam P. also picked this note while you were offline",
            icon: .deliveryNote,
            lines: [
                SystemConflictLine(
                    sko: "SKO-00733", location: "A-03-02", name: "Bath bomb – rose 180g", required: 6, requiredVerb: "to pick",
                    mine: SystemLineValue("6", quantity: 6), aiku: SystemLineValue("4", quantity: 4),
                    mineSummary: "Picked 6 · 10:12", aikuSummary: "Picked 4", aikuBy: "Sam P. · 10:15"
                ),
                SystemConflictLine(
                    sko: "SKO-00953", location: "B-01-04", name: "Incense sticks", required: 2, requiredVerb: "to pick",
                    mine: SystemLineValue("Not picked"), aiku: SystemLineValue("Picked 2", quantity: 2),
                    mineSummary: "Not picked 2 · out of stock", aikuSummary: "Picked 2 from C-04-02", aikuBy: "Sam P. · 10:15"
                ),
            ],
            sameLines: [
                SystemSameLine(sko: "SKO-00412", chip: Chip("Picked 4", .success)),
                SystemSameLine(sko: "SKO-01187", chip: Chip("Waiting", .warn)),
            ]
        ),
        SystemConflict(
            reference: "A-03-02",
            kind: "location",
            title: "SKO-00733 in A-03-02",
            headerTitle: "SKO-00733 in A-03-02",
            summary: "Jo K. audited the location at 10:25",
            icon: .locations,
            lines: [
                SystemConflictLine(
                    sko: "SKO-00733", location: "A-03-02", name: "Bath bomb – rose 180g", required: 30, requiredVerb: "before audit",
                    mine: SystemLineValue("28", quantity: 28), aiku: SystemLineValue("29", quantity: 29),
                    mineSummary: "Audit 30 → 28 · Damaged stock · 10:20", aikuSummary: "Audit 30 → 29", aikuBy: "Jo K. · 10:25",
                    isAdditive: false
                ),
            ],
            sameLines: []
        ),
    ]
}
