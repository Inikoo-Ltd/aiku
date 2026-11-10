import Foundation

enum FulfilmentSampleData {
    static let customerA = "[Customer A]"
    static let customerB = "[Customer B]"

    static let storageLocations = ["D-02-04", "D-02-05", "D-02-06", "D-03-02", "D-03-03", "D-04-01", "D-04-02", "D-05-02"]

    static let storedItems: [FulfilmentStoredItem] = [
        FulfilmentStoredItem(code: "MUG-BLUE", name: "Ceramic mug – blue", customer: customerA, ean: "5060000000017"),
        FulfilmentStoredItem(code: "MUG-GREEN", name: "Ceramic mug – green", customer: customerA),
        FulfilmentStoredItem(code: "TOWEL-SET", name: "Tea towel set", customer: customerA),
        FulfilmentStoredItem(code: "GIFT-L", name: "Gift box large", customer: customerB, status: .discontinuing),
        FulfilmentStoredItem(code: "REED-100", name: "Reed diffuser 100ml", customer: customerB),
        FulfilmentStoredItem(code: "CANDLE-AMB", name: "Soy candle – amber", customer: customerB)
    ]

    static var pallets: [FulfilmentPallet] {
        var all: [FulfilmentPallet] = [
            FulfilmentPallet(reference: "bravo-p0118", customer: customerB, type: .pallet, customerReference: "Spring stock 1", state: .received),
            FulfilmentPallet(reference: "bravo-p0119", customer: customerB, type: .pallet, customerReference: "Spring stock 2", state: .received)
        ]
        all += (120...127).map { number in
            FulfilmentPallet(reference: "bravo-p0\(number)", customer: customerB, type: .box, customerReference: "Gift sets", state: .received)
        }
        all += (235...240).map { number in
            FulfilmentPallet(reference: "acme-p0\(number)", customer: customerA, type: .pallet, state: .confirmed)
        }
        all += [
            FulfilmentPallet(reference: "acme-p0231", customer: customerA, type: .pallet, state: .bookedIn, location: "D-02-01"),
            FulfilmentPallet(reference: "acme-p0232", customer: customerA, type: .pallet, state: .bookedIn, location: "D-02-02"),
            FulfilmentPallet(reference: "acme-p0233", customer: customerA, type: .oversize, state: .bookedIn, location: "D-05-01"),
            FulfilmentPallet(reference: "acme-p0234", customer: customerA, type: .pallet, customerReference: "Winter stock 4", state: .bookingIn),
            FulfilmentPallet(
                reference: "acme-p0140", customer: customerA, type: .pallet, customerReference: "Spring range", state: .storing, location: "D-03-01",
                items: [
                    FulfilmentStoredQuantity(itemCode: "MUG-BLUE", quantity: 40),
                    FulfilmentStoredQuantity(itemCode: "MUG-GREEN", quantity: 4),
                    FulfilmentStoredQuantity(itemCode: "TOWEL-SET", quantity: 18)
                ]
            ),
            FulfilmentPallet(
                reference: "bravo-p0140", customer: customerB, type: .pallet, state: .storing, location: "D-03-01",
                items: [
                    FulfilmentStoredQuantity(itemCode: "GIFT-L", quantity: 70),
                    FulfilmentStoredQuantity(itemCode: "REED-100", quantity: 1)
                ]
            ),
            FulfilmentPallet(
                reference: "bravo-p0152", customer: customerB, type: .pallet, state: .storing, location: "D-04-06",
                items: [
                    FulfilmentStoredQuantity(itemCode: "CANDLE-AMB", quantity: 1),
                    FulfilmentStoredQuantity(itemCode: "REED-100", quantity: 3),
                    FulfilmentStoredQuantity(itemCode: "GIFT-L", quantity: 70)
                ]
            ),
            FulfilmentPallet(
                reference: "bravo-p0097", customer: customerB, type: .pallet, state: .damaged,
                items: [FulfilmentStoredQuantity(itemCode: "GIFT-L", quantity: 70)]
            ),
            FulfilmentPallet(reference: "acme-p0051", customer: customerA, type: .box, state: .lost),
            FulfilmentPallet(
                reference: "acme-p0118", customer: customerA, type: .pallet, state: .returning, location: "D-01-03",
                items: [FulfilmentStoredQuantity(itemCode: "MUG-BLUE", quantity: 24)]
            ),
            FulfilmentPallet(reference: "acme-p0121", customer: customerA, type: .pallet, state: .returning, location: "D-01-07"),
            FulfilmentPallet(reference: "acme-p0119", customer: customerA, type: .pallet, state: .returning),
            FulfilmentPallet(reference: "acme-p0120", customer: customerA, type: .pallet, state: .returning),
            FulfilmentPallet(reference: "acme-p0122", customer: customerA, type: .pallet, state: .other)
        ]
        return all
    }

