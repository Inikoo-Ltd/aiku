import Foundation

enum SampleData {
    static let warehouse = Warehouse(name: "Main Warehouse", code: "WH1")
    static let currentUser = StaffMember(name: "Alex Morgan")
    static let totalSKUs = 4812
    static let totalLocations = 1284
    static let unreadNotifications = 3

    static let goodsOut = GoodsOutSummary(
        unassigned: 12, inQueue: 36, handling: 18, blocked: 2, packed: 23,
        dispatchedToday: 142, plannedToday: 228
    )

    static let goodsIn = GoodsInSummary(dispatched: 3, received: 2, checked: 1)

    static let stockLevelCounts: [StockLevel: Int] = [.low: 17, .critical: 5, .outOfStock: 3]

    static let assignedTasks: [AssignedTask] = [
        AssignedTask(reference: "DN-24817", kind: .deliveryNote, status: "Handling", detail: "Delivery note · 14 items"),
        AssignedTask(reference: "SD-1093", kind: .stockDelivery, status: "Received", detail: "Stock delivery · 6 items to check"),
        AssignedTask(reference: "A-12-03", kind: .stockAudit, status: "In Process", detail: "Stock audit · location"),
    ]

    static let recentMovements: [StockMovement] = [
        StockMovement(id: "m1", title: "Purchase", detail: "SKU-00412 into A-03-02", quantityChange: 120, direction: .in, actor: "Sam P.", time: "10 min ago"),
        StockMovement(id: "m2", title: "Picked", detail: "SKU-00733 for DN-24790", quantityChange: -6, direction: .out, actor: "You", time: "24 min ago"),
        StockMovement(id: "m3", title: "Location transfer", detail: "B-01-04 → A-07-01", quantityChange: 40, direction: .neutral, actor: "Jo K.", time: "1 h ago"),
        StockMovement(id: "m4", title: "Adjustment", detail: "SKU-00953 · damaged", quantityChange: -2, direction: .out, actor: "Sam P.", time: "2 h ago"),
    ]

    static let skus: [SKU] = [
        SKU(
            reference: "SKU-00412", name: "Lavender essential oil 10ml", familyCode: "EO", stock: 1240, unitValue: 1.85, level: .normal,
            locations: [
                SKULocationStock(locationCode: "A-03-02", note: "Area A · picking", quantity: 120, isPicking: true),
                SKULocationStock(locationCode: "B-01-04", note: "Area B · overflow", quantity: 1120, isPicking: false),
            ],
            history: [
                StockMovement(id: "h412-1", title: "Purchase", detail: "SD-1090 · A-03-02 · today 08:55", quantityChange: 120, direction: .in, actor: "Sam P.", time: "today 08:55"),
            ]
        ),
        SKU(
            reference: "SKU-00733", name: "Bath bomb – rose 180g", familyCode: "BB", stock: 36, unitValue: 0.95, level: .low,
            locations: [
                SKULocationStock(locationCode: "A-03-02", note: "Area A · last audit 02 Nov", quantity: 30, isPicking: true),
                SKULocationStock(locationCode: "B-01-04", note: "Area B · overflow", quantity: 6, isPicking: false),
            ],
            history: [
                StockMovement(id: "h733-1", title: "Picked", detail: "DN-24790 · A-03-02 · today 09:12", quantityChange: -6, direction: .out, actor: "You", time: "today 09:12"),
                StockMovement(id: "h733-2", title: "Location transfer", detail: "B-01-04 → A-03-02 · yesterday", quantityChange: 20, direction: .neutral, actor: "Jo K.", time: "yesterday"),
                StockMovement(id: "h733-3", title: "Purchase", detail: "SD-1071 · B-01-04 · 04 Nov", quantityChange: 48, direction: .in, actor: "Sam P.", time: "04 Nov"),
            ]
        ),
        SKU(
            reference: "SKU-00953", name: "Incense sticks – sandalwood", familyCode: "IN", stock: 8, unitValue: 0.40, level: .critical,
            locations: [SKULocationStock(locationCode: "A-12-03", note: "Area A · audit in process", quantity: 8, isPicking: true)],
            history: [
                StockMovement(id: "h953-1", title: "Adjustment", detail: "Damaged · A-12-03 · today", quantityChange: -2, direction: .out, actor: "Sam P.", time: "today"),
            ]
        ),
        SKU(
            reference: "SKU-01187", name: "Soy wax candle – vanilla", familyCode: "CA", stock: 412, unitValue: 2.60, level: .normal,
            locations: [
                SKULocationStock(locationCode: "A-03-02", note: "Area A · picking", quantity: 48, isPicking: true),
                SKULocationStock(locationCode: "A-07-01", note: "Area A", quantity: 364, isPicking: false),
            ],
            history: []
        ),
        SKU(
            reference: "SKU-01402", name: "Eucalyptus essential oil 10ml", familyCode: "EO", stock: 0, unitValue: 1.70, level: .outOfStock,
            locations: [],
            history: []
        ),
    ]

