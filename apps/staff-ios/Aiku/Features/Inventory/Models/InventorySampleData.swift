import Foundation

enum InventorySampleData {
    static let dashboard = InventoryDashboardSummary(
        urgentAudits: 14,
        toReplenish: 22,
        toReplenishDropshipping: 3,
        replenishments: 9,
        lowStockAudits: 14,
        belowMinimum: 31,
        negativeStocks: 2,
        wrongWeights: 5,
        withoutProduct: 3,
        stockValueLabel: "[£ 412k]",
        utilisationPercent: 86,
        usedLocations: 1104,
        totalLocations: 1284
    )

    static let families: [InventoryFamily] = [
        InventoryFamily(code: "BB", name: "Bath bombs", state: .active, skoCount: 42, stockValueLabel: "[£ 3.9k]", coverWeeks: 6),
        InventoryFamily(code: "EO", name: "Essential oils", state: .active, skoCount: 118, stockValueLabel: "[£ 21k]", coverWeeks: 9),
        InventoryFamily(code: "CA", name: "Candles", state: .active, skoCount: 64, stockValueLabel: "[£ 8.2k]", coverWeeks: 4),
        InventoryFamily(code: "IN", name: "Incense", state: .inProcess, skoCount: 27, stockValueLabel: "[£ 1.6k]", coverWeeks: 5),
        InventoryFamily(code: "SO", name: "Soap", state: .discontinuing, skoCount: 19, stockValueLabel: "[£ 0.9k]", coverWeeks: 3),
        InventoryFamily(code: "GE", name: "Gemstones", state: .discontinued, skoCount: 8, stockValueLabel: "[£ 0.4k]", coverWeeks: 0)
    ]

    static let skos: [InventorySko] = [
        InventorySko(
            reference: "SKO-00733", name: "Bath bomb – rose 180g", familyCode: "BB", state: .active,
            supplierCode: "AWBB-ROSE-180", unitsPerSko: 6, barcode: "[CODE128]", unitBarcode: "[5060000000017]",
            pickerNote: "fragile, keep upright", reserved: 12, toBePicked: 6, coverDays: 9, isOnDemand: true,
            unitValue: 2.10, criticalAt: 6, lowAt: 40, excessAt: 300, abnormalityNote: nil,
            locations: [
                InventorySkoLocation(code: "A-03-02", type: .picking, channel: .wholesale, quantity: 30, minimum: 12, maximum: 48, lastAudited: "28 Oct"),
                InventorySkoLocation(code: "B-02-01", type: .picking, channel: .dropshipping, quantity: 0, minimum: 6, maximum: 24),
                InventorySkoLocation(code: "C-04-02", type: .storing, quantity: 6, replenish: 24, lastAudited: "02 Nov")
            ]
        ),
        InventorySko(
            reference: "SKO-00412", name: "Lavender essential oil 10ml", familyCode: "EO", state: .active,
            supplierCode: "AWEO-LAV-10", unitsPerSko: 12, barcode: "[CODE128]", unitBarcode: "[5060000000024]",
            pickerNote: nil, reserved: 24, toBePicked: 0, coverDays: 38, isOnDemand: false,
            unitValue: 1.85, criticalAt: 10, lowAt: 60, excessAt: 400, abnormalityNote: nil,
            locations: [
                InventorySkoLocation(code: "A-03-02", type: .picking, channel: .wholesale, quantity: 100, minimum: 24, maximum: 120, lastAudited: "30 Oct"),
                InventorySkoLocation(code: "C-02-03", type: .storing, quantity: 120, replenish: 48, lastAudited: "15 Oct")
            ]
        ),
        InventorySko(
            reference: "SKO-01187", name: "Soy wax candle – vanilla", familyCode: "CA", state: .active,
            supplierCode: "AWCA-VAN-01", unitsPerSko: 4, barcode: "[CODE128]", unitBarcode: "[5060000000031]",
            pickerNote: nil, reserved: 2, toBePicked: 2, coverDays: 3, isOnDemand: false,
            unitValue: 4.40, criticalAt: 8, lowAt: 30, excessAt: 200, abnormalityNote: nil,
            locations: [
                InventorySkoLocation(code: "A-07-01", type: .picking, channel: .wholesale, quantity: 4, minimum: 8, maximum: 32, lastAudited: "05 Nov")
            ]
        ),
        InventorySko(
            reference: "SKO-00953", name: "Incense sticks – sandalwood", familyCode: "IN", state: .active,
            supplierCode: "AWIN-SAN-20", unitsPerSko: 20, barcode: "[CODE128]", unitBarcode: "[5060000000048]",
            pickerNote: nil, reserved: 0, toBePicked: 0, coverDays: 0, isOnDemand: false,
            unitValue: 0.95, criticalAt: 10, lowAt: 40, excessAt: 300, abnormalityNote: nil,
            locations: [
                InventorySkoLocation(code: "B-01-04", type: .picking, channel: .wholesale, quantity: 0, minimum: 10, maximum: 60, lastAudited: "10 Nov")
            ]
        ),
        InventorySko(
            reference: "SKO-01402", name: "Eucalyptus essential oil 10ml", familyCode: "EO", state: .active,
            supplierCode: "AWEO-EUC-10", unitsPerSko: 12, barcode: "[CODE128]", unitBarcode: "[5060000000055]",
            pickerNote: nil, reserved: 36, toBePicked: 12, coverDays: 120, isOnDemand: false,
            unitValue: 1.70, criticalAt: 10, lowAt: 60, excessAt: 400, abnormalityNote: nil,
            locations: [
                InventorySkoLocation(code: "A-04-01", type: .picking, channel: .wholesale, quantity: 160, minimum: 24, maximum: 160, lastAudited: "01 Nov"),
                InventorySkoLocation(code: "D-01-01", type: .storing, quantity: 800, replenish: 96, lastAudited: "10 Nov")
            ]
        ),
        InventorySko(
            reference: "SKO-00734", name: "Bath bomb – lavender 180g", familyCode: "BB", state: .active,
            supplierCode: "AWBB-LAV-180", unitsPerSko: 6, barcode: "[CODE128]", unitBarcode: "[5060000000062]",
            pickerNote: "fragile, keep upright", reserved: 18, toBePicked: 6, coverDays: 32, isOnDemand: false,
            unitValue: 2.10, criticalAt: 6, lowAt: 40, excessAt: 300, abnormalityNote: nil,
            locations: [
                InventorySkoLocation(code: "A-03-03", type: .picking, channel: .wholesale, quantity: 90, minimum: 12, maximum: 96, lastAudited: "27 Oct"),
                InventorySkoLocation(code: "C-04-03", type: .storing, quantity: 120, replenish: 24, lastAudited: "02 Nov")
            ]
        ),
        InventorySko(
            reference: "SKO-00740", name: "Bath bomb gift set ×6", familyCode: "BB", state: .active,
            supplierCode: "AWBB-GIFT-6", unitsPerSko: 4, barcode: "[CODE128]", unitBarcode: "[5060000000079]",
            pickerNote: nil, reserved: 4, toBePicked: 0, coverDays: 70, isOnDemand: false,
            unitValue: 9.50, criticalAt: 4, lowAt: 20, excessAt: 120, abnormalityNote: nil,
            locations: [
                InventorySkoLocation(code: "A-05-03", type: .picking, channel: .wholesale, quantity: 148, minimum: 8, maximum: 60, lastAudited: "20 Oct")
            ]
        ),
        InventorySko(
            reference: "SKO-X-0192", name: "[Name]", familyCode: nil, state: .abnormality,
            supplierCode: nil, unitsPerSko: 1, barcode: "[CODE128]", unitBarcode: "[EAN]",
            pickerNote: nil, reserved: 0, toBePicked: 0, coverDays: 0, isOnDemand: false,
            unitValue: 0, criticalAt: 0, lowAt: 0, excessAt: 1000, abnormalityNote: "no stock record match",
            locations: []
        ),
        InventorySko(
            reference: "SKO-X-0207", name: "[Name]", familyCode: nil, state: .abnormality,
            supplierCode: nil, unitsPerSko: 1, barcode: "[CODE128]", unitBarcode: "[EAN]",
            pickerNote: nil, reserved: 0, toBePicked: 0, coverDays: 0, isOnDemand: false,
            unitValue: 0, criticalAt: 0, lowAt: 0, excessAt: 1000, abnormalityNote: nil,
            locations: []
        )
    ]

