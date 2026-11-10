import Foundation

enum GoodsOutSampleData {
    static let currentUser = StaffProfile.sample.name

    static func line(_ sko: String, _ required: Int, picked: Int = 0, at location: String? = nil, stock: Int? = nil, outcome: GoodsOutLineOutcome? = nil, from pickedFrom: String? = nil, packed packedInto: [Int: Int] = [:]) -> GoodsOutLine {
        let product = products[sko] ?? (name: sko, location: "A-01-01")
        return GoodsOutLine(
            sko: sko,
            name: product.name,
            location: location ?? product.location,
            required: required,
            picked: picked,
            pickedFrom: pickedFrom,
            locationStock: stock,
            outcome: outcome,
            packedInto: packedInto
        )
    }

    static let products: [String: (name: String, location: String)] = [
        "SKO-00412": ("Lavender essential oil 10ml", "A-01-01"),
        "SKO-00733": ("Bath bomb – rose 180g", "A-03-02"),
        "SKO-01187": ("Soy wax candle – vanilla", "A-07-01"),
        "SKO-00953": ("Incense sticks – sandalwood", "B-01-04"),
        "SKO-00734": ("Bath bomb – lavender 180g", "B-01-04"),
        "SKO-01402": ("Eucalyptus essential oil 10ml", "C-02-01")
    ]

