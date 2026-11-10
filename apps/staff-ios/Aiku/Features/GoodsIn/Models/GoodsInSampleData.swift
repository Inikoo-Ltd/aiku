import Foundation

enum GoodsInSampleData {
    static let containerFacts: [GoodsInFact] = [
        GoodsInFact("Type", "Container"),
        GoodsInFact("Container size", "40 ft"),
        GoodsInFact("Incoterm", "FOB"),
        GoodsInFact("Port of export", "[Port]"),
        GoodsInFact("Port of import", "Felixstowe"),
        GoodsInFact("Container number", "[MSCU 123456 7]"),
        GoodsInFact("Shipper reference", "[REF-8812]"),
        GoodsInFact("Tracking number", "[TRK-0042]"),
        GoodsInFact("Shipping agent", "[Agent]")
    ]

    static let parcelFacts: [GoodsInFact] = [
        GoodsInFact("Type", "Parcel"),
        GoodsInFact("Tracking number", "[TRK-0042]"),
        GoodsInFact("Shipping agent", "[Agent]")
    ]

    static let checks: [GoodsInDeliveryCheck] = [
        GoodsInDeliveryCheck(id: "packaging", title: "[Check · packaging intact]", result: .passed, detail: "Alex Morgan · 08:52"),
        GoodsInDeliveryCheck(id: "labels", title: "[Check · labels match the delivery]", result: .failed, detail: "“2 cartons without labels” · incident: [type] · qty 2"),
        GoodsInDeliveryCheck(id: "pallets", title: "[Check · pallets wrapped]")
    ]

    static let freshChecks: [GoodsInDeliveryCheck] = [
        GoodsInDeliveryCheck(id: "packaging", title: "[Check · packaging intact]"),
        GoodsInDeliveryCheck(id: "labels", title: "[Check · labels match the delivery]"),
        GoodsInDeliveryCheck(id: "pallets", title: "[Check · pallets wrapped]")
    ]

    static func bathBomb(checked: Int? = nil, placed: Bool = false) -> GoodsInItem {
        GoodsInItem(
            sko: "SKO-00733", name: "Bath bomb – rose 180g", delivered: 48, checked: checked,
            placements: placed ? [GoodsInPlacement(location: "A-03-02", quantity: 48)] : [],
            pickingLocation: "A-03-02", pickingSummary: "A-03-02 for Wholesale",
            locationOptions: [GoodsInLocationOption(code: "A-03-02", detail: "Picking location · Wholesale · 30 in location")]
        )
    }

    static func lavender(checked: Int? = nil, placed: Bool = false) -> GoodsInItem {
        GoodsInItem(
            sko: "SKO-00412", name: "Lavender essential oil 10ml", delivered: 120, checked: checked,
            placements: placed ? [GoodsInPlacement(location: "A-01-01", quantity: checked ?? 0)] : [],
            pickingLocation: "A-01-01", pickingSummary: "A-01-01 for Wholesale",
            locationOptions: [GoodsInLocationOption(code: "A-01-01", detail: "Picking location · Wholesale · 40 in location")]
        )
    }

    static func eucalyptus(checked: Int? = nil) -> GoodsInItem {
        GoodsInItem(
            sko: "SKO-01402", name: "Eucalyptus essential oil 10ml", delivered: 60, checked: checked,
            pickingLocation: "A-01-02", pickingSummary: "A-01-02 for Wholesale",
            locationOptions: [GoodsInLocationOption(code: "A-01-02", detail: "Picking location · Wholesale · 12 in location")]
        )
    }

    static func candle(checked: Int? = nil) -> GoodsInItem {
        GoodsInItem(
            sko: "SKO-01187", name: "Soy wax candle – vanilla", delivered: 72, checked: checked,
            pickingLocation: "A-07-01", pickingSummary: "A-07-01 for Wholesale · B-02-01 for Dropshipping",
            locationOptions: [
                GoodsInLocationOption(code: "A-07-01", detail: "Picking location · Wholesale · 30 in location"),
                GoodsInLocationOption(code: "C-04-02", detail: "Storing · 6 in location")
            ]
        )
    }

    static func incense(checked: Int? = nil) -> GoodsInItem {
        GoodsInItem(
            sko: "SKO-00953", name: "Incense sticks – sandalwood", delivered: 200, checked: checked,
            pickingLocation: "B-01-04", pickingSummary: "B-01-04 for Wholesale",
            locationOptions: [
                GoodsInLocationOption(code: "B-01-04", detail: "Picking location · Wholesale · 80 in location"),
                GoodsInLocationOption(code: "C-04-02", detail: "Storing · 6 in location")
            ]
        )
    }