    static let deliveries: [FulfilmentDelivery] = [
        FulfilmentDelivery(
            reference: "bravo-007", customer: customerB, state: .received,
            palletReferences: ["bravo-p0118", "bravo-p0119"] + (120...127).map { "bravo-p0\($0)" }
        ),
        FulfilmentDelivery(
            reference: "acme-012", customer: customerA, state: .confirmed,
            palletReferences: (235...240).map { "acme-p0\($0)" }, arrivalNote: "arriving today"
        ),
        FulfilmentDelivery(
            reference: "acme-011", customer: customerA, state: .bookingIn,
            palletReferences: ["acme-p0231", "acme-p0232", "acme-p0233", "acme-p0234"]
        )
    ]

    static let incidents: [FulfilmentIncident] = [
        FulfilmentIncident(palletReference: "bravo-p0097", kind: .damaged, note: "Forklift hit the corner", reportedBy: "Jo K.", date: "06 Nov"),
        FulfilmentIncident(palletReference: "acme-p0051", kind: .lost, note: "Not at D-04-06 at audit", reportedBy: "Sam P.", date: "02 Nov"),
        FulfilmentIncident(palletReference: "acme-p0122", kind: .other, note: "Pallet wet", reportedBy: "Sam P.", date: "08 Nov", returnReference: "acme-r003")
    ]

    static let returns: [FulfilmentReturn] = [
        FulfilmentReturn(
            reference: "acme-r004", customer: customerA, type: .pallets,
            palletLines: [
                FulfilmentReturnPalletLine(palletReference: "acme-p0118", location: "D-01-03"),
                FulfilmentReturnPalletLine(palletReference: "acme-p0121", location: "D-01-07")
            ]
        ),
        FulfilmentReturn(
            reference: "bravo-r011", customer: customerB, type: .storedItems,
            itemLines: [
                FulfilmentReturnItemLine(itemCode: "GIFT-L", palletReference: "bravo-p0140", location: "D-03-01", required: 4, available: 70, picked: 4),
                FulfilmentReturnItemLine(itemCode: "REED-100", palletReference: "bravo-p0140", location: "D-03-01", required: 2, available: 1, picked: 1),
                FulfilmentReturnItemLine(itemCode: "CANDLE-AMB", palletReference: "bravo-p0152", location: "D-04-06", required: 1, available: 1)
            ]
        ),
        FulfilmentReturn(
            reference: "acme-r009", customer: customerA, type: .dropshipping, source: "from Shopify",
            itemLines: [
                FulfilmentReturnItemLine(itemCode: "MUG-BLUE", palletReference: "acme-p0140", location: "D-03-01", required: 2, available: 40, picked: 1)
            ]
        ),
        FulfilmentReturn(
            reference: "acme-r003", customer: customerA, type: .pallets,
            palletLines: [
                FulfilmentReturnPalletLine(palletReference: "acme-p0119", location: "D-01-04", state: .picked),
                FulfilmentReturnPalletLine(palletReference: "acme-p0120", location: "D-01-05", state: .picked),
                FulfilmentReturnPalletLine(palletReference: "acme-p0122", location: "D-01-08", state: .notPicked, incident: .other)
            ]
        )
    ]

    static let audits: [FulfilmentAudit] = [
        FulfilmentAudit(
            reference: "acme-sia014", palletReference: "acme-p0140",
            lines: [
                FulfilmentAuditLine(itemCode: "MUG-BLUE", expected: 40, counted: 40),
                FulfilmentAuditLine(itemCode: "MUG-GREEN", expected: 4, counted: 2),
                FulfilmentAuditLine(itemCode: "TOWEL-SET", expected: 18)
            ],
            currentItemCode: "MUG-GREEN"
        )
    ]

    static let carrierShipment = FulfilmentShipment(shipper: "[Pallet carrier]", tracking: "[number]")
}