    static let locations: [WarehouseLocation] = [
        WarehouseLocation(
            code: "A-03-02", area: "Area A", summary: "Area A · 6 SKUs · 120 kg · 0.8 CBM", stockValue: 2140, status: .operational,
            allowsStocks: true, allowsFulfilment: false, weightKg: 120, maxWeightKg: 250, cbm: 0.8, maxCbm: 1.2,
            skus: [
                LocationSKU(skuReference: "SKU-00733", name: "Bath bomb – rose 180g", quantity: 30),
                LocationSKU(skuReference: "SKU-00412", name: "Lavender essential oil 10ml", quantity: 120),
                LocationSKU(skuReference: "SKU-01187", name: "Soy wax candle – vanilla", quantity: 48),
            ],
            movements: [
                StockMovement(id: "l1", title: "Picked · SKU-00733", detail: "DN-24790 · today 09:12", quantityChange: -6, direction: .out, actor: "You", time: "today 09:12"),
                StockMovement(id: "l2", title: "Purchase · SKU-00412", detail: "SD-1090 · today 08:55", quantityChange: 120, direction: .in, actor: "Sam P.", time: "today 08:55"),
            ]
        ),
        WarehouseLocation(
            code: "A-07-01", area: "Area A", summary: "Area A · 3 SKUs · 64 kg · 0.4 CBM", stockValue: 980, status: .operational,
            allowsStocks: true, allowsFulfilment: false, weightKg: 64, maxWeightKg: 250, cbm: 0.4, maxCbm: 1.2,
            skus: [LocationSKU(skuReference: "SKU-01187", name: "Soy wax candle – vanilla", quantity: 364)],
            movements: []
        ),
        WarehouseLocation(
            code: "A-12-03", area: "Area A", summary: "Area A · 4 SKUs · 38 kg · 0.3 CBM", stockValue: 610, status: .auditInProcess,
            allowsStocks: true, allowsFulfilment: false, weightKg: 38, maxWeightKg: 250, cbm: 0.3, maxCbm: 1.2,
            skus: [LocationSKU(skuReference: "SKU-00953", name: "Incense sticks – sandalwood", quantity: 8)],
            movements: []
        ),
        WarehouseLocation(
            code: "B-01-04", area: "Area B", summary: "Area B · 2 SKUs · 210 kg · 1.6 CBM", stockValue: 1325, status: .operational,
            allowsStocks: true, allowsFulfilment: false, weightKg: 210, maxWeightKg: 400, cbm: 1.6, maxCbm: 2.4,
            skus: [
                LocationSKU(skuReference: "SKU-00412", name: "Lavender essential oil 10ml", quantity: 1120),
                LocationSKU(skuReference: "SKU-00733", name: "Bath bomb – rose 180g", quantity: 6),
            ],
            movements: []
        ),
        WarehouseLocation(
            code: "B-02-01", area: "Area B", summary: "Area B · 2 pallets · 480 kg · 2.4 CBM", stockValue: nil, status: .operational,
            allowsStocks: false, allowsFulfilment: true, weightKg: 480, maxWeightKg: 800, cbm: 2.4, maxCbm: 3.0,
            skus: [],
            movements: []
        ),
        WarehouseLocation(
            code: "C-04-02", area: "Area C", summary: "Area C · empty · not available for put-away", stockValue: 0, status: .broken,
            allowsStocks: true, allowsFulfilment: false, weightKg: 0, maxWeightKg: 250, cbm: 0, maxCbm: 1.2,
            skus: [],
            movements: []
        ),
    ]