    static func roseQuartz(checked: Int? = nil) -> GoodsInItem {
        GoodsInItem(
            sko: "SKO-02210", name: "Rose quartz tumble stones", delivered: 6, checked: checked,
            suggestedLocation: GoodsInLocationOption(code: "A-09-04", detail: "Area A · empty"),
            isNewSko: true
        )
    }

    static func generatedItems(count: Int, startingAt first: Int, newSkos: Int = 0) -> [GoodsInItem] {
        (0..<count).map { offset in
            let number = first + offset
            let code = String(format: "SKO-%05d", number)
            return GoodsInItem(
                sko: code, name: "[Product name]", delivered: 24 + (offset % 5) * 12,
                pickingLocation: offset < count - newSkos ? "B-0\(offset % 4 + 1)-0\(offset % 6 + 1)" : nil,
                pickingSummary: offset < count - newSkos ? "B-0\(offset % 4 + 1)-0\(offset % 6 + 1) for Wholesale" : nil,
                locationOptions: offset < count - newSkos ? [GoodsInLocationOption(code: "B-0\(offset % 4 + 1)-0\(offset % 6 + 1)", detail: "Picking location · Wholesale")] : [],
                suggestedLocation: GoodsInLocationOption(code: "A-09-04", detail: "Area A · empty"),
                isNewSko: offset >= count - newSkos
            )
        }
    }

    static func deliveries(now: Date = .now) -> [GoodsInDelivery] {
        let day: TimeInterval = 24 * 3600
        let today = Calendar.current.startOfDay(for: now)
        var list: [GoodsInDelivery] = [
            GoodsInDelivery(
                reference: "SDaw-0093", source: .supplier, sourceName: "[Supplier]", phase: .inGoodsIn,
                dispatchedAt: today.addingTimeInterval(-5 * day), receivedLabel: "today 08:40",
                deliveryFacts: containerFacts, weight: "312 kg · partial",
                items: [bathBomb(checked: 48), lavender(checked: 108), eucalyptus(checked: 0), candle(), incense(), roseQuartz()],
                checks: checks
            ),
            GoodsInDelivery(
                reference: "SDaw-0088", source: .agent, sourceName: "[Agent]", phase: .inGoodsIn,
                dispatchedAt: today.addingTimeInterval(-9 * day), receivedLabel: "yesterday",
                orderer: "[Agent name] · [AGT-CODE]", purchaseOrders: "Purchase order POaw-0198",
                estimatedReceiving: "Estimated receiving 09 Nov 2026",
                deliveryFacts: parcelFacts, weight: "46 kg",
                items: generatedItems(count: 3, startingAt: 3101),
                checks: freshChecks
            ),
            GoodsInDelivery(
                reference: "SDaw-0096", source: .supplier, sourceName: "[Supplier]", phase: .dispatched,
                dispatchedAt: today.addingTimeInterval(-3 * day),
                deliveryFacts: containerFacts, weight: "1,240 kg", volume: "18.4 m³",
                items: generatedItems(count: 22, startingAt: 3201, newSkos: 2),
                checks: freshChecks
            ),
            GoodsInDelivery(
                reference: "SDaw-0097", source: .supplier, sourceName: "[Supplier]", phase: .dispatched,
                dispatchedAt: today.addingTimeInterval(-1 * day),
                deliveryFacts: parcelFacts, weight: "84 kg", volume: "1.2 m³",
                items: generatedItems(count: 4, startingAt: 3301),
                checks: freshChecks
            ),
            GoodsInDelivery(
                reference: "SDaw-0095", source: .agent, sourceName: "[Agent]", phase: .dispatched,
                dispatchedAt: today.addingTimeInterval(9 * 3600),
                orderer: "[Agent name] · [AGT-CODE]", purchaseOrders: "Purchase order POaw-0207",
                deliveryFacts: containerFacts, weight: "620 kg", volume: "9.1 m³",
                items: generatedItems(count: 8, startingAt: 3401, newSkos: 1),
                checks: freshChecks
            ),
            GoodsInDelivery(
                reference: "SDaw-0091", source: .supplier, sourceName: "[Supplier]", phase: .inGoodsIn, isBookingIn: true,
                dispatchedAt: today.addingTimeInterval(-12 * day), receivedLabel: "06 Nov",
                deliveryFacts: containerFacts, weight: "298 kg",
                items: [bathBomb(checked: 48, placed: true), lavender(checked: 108, placed: true), candle(checked: 72), incense(checked: 200), roseQuartz(checked: 6)],
                checks: checks.map { var check = $0; if check.result == .toDo { check.result = .passed; check.detail = "Alex Morgan · 09:10" }; return check }
            ),
            GoodsInDelivery(
                reference: "SDaw-0090", source: .production, sourceName: "[Production]", phase: .inGoodsIn,
                dispatchedAt: today.addingTimeInterval(-2 * day), receivedLabel: "08 Nov",
                orderer: "[Production line] · [PRD-CODE]", purchaseOrders: "Production order PRaw-0031",
                deliveryFacts: [GoodsInFact("Type", "Internal transfer")], weight: "38 kg",
                items: generatedItems(count: 4, startingAt: 3501).map { var item = $0; item.checked = item.delivered; return item },
                checks: freshChecks
            )
        ]
        let bookedIn: [(String, GoodsInSource, String, Int)] = [
            ("SDaw-0089", .supplier, "[Supplier]", 1), ("SDaw-0087", .supplier, "[Supplier]", 1),
            ("SDaw-0086", .partner, "[Partner]", 2), ("SDaw-0085", .supplier, "[Supplier]", 2),
            ("SDaw-0084", .agent, "[Agent]", 3), ("SDaw-0083", .supplier, "[Supplier]", 3),
            ("SDaw-0082", .supplier, "[Supplier]", 4), ("SDaw-0081", .agent, "[Agent]", 5),
            ("SDaw-0080", .supplier, "[Supplier]", 5), ("SDaw-0079", .supplier, "[Supplier]", 6),
            ("SDaw-0078", .supplier, "[Supplier]", 6), ("SDaw-0077", .supplier, "[Supplier]", 6)
        ]
        for (index, entry) in bookedIn.enumerated() {
            let items = generatedItems(count: 2 + index % 3, startingAt: 3600 + index * 10).map { item -> GoodsInItem in
                var placed = item
                placed.checked = item.delivered
                placed.placements = [GoodsInPlacement(location: item.pickingLocation ?? "A-09-04", quantity: item.delivered)]
                return placed
            }
            list.append(GoodsInDelivery(
                reference: entry.0, source: entry.1, sourceName: entry.2, phase: .inGoodsIn, isBookingIn: true,
                dispatchedAt: today.addingTimeInterval(-Double(entry.3 + 8) * day), receivedLabel: "earlier",
                bookedInAt: today.addingTimeInterval(-Double(entry.3) * day + 10 * 3600),
                deliveryFacts: parcelFacts, weight: "—",
                items: items,
                checks: checks.map { var check = $0; check.result = .passed; check.detail = "Alex Morgan"; return check }
            ))
        }
        return list
    }

