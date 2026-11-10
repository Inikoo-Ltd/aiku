import Foundation

enum DispatchSampleData {
    static let shippers: [DispatchShipper] = [
        DispatchShipper(name: "DPD (GB)", integration: "API", shipmentsToday: 48),
        DispatchShipper(name: "APC (GB)", integration: "API", shipmentsToday: 21),
        DispatchShipper(name: "Packeta (SK)", integration: "API", shipmentsToday: 0)
    ]

    static let trolleyCodes = ["T-01", "T-02", "T-03", "T-04", "T-05", "T-06", "T-07"]

    static let trolleyNotes: [String: String] = ["T-01": "AR24790"]

    static let sessions: [DispatchPickingSession] = [
        DispatchPickingSession(
            reference: "PSaw-0142", kind: .dropshipping, picker: "Sam P.", deliveryNoteCount: 12, trolleys: ["T-02", "T-04"],
            locations: [
                DispatchPickLocation(location: "A-01-01", sko: "SKO-00412", notes: [
                    DispatchPickNote(deliveryNote: "AR24820", trolley: "T-02", required: 4, picked: 4)
                ]),
                DispatchPickLocation(location: "B-01-04", sko: "SKO-00953", notes: [
                    DispatchPickNote(deliveryNote: "AR24821", trolley: "T-04", required: 6, picked: 2)
                ])
            ],
            isStarted: true
        ),
        DispatchPickingSession(
            reference: "PSaw-0143", kind: .dropshipping, picker: "Alex Morgan", isYou: true, deliveryNoteCount: 8, trolleys: ["T-03", "T-05"],
            locations: [
                DispatchPickLocation(location: "A-01-01", sko: "SKO-00412", notes: [
                    DispatchPickNote(deliveryNote: "AR24830", trolley: "T-03", required: 3),
                    DispatchPickNote(deliveryNote: "AR24832", trolley: "T-03", required: 2),
                    DispatchPickNote(deliveryNote: "AR24833", trolley: "T-03", required: 2)
                ]),
                DispatchPickLocation(location: "A-03-02", sko: "SKO-00733", skoName: "Bath bomb – rose 180g", notes: [
                    DispatchPickNote(deliveryNote: "AR24830", trolley: "T-03", required: 2),
                    DispatchPickNote(deliveryNote: "AR24831", trolley: "T-03", required: 3),
                    DispatchPickNote(deliveryNote: "AR24834", trolley: "T-05", required: 4)
                ]),
                DispatchPickLocation(location: "B-01-04", sko: "SKO-00953", notes: [
                    DispatchPickNote(deliveryNote: "AR24835", trolley: "T-05", required: 2)
                ]),
                DispatchPickLocation(location: "A-07-01", sko: "SKO-01187", skoName: "Soy wax candle", notes: [
                    DispatchPickNote(deliveryNote: "AR24836", trolley: "T-05", required: 3),
                    DispatchPickNote(deliveryNote: "AR24837", trolley: "T-05", required: 2)
                ])
            ]
        ),
        DispatchPickingSession(
            reference: "PSaw-0139", kind: .dropshipping, picker: "Jo K.", deliveryNoteCount: 1, trolleys: [],
            locations: [
                DispatchPickLocation(location: "A-07-01", sko: "SKO-01187", skoName: "Soy wax candle", notes: [
                    DispatchPickNote(deliveryNote: "AR24811", trolley: "Bay 2", required: 2, isWaiting: true)
                ])
            ],
            isStarted: true,
            bay: "Bay 2"
        ),
        DispatchPickingSession(
            reference: "PSaw-0137", kind: .dropshipping, picker: "Jo K.", deliveryNoteCount: 3, trolleys: ["T-06", "T-07"],
            locations: [],
            packNotes: [
                DispatchPackNote(deliveryNote: "AR24801", trolley: "T-06", items: 3, packed: 3),
                DispatchPackNote(deliveryNote: "AR24802", trolley: "T-06", items: 5, packed: 2),
                DispatchPackNote(deliveryNote: "AR24805", trolley: "T-07", items: 2)
            ],
            isStarted: true
        )
    ]

    static let bays: [DispatchPickedBay] = [
        DispatchPickedBay(name: "Bay 1"),
        DispatchPickedBay(name: "Bay 2", notes: [
            DispatchBayNote(deliveryNote: "AR24810"),
            DispatchBayNote(deliveryNote: "AR24811", isWaiting: true)
        ]),
        DispatchPickedBay(name: "Bay 3", notes: [DispatchBayNote(deliveryNote: "AR24815")])
    ]
}