    static let notes: [GoodsOutDeliveryNote] = [
        GoodsOutDeliveryNote(
            reference: "AR24811",
            channel: .wholesale,
            customer: "[Customer]",
            customerNumber: "18831",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .dpd,
            state: .picking,
            picker: currentUser,
            trolley: "T-03",
            lines: [
                line("SKO-00412", 4, picked: 4),
                line("SKO-00733", 6, picked: 3, stock: 30),
                line("SKO-01187", 2, outcome: .waiting(.warehouse, note: "Location empty, check overstock")),
                line("SKO-00953", 2, outcome: .notPicked(.outOfStock))
            ],
            customerInstructions: "Deliver before 10am, ring the bell at goods-in.",
            warehouseNote: "Orders for this customer always go on a pallet.",
            createdAt: GoodsOutDateFormat.date(day: 9, hour: 8, minute: 10),
            stateDates: [.picking: GoodsOutDateFormat.date(day: 10, hour: 9, minute: 12)],
            age: .yesterday,
            estimate: "~18 min picking, ~8 min packing",
            weightKg: 6.2,
            skoCount: 14
        ),
        GoodsOutDeliveryNote(
            reference: "AR24812",
            channel: .b2c,
            customer: "[Customer]",
            customerNumber: "20871",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .apc,
            state: .cancelled,
            picker: currentUser,
            lines: [
                line("SKO-00733", 6, picked: 6),
                line("SKO-00412", 2, picked: 2, at: "A-07-01")
            ],
            createdAt: GoodsOutDateFormat.date(day: 9, hour: 16, minute: 4),
            stateDates: [.picking: GoodsOutDateFormat.date(day: 10, hour: 8, minute: 41)],
            age: .yesterday,
            estimate: "~6 min picking, ~3 min packing",
            weightKg: 1.1,
            skoCount: 2
        ),
        GoodsOutDeliveryNote(
            reference: "AR24815",
            channel: .wholesale,
            customer: "[Customer]",
            customerNumber: "17402",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .dpd,
            state: .waiting,
            picker: "Jo K.",
            pickedBay: "Bay 2",
            lines: [
                line("SKO-01187", 4, picked: 2, outcome: .waiting(.warehouse, note: "Location empty, check overstock")),
                line("SKO-00953", 2, picked: 1, outcome: .waiting(.customerService, note: "customer to confirm a substitute")),
                line("SKO-00733", 6, picked: 6)
            ],
            customerInstructions: "Use the side entrance for deliveries.",
            createdAt: GoodsOutDateFormat.date(day: 6, hour: 11, minute: 25),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 9, hour: 14, minute: 2),
                .waiting: GoodsOutDateFormat.date(day: 9, hour: 15, minute: 30)
            ],
            age: .thisWeek,
            isOverdue: true,
            estimate: "~25 min picking, ~10 min packing",
            weightKg: 12.4,
            skoCount: 28,
            messages: GoodsOutSampleData.waitingChat,
            tasks: [GoodsOutTask(id: 1, title: "Task: recount B-01-04", assignee: "Sam P.")]
        ),
        GoodsOutDeliveryNote(
            reference: "AR24816",
            channel: .dropshipping,
            platform: "Shopify",
            customer: "[Dropship customer]",
            customerNumber: "20523",
            contactName: "[End customer name]",
            email: "[end customer email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .dpd,
            state: .todo,
            lines: [
                line("SKO-00412", 4),
                line("SKO-00733", 6, stock: 30),
                line("SKO-01187", 2),
                line("SKO-00953", 2)
            ],
            customerInstructions: "Please use plain packaging, no invoice in the box.",
            warehouseNote: "Fragile: wrap the candles separately.",
            createdAt: GoodsOutDateFormat.date(day: 7, hour: 22, minute: 52),
            age: .thisWeek,
            isOverdue: true,
            estimate: "~12 min picking, ~6 min packing",
            weightKg: 6.2,
            skoCount: 6
        ),
        GoodsOutDeliveryNote(
            reference: "AR24817",
            channel: .wholesale,
            customer: "[Customer]",
            customerNumber: "16220",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .dpd,
            state: .picking,
            picker: "Sam P.",
            lines: [
                line("SKO-00412", 6, picked: 6),
                line("SKO-00733", 6, picked: 3, stock: 30),
                line("SKO-01187", 1),
                line("SKO-00953", 2, at: "A-12-03"),
                line("SKO-00734", 3),
                line("SKO-01402", 1)
            ],
            createdAt: GoodsOutDateFormat.date(day: 10, hour: 7, minute: 15),
            stateDates: [.picking: GoodsOutDateFormat.date(day: 10, hour: 8, minute: 55)],
            estimate: "~15 min picking, ~7 min packing",
            weightKg: 4.8,
            skoCount: 19
        ),
        GoodsOutDeliveryNote(
            reference: "AR24819",
            channel: .wholesale,
            customer: "[Customer]",
            customerNumber: "19004",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .apc,
            state: .todo,
            lines: [
                line("SKO-00734", 3),
                line("SKO-01402", 4),
                line("SKO-00412", 2)
            ],
            createdAt: GoodsOutDateFormat.date(day: 10, hour: 8, minute: 30),
            estimate: "~8 min picking, ~4 min packing",
            weightKg: 3.8,
            skoCount: 9
        ),
        GoodsOutDeliveryNote(
            reference: "AR24813",
            channel: .wholesale,
            customer: "[Customer]",
            customerNumber: "18831",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .dpd,
            state: .picked,
            picker: currentUser,
            pickedBay: "Bay 1",
            lines: [
                line("SKO-00412", 4, picked: 4),
                line("SKO-00733", 6, picked: 6),
                line("SKO-01187", 2, picked: 2, from: "C-04-02"),
                line("SKO-00953", 2, outcome: .notPicked(.outOfStock))
            ],
            customerInstructions: "Deliver before 10am, ring the bell at goods-in.",
            createdAt: GoodsOutDateFormat.date(day: 9, hour: 9, minute: 40),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 10, hour: 7, minute: 50),
                .picked: GoodsOutDateFormat.date(day: 10, hour: 8, minute: 20)
            ],
            age: .yesterday,
            estimate: "~18 min picking, ~8 min packing",
            weightKg: 6.2,
            skoCount: 14
        ),
        GoodsOutDeliveryNote(
            reference: "AR24821",
            channel: .wholesale,
            customer: "[Customer]",
            customerNumber: "15518",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .dpd,
            state: .packing,
            picker: "Jo K.",
            pickedBay: "Bay 4",
            lines: [
                line("SKO-00412", 4, picked: 4, packed: [1: 4]),
                line("SKO-00733", 6, picked: 6, packed: [1: 2]),
                line("SKO-01187", 2, picked: 2)
            ],
            createdAt: GoodsOutDateFormat.date(day: 9, hour: 13, minute: 5),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 10, hour: 7, minute: 30),
                .picked: GoodsOutDateFormat.date(day: 10, hour: 8, minute: 2),
                .packing: GoodsOutDateFormat.date(day: 10, hour: 8, minute: 40)
            ],
            age: .yesterday,
            estimate: "~18 min picking, ~8 min packing",
            weightKg: 5.5,
            skoCount: 12,
            packingMaterials: ["2 × [BOX-M]", "1 × [GIFT-WRAP]"],
            parcels: [
                GoodsOutParcel(number: 1, weightKg: 3.4, length: 40, width: 30, height: 20),
                GoodsOutParcel(number: 2, weightKg: 2.1)
            ],
            leaflets: [GoodsOutLeaflet(id: "[Spring catalogue]"), GoodsOutLeaflet(id: "[Returns form]")]
        ),
        GoodsOutDeliveryNote(
            reference: "AR24840",
            channel: .b2c,
            customer: "[Customer]",
            customerNumber: "21207",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .collection,
            state: .packed,
            picker: "Jo K.",
            lines: [line("SKO-01402", 1, picked: 1, packed: [1: 1])],
            createdAt: GoodsOutDateFormat.date(day: 10, hour: 6, minute: 48),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 10, hour: 7, minute: 5),
                .picked: GoodsOutDateFormat.date(day: 10, hour: 7, minute: 9),
                .packing: GoodsOutDateFormat.date(day: 10, hour: 7, minute: 20),
                .packed: GoodsOutDateFormat.date(day: 10, hour: 7, minute: 26)
            ],
            estimate: "~2 min picking, ~2 min packing",
            weightKg: 1.2,
            skoCount: 1,
            listDetail: "Collection · 1 item",
            parcels: [GoodsOutParcel(number: 1, weightKg: 1.2, length: 25, width: 20, height: 10)],
            leaflets: [GoodsOutLeaflet(id: "[Spring catalogue]", isPrinted: true)]
        ),
        GoodsOutDeliveryNote(
            reference: "AR24822",
            channel: .marketplaces,
            platform: "TikTok",
            customer: "[Customer]",
            customerNumber: "22310",
            contactName: "[End customer name]",
            email: "[end customer email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            state: .packed,
            picker: "Sam P.",
            lines: [
                line("SKO-00733", 2, picked: 2, packed: [1: 2]),
                line("SKO-00734", 2, picked: 2, packed: [1: 2])
            ],
            createdAt: GoodsOutDateFormat.date(day: 9, hour: 19, minute: 12),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 10, hour: 6, minute: 40),
                .picked: GoodsOutDateFormat.date(day: 10, hour: 6, minute: 52),
                .packing: GoodsOutDateFormat.date(day: 10, hour: 7, minute: 1),
                .packed: GoodsOutDateFormat.date(day: 10, hour: 7, minute: 12)
            ],
            age: .yesterday,
            estimate: "~4 min picking, ~3 min packing",
            weightKg: 0.9,
            skoCount: 4,
            listDetail: "shipped by TikTok",
            parcels: [GoodsOutParcel(number: 1, weightKg: 0.9, length: 20, width: 15, height: 10)]
        ),
        GoodsOutDeliveryNote(
            reference: "AR24826",
            channel: .wholesale,
            customer: "[Customer]",
            customerNumber: "17955",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .dpd,
            state: .waiting,
            picker: "Sam P.",
            pickedBay: "Bay 2",
            lines: [
                line("SKO-00734", 3, picked: 1, outcome: .waiting(.warehouse, note: "Incomplete set, 2 bath bombs missing")),
                line("SKO-00412", 2, picked: 2)
            ],
            createdAt: GoodsOutDateFormat.date(day: 8, hour: 10, minute: 18),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 9, hour: 10, minute: 0),
                .waiting: GoodsOutDateFormat.date(day: 9, hour: 10, minute: 35)
            ],
            age: .thisWeek,
            isOverdue: true,
            estimate: "~6 min picking, ~4 min packing",
            weightKg: 1.9,
            skoCount: 5,
            listDetail: "incomplete set",
            incompleteSet: GoodsOutIncompleteSet(name: "[Gift set – spa trio]", parts: [
                GoodsOutSetPart(sko: "SKO-00953", name: "Incense sticks – sandalwood", location: "B-01-04", picked: 0),
                GoodsOutSetPart(sko: "SKO-00733", name: "Bath bomb – rose 180g", location: "A-03-02", picked: 1),
                GoodsOutSetPart(sko: "SKO-00412", name: "Lavender essential oil 10ml", location: "A-01-01", picked: 1)
            ])
        ),
        GoodsOutDeliveryNote(
            reference: "AR24831",
            channel: .wholesale,
            customer: "[Customer]",
            customerNumber: "14467",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .apc,
            state: .finalised,
            picker: currentUser,
            lines: [
                line("SKO-00953", 6, picked: 6, packed: [1: 6]),
                line("SKO-01187", 4, picked: 4, packed: [2: 4])
            ],
            createdAt: GoodsOutDateFormat.date(day: 8, hour: 15, minute: 44),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 9, hour: 11, minute: 20),
                .picked: GoodsOutDateFormat.date(day: 9, hour: 11, minute: 48),
                .packing: GoodsOutDateFormat.date(day: 9, hour: 12, minute: 10),
                .packed: GoodsOutDateFormat.date(day: 9, hour: 12, minute: 31),
                .finalised: GoodsOutDateFormat.date(day: 9, hour: 12, minute: 40)
            ],
            age: .thisWeek,
            estimate: "~10 min picking, ~6 min packing",
            weightKg: 3.1,
            skoCount: 10,
            parcels: [
                GoodsOutParcel(number: 1, weightKg: 1.9, length: 40, width: 30, height: 20),
                GoodsOutParcel(number: 2, weightKg: 1.2, length: 30, width: 20, height: 15)
            ],
            shipments: [GoodsOutShipment(id: 1, shipper: "APC (GB)", tracking: "[TRK-4467-2210]", parcelCount: 2, cost: "[£ 5.80]", source: .api, labelPrinted: true)],
            leaflets: [GoodsOutLeaflet(id: "[Spring catalogue]", isPrinted: true)]
        ),
        GoodsOutDeliveryNote(
            reference: "AR24801",
            channel: .wholesale,
            customer: "[Customer]",
            customerNumber: "18831",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .dpd,
            state: .dispatched,
            picker: currentUser,
            lines: [
                line("SKO-00412", 8, picked: 8, packed: [1: 8]),
                line("SKO-00734", 4, picked: 4, packed: [2: 4])
            ],
            createdAt: GoodsOutDateFormat.date(day: 5, hour: 9, minute: 2),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 6, hour: 8, minute: 15),
                .picked: GoodsOutDateFormat.date(day: 6, hour: 8, minute: 44),
                .packing: GoodsOutDateFormat.date(day: 6, hour: 9, minute: 5),
                .packed: GoodsOutDateFormat.date(day: 6, hour: 9, minute: 30),
                .finalised: GoodsOutDateFormat.date(day: 6, hour: 9, minute: 41),
                .dispatched: GoodsOutDateFormat.date(day: 6, hour: 16, minute: 0)
            ],
            age: .thisWeek,
            estimate: "~12 min picking, ~6 min packing",
            weightKg: 4.4,
            skoCount: 12,
            parcels: [
                GoodsOutParcel(number: 1, weightKg: 2.6, length: 40, width: 30, height: 20),
                GoodsOutParcel(number: 2, weightKg: 1.8, length: 30, width: 20, height: 15)
            ],
            shipments: [GoodsOutShipment(id: 1, shipper: "DPD (GB)", tracking: "[TRK-1234-5678]", parcelCount: 2, cost: "[£ 6.20]", source: .api, labelPrinted: true)],
            leaflets: [GoodsOutLeaflet(id: "[Spring catalogue]", isPrinted: true), GoodsOutLeaflet(id: "[Returns form]", isPrinted: true)],
            invoiceGenerated: true,
            emails: GoodsOutSampleData.dispatchedEmails
        )
    ] + GoodsOutSampleData.dispatchNotes

    static let trolleys: [GoodsOutTrolley] = [
        GoodsOutTrolley(code: "T-03"),
        GoodsOutTrolley(code: "T-05"),
        GoodsOutTrolley(code: "T-07"),
        GoodsOutTrolley(code: "T-01", heldBy: "Delivery note AR24790"),
        GoodsOutTrolley(code: "T-02", heldBy: "Picking session PSaw-0142")
    ]

    static let pickedBays: [GoodsOutPickedBay] = [
        GoodsOutPickedBay(name: "Bay 1"),
        GoodsOutPickedBay(name: "Bay 2", otherNotes: 0),
        GoodsOutPickedBay(name: "Bay 4")
    ]

    static let pickers: [GoodsOutPickerProfile] = [
        GoodsOutPickerProfile(name: currentUser),
        GoodsOutPickerProfile(name: "Sam P.", session: "PSaw-0142", sessionOrders: 12, sessionTrolleys: 2),
        GoodsOutPickerProfile(name: "Jo K.")
    ]

    static func stockLocations(for line: GoodsOutLine, channel: GoodsOutChannel) -> [GoodsOutStockLocation] {
        var locations = [GoodsOutStockLocation(code: line.location, kind: "Picking location · \(channel.title)", stock: line.locationStock ?? 30)]
        if line.location != "C-04-02" {
            locations.append(GoodsOutStockLocation(code: "C-04-02", kind: "Storing", stock: 14))
        }
        if line.location != "B-02-01" {
            locations.append(GoodsOutStockLocation(code: "B-02-01", kind: "Picking location · Dropshipping", stock: 0))
        }
        return locations
    }

    static func pickingLocationSections(for line: GoodsOutLine, channel: GoodsOutChannel) -> [GoodsOutPickingLocationSection] {
        let alternative = line.location == "C-04-02" ? "A-03-02" : "C-04-02"
        let own = GoodsOutPickingLocationSection(
            channel: channel,
            options: [
                GoodsOutStockLocation(code: line.location, kind: "Picking", stock: line.locationStock ?? 30),
                GoodsOutStockLocation(code: alternative, kind: "Storing", stock: 14)
            ],
            selected: line.location
        )
        let otherChannel: GoodsOutChannel = channel == .dropshipping ? .wholesale : .dropshipping
        let other = GoodsOutPickingLocationSection(
            channel: otherChannel,
            options: [
                GoodsOutStockLocation(code: "B-02-01", kind: "Picking", stock: 0),
                GoodsOutStockLocation(code: "C-04-02", kind: "Storing", stock: 14)
            ],
            selected: "B-02-01"
        )
        return [own, other]
    }
}

struct GoodsOutPickingLocationSection: Identifiable, Hashable, Sendable {
    let channel: GoodsOutChannel
    let options: [GoodsOutStockLocation]
    var selected: String

    var id: GoodsOutChannel { channel }
}