    static let movements: [InventoryStockMovement] = [
        InventoryStockMovement(
            skoReference: "SKO-00412", kind: .purchase, quantity: 120,
            summary: "A-03-02 · Sam P. · 10:22 · running 220",
            todaySummary: "into A-03-02 · Sam P. · 10 min ago"
        ),
        InventoryStockMovement(
            skoReference: "SKO-00733", kind: .picked, quantity: -6,
            summary: "A-03-02 · you · 10:08 · delivery note AR24790 · running 36",
            todaySummary: "for delivery note AR24790 · you · 24 min ago"
        ),
        InventoryStockMovement(
            skoReference: "SKO-00953", kind: .audit, quantity: -2,
            summary: "B-01-04 · Damaged stock · Jo K. · 08:40 · running 0",
            todaySummary: "Damaged stock · B-01-04 · Jo K. · 2 h ago"
        ),
        InventoryStockMovement(
            skoReference: "SKO-01187", kind: .locationTransfer, quantity: 24,
            summary: "C-04-02 → A-07-01 · Wrong bin location · Sam P."
        ),
        InventoryStockMovement(
            skoReference: "SKO-01402", kind: .audit, quantity: 4,
            summary: "D-01-01 · Count gain (stock found) · Jo K."
        ),
        InventoryStockMovement(
            skoReference: "SKO-00740", kind: .consumption, quantity: -1,
            summary: "A-05-03 · Internal consumption · showroom"
        )
    ]

    static let locationOptions: [InventoryLocationOption] = [
        InventoryLocationOption(code: "A-09-04", detail: "Area A · empty"),
        InventoryLocationOption(code: "A-07-01", detail: "Area A · 1 SKO"),
        InventoryLocationOption(code: "A-03-02", detail: "Area A · 2 SKOs"),
        InventoryLocationOption(code: "B-03-02", detail: "Area B · empty"),
        InventoryLocationOption(code: "C-04-02", detail: "Area C · 1 SKO"),
        InventoryLocationOption(code: "C-05-01", detail: "Area C · empty"),
        InventoryLocationOption(code: "D-02-01", detail: "Area D · empty")
    ]
}
