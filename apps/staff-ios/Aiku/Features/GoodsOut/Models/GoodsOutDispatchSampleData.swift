import Foundation

extension GoodsOutSampleData {
    static let apiShippers: [GoodsOutShipperOption] = [
        GoodsOutShipperOption(id: GoodsOutShipper.dpd.rawValue, title: GoodsOutShipper.dpd.title, detail: "[phone] · [tracking url]", hasAPI: true),
        GoodsOutShipperOption(id: GoodsOutShipper.apc.rawValue, title: GoodsOutShipper.apc.title, detail: "[phone] · [tracking url]", hasAPI: true)
    ]

    static let otherShippers: [GoodsOutShipperOption] = [
        GoodsOutShipperOption(id: "local-courier", title: "[Local courier]", detail: "No API · tracking number by hand", hasAPI: false),
        GoodsOutShipperOption(id: "pallet-carrier", title: "[Pallet carrier]", detail: "No API · tracking number by hand", hasAPI: false),
        GoodsOutShipperOption(id: "packeta-sk", title: "Packeta (SK)", detail: "API · label created automatically", hasAPI: true)
    ]

    static let printers: [GoodsOutPrinter] = [
        GoodsOutPrinter(id: "Dispatch-1 · A4", detail: "Packing bench 1 · last used"),
        GoodsOutPrinter(id: "Goods-out office · A4")
    ]

    static let tradeUnits: [String: GoodsOutTradeUnit] = [
        "SKO-00412": GoodsOutTradeUnit(tariff: "3301 29", origin: "GB", isDangerous: true),
        "SKO-00733": GoodsOutTradeUnit(tariff: "3307 30", origin: "GB"),
        "SKO-01187": GoodsOutTradeUnit(tariff: nil, origin: "CN"),
        "SKO-00953": GoodsOutTradeUnit(tariff: "3307 41", origin: "IN"),
        "SKO-00734": GoodsOutTradeUnit(tariff: "3307 30", origin: "GB"),
        "SKO-01402": GoodsOutTradeUnit(tariff: "3301 29", origin: "GB", isDangerous: true)
    ]

    static let waitingChat: [GoodsOutChatMessage] = [
        GoodsOutChatMessage(id: 1, byline: "Jo K. (packer)", sentAt: GoodsOutDateFormat.date(day: 10, hour: 11, minute: 5), text: "SKO-00953: 1 of 2 found, pack crushed. Substitute or send 1?"),
        GoodsOutChatMessage(id: 2, byline: "[CS name] · CRM", sentAt: GoodsOutDateFormat.date(day: 10, hour: 11, minute: 20), text: "Customer agreed: send 1, refund the other. I’ve updated the order.")
    ]

    static let dispatchedEmails: [GoodsOutEmail] = [
        GoodsOutEmail(id: 3, title: "Dispatch confirmation", recipient: "[customer email]", sentAt: GoodsOutDateFormat.date(day: 10, hour: 14, minute: 22), status: .opened),
        GoodsOutEmail(id: 2, title: "Tracking update", recipient: "[customer email]", sentAt: GoodsOutDateFormat.date(day: 10, hour: 14, minute: 23), status: .delivered),
        GoodsOutEmail(id: 1, title: "Dispatch confirmation", recipient: "[client email]", sentAt: GoodsOutDateFormat.date(day: 9, hour: 16, minute: 2), status: .softBounce, orderReference: "AR24790")
    ]