    static let stockDeliveries: [StockDelivery] = [
        StockDelivery(
            reference: "SD-1093", supplier: "[Supplier name]", state: .received, timing: "6 items · arrived today 08:40",
            nextAction: "Check items", receivedAt: "Today 08:40",
            items: [
                StockDeliveryItem(skuReference: "SKU-00733", name: "Bath bomb – rose 180g", expected: 48, received: 48),
                StockDeliveryItem(skuReference: "SKU-00412", name: "Lavender essential oil 10ml", expected: 120, received: 108),
                StockDeliveryItem(skuReference: "SKU-01187", name: "Soy wax candle – vanilla", expected: 72, received: nil),
                StockDeliveryItem(skuReference: "SKU-00953", name: "Incense sticks – sandalwood", expected: 200, received: nil),
                StockDeliveryItem(skuReference: "SKU-01402", name: "Eucalyptus essential oil 10ml", expected: 60, received: nil),
            ]
        ),
        StockDelivery(reference: "SD-1091", supplier: "[Supplier name]", state: .checked, timing: "14 items · ready to place", nextAction: "Place in locations", receivedAt: "Yesterday", items: []),
        StockDelivery(reference: "SD-1096", supplier: "[Supplier name]", state: .dispatched, timing: "22 items · expected 11 Nov", nextAction: nil, receivedAt: nil, items: []),
        StockDelivery(reference: "SD-1097", supplier: "[Supplier name]", state: .dispatched, timing: "8 items · expected 12 Nov", nextAction: nil, receivedAt: nil, items: []),
        StockDelivery(reference: "SD-1088", supplier: "[Supplier name]", state: .received, timing: "3 items · arrived yesterday", nextAction: nil, receivedAt: "Yesterday", items: []),
    ]

    static let deliveryNotes: [DeliveryNote] = [
        DeliveryNote(
            reference: "DN-24817", customer: "[Customer]", shop: "UK shop", date: "09 Nov", state: .handling, weightKg: 6.2, picker: currentUser.name,
            items: [
                PickItem(skuReference: "SKU-00412", name: "Lavender essential oil 10ml", locationCode: "A-03-02", required: 6, picked: 6, locationStock: 120, isOutOfStock: false),
                PickItem(skuReference: "SKU-00733", name: "Bath bomb – rose 180g", locationCode: "A-03-02", required: 6, picked: 3, locationStock: 30, isOutOfStock: false),
                PickItem(skuReference: "SKU-01187", name: "Soy wax candle – vanilla", locationCode: "A-07-01", required: 1, picked: 0, locationStock: 364, isOutOfStock: false),
                PickItem(skuReference: "SKU-01402", name: "Eucalyptus essential oil 10ml", locationCode: nil, required: 1, picked: 0, locationStock: nil, isOutOfStock: true),
            ]
        ),
        unassignedNote("DN-24811", shop: "UK shop", items: 14, weight: 6.2),
        unassignedNote("DN-24812", shop: "EU shop", items: 3, weight: 1.1),
        unassignedNote("DN-24815", shop: "UK shop", items: 28, weight: 12.4),
        unassignedNote("DN-24816", shop: "Dropshipping", items: 1, weight: 0.3),
        unassignedNote("DN-24819", shop: "UK shop", items: 9, weight: 3.8),
        DeliveryNote(reference: "DN-24802", customer: "[Customer]", shop: "UK shop", date: "08 Nov", state: .inQueue, weightKg: 2.4, picker: nil,
                     items: [PickItem(skuReference: "SKU-00412", name: "Lavender essential oil 10ml", locationCode: "A-03-02", required: 4, picked: 0, locationStock: 120, isOutOfStock: false)]),
    ]

    private static func unassignedNote(_ reference: String, shop: String, items: Int, weight: Double) -> DeliveryNote {
        DeliveryNote(
            reference: reference, customer: "[Customer]", shop: shop, date: "09 Nov", state: .unassigned, weightKg: weight, picker: nil,
            items: [PickItem(skuReference: "SKU-00412", name: "Lavender essential oil 10ml", locationCode: "A-03-02", required: items, picked: 0, locationStock: 120, isOutOfStock: false)]
        )
    }

    static func sku(_ reference: String) -> SKU? { skus.first { $0.reference == reference } }
    static func location(_ code: String) -> WarehouseLocation? { locations.first { $0.code == code } }
    static func stockDelivery(_ reference: String) -> StockDelivery? { stockDeliveries.first { $0.reference == reference } }
    static func deliveryNote(_ reference: String) -> DeliveryNote? { deliveryNotes.first { $0.reference == reference } }
}