    static func returns() -> [GoodsInReturn] {
        [
            GoodsInReturn(reference: "[Return ref] 0412", customer: "[Customer]", items: [
                GoodsInReturnItem(sko: "SKO-00412", name: "Lavender essential oil 10ml", quantity: 2, homeLocation: "A-01-01"),
                GoodsInReturnItem(sko: "SKO-00953", name: "Incense sticks – sandalwood", quantity: 5, homeLocation: "B-01-04"),
                GoodsInReturnItem(sko: "SKO-02210", name: "Rose quartz tumble stones", quantity: 1, homeLocation: "A-09-04")
            ]),
            GoodsInReturn(reference: "[Return ref] 0409", customer: "[Customer]", items: [
                GoodsInReturnItem(sko: "SKO-00733", name: "Bath bomb – rose 180g", quantity: 2, homeLocation: "A-03-02", outcome: .toLocation("A-03-02")),
                GoodsInReturnItem(sko: "SKO-01187", name: "Soy wax candle – vanilla", quantity: 1, homeLocation: "A-07-01")
            ]),
            GoodsInReturn(reference: "[Return ref] 0401", customer: "[Customer]", items: [
                GoodsInReturnItem(sko: "SKO-00733", name: "Bath bomb – rose 180g", quantity: 1, homeLocation: "A-03-02", outcome: .toLocation("A-03-02")),
                GoodsInReturnItem(sko: "SKO-00412", name: "Lavender essential oil 10ml", quantity: 3, homeLocation: "A-01-01", outcome: .toLocation("A-01-01")),
                GoodsInReturnItem(sko: "SKO-01402", name: "Eucalyptus essential oil 10ml", quantity: 1, homeLocation: "A-01-02", outcome: .damaged),
                GoodsInReturnItem(sko: "SKO-01187", name: "Soy wax candle – vanilla", quantity: 2, homeLocation: "A-07-01", outcome: .toLocation("A-07-01"))
            ], isSetAsReturned: true)
        ]
    }
}