    static let dispatchNotes: [GoodsOutDeliveryNote] = [
        GoodsOutDeliveryNote(
            reference: "AR24835",
            channel: .b2c,
            customer: "[Customer]",
            customerNumber: "21544",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .dpd,
            state: .packed,
            picker: "Jo K.",
            lines: [
                line("SKO-00412", 4, picked: 4, packed: [1: 4]),
                line("SKO-00733", 6, picked: 6, packed: [1: 4, 2: 2]),
                line("SKO-01187", 2, picked: 2, packed: [2: 2]),
                line("SKO-00953", 2, outcome: .notPicked(.outOfStock))
            ],
            customerInstructions: "Leave with the neighbour if nobody is in.",
            createdAt: GoodsOutDateFormat.date(day: 9, hour: 17, minute: 40),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 10, hour: 8, minute: 5),
                .picked: GoodsOutDateFormat.date(day: 10, hour: 8, minute: 31),
                .packing: GoodsOutDateFormat.date(day: 10, hour: 8, minute: 50),
                .packed: GoodsOutDateFormat.date(day: 10, hour: 9, minute: 6)
            ],
            age: .yesterday,
            estimate: "~14 min picking, ~8 min packing",
            weightKg: 5.5,
            skoCount: 12,
            parcels: [
                GoodsOutParcel(number: 1, weightKg: 3.4, length: 40, width: 30, height: 20),
                GoodsOutParcel(number: 2, weightKg: 2.1, length: 30, width: 20, height: 15)
            ],
            leaflets: [GoodsOutLeaflet(id: "[Spring catalogue]", isPrinted: true), GoodsOutLeaflet(id: "[Returns form]")]
        ),
        GoodsOutDeliveryNote(
            reference: "AR24838",
            channel: .b2c,
            customer: "[Customer]",
            customerNumber: "21610",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .collection,
            state: .finalised,
            picker: "Sam P.",
            lines: [
                line("SKO-00733", 2, picked: 2, packed: [1: 2]),
                line("SKO-01402", 1, picked: 1, packed: [1: 1])
            ],
            createdAt: GoodsOutDateFormat.date(day: 9, hour: 12, minute: 15),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 9, hour: 15, minute: 0),
                .picked: GoodsOutDateFormat.date(day: 9, hour: 15, minute: 9),
                .packing: GoodsOutDateFormat.date(day: 9, hour: 15, minute: 20),
                .packed: GoodsOutDateFormat.date(day: 9, hour: 15, minute: 28),
                .finalised: GoodsOutDateFormat.date(day: 9, hour: 15, minute: 30)
            ],
            age: .yesterday,
            estimate: "~4 min picking, ~3 min packing",
            weightKg: 0.7,
            skoCount: 3,
            listDetail: "Collection · ready to collect",
            parcels: [GoodsOutParcel(number: 1, weightKg: 0.7, length: 25, width: 20, height: 10)],
            leaflets: [GoodsOutLeaflet(id: "[Spring catalogue]", isPrinted: true)]
        ),
        GoodsOutDeliveryNote(
            reference: "AR24790",
            channel: .b2c,
            customer: "[Customer]",
            customerNumber: "20994",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            shipper: .apc,
            state: .picked,
            picker: "Jo K.",
            pickedBay: "Bay 3",
            lines: [
                line("SKO-00412", 1, picked: 1),
                line("SKO-00733", 1, picked: 1),
                line("SKO-00953", 1, picked: 1)
            ],
            createdAt: GoodsOutDateFormat.date(day: 9, hour: 7, minute: 55),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 10, hour: 7, minute: 10),
                .picked: GoodsOutDateFormat.date(day: 10, hour: 7, minute: 22)
            ],
            age: .yesterday,
            estimate: "~4 min picking, ~3 min packing",
            weightKg: 1.2,
            skoCount: 3
        ),
        GoodsOutDeliveryNote(
            reference: "AR24842",
            channel: .marketplaces,
            platform: "Faire",
            customer: "[Customer]",
            customerNumber: "22476",
            contactName: "[Contact name]",
            email: "[email]",
            phone: "[phone]",
            deliveryAddress: .sample,
            state: .picked,
            picker: "Sam P.",
            pickedBay: "Bay 3",
            lines: [line("SKO-01402", 1, picked: 1)],
            createdAt: GoodsOutDateFormat.date(day: 10, hour: 6, minute: 30),
            stateDates: [
                .picking: GoodsOutDateFormat.date(day: 10, hour: 8, minute: 12),
                .picked: GoodsOutDateFormat.date(day: 10, hour: 8, minute: 15)
            ],
            estimate: "~2 min picking, ~2 min packing",
            weightKg: 0.3,
            skoCount: 1,
            listDetail: "shipped by Faire"
        )
    ]
}
