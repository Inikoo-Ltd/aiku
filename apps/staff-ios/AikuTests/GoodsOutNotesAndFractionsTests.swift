import Foundation
import Testing
@testable import Aiku

struct GoodsOutNotesAndFractionsTests {
    private static func note() throws -> GoodsOutDeliveryNoteDTO {
        let json = """
        {"id": 501, "reference": "AR24811", "state": "handling", "allow_actions": true,
         "lines": [
           {"id": 11, "quantity_required": 3, "quantity_picked": 1, "quantity_to_pick": 2,
            "org_stock": {"code": "SKO-1", "packed_in": 2},
            "locations": [{"location_org_stock_id": 91, "code": "12B9", "stock": 10, "stock_label": "10", "quantity_picked": 1, "version": 7}]}
         ]}
        """
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(GoodsOutDeliveryNoteDTO.self, from: Data(json.utf8))
    }

    @Test("Note cards take Aiku's fixed colour for each kind, as on the web")
    func noteKindColours() {
        #expect(NoteKind.shippingLabel.accent.hex == "#93C5FD")
        #expect(NoteKind.customer.accent.hex == "#599FF0")
        #expect(NoteKind.warehouse.accent.hex == "#FCD34D")
        #expect(NoteKind.fulfilmentCustomer.accent.hex == "#009EFF")
        #expect(NoteKind.fulfilmentPublic.accent.hex == "#E6007E")
        #expect(NoteKind.fulfilmentPrivate.accent.hex == "#5000E6")
        #expect(NoteKind.shippingLabel.header.hex == "#93C5FD")
        #expect(NoteKind.fulfilmentCustomer.header.hex == "#99D8FF")
        #expect(NoteKind.fulfilmentPublic.header.hex == "#F599CB")
        #expect(NoteKind.fulfilmentPrivate.header.hex == "#B999F5")
    }

    @Test("Note card titles and bodies stay readable in every theme, light and dark")
    func noteCardsAreReadable() {
        for kind in NoteKind.allCases {
            #expect(kind.header.contrast(with: NoteKind.headerInk) >= Palette.readableContrast)
            for theme in ThemeID.allCases {
                for dark in [false, true] {
                    let palette = Palette(theme: theme, dark: dark)
                    let body = kind.bodyTint.over(palette.surface)
                    #expect(body.contrast(with: palette.text2) >= Palette.readableContrast)
                }
            }
        }
    }

    @MainActor
    @Test("Queued work shows the web's fraction of an SKO")
    func queuedWorkFraction() throws {
        let line = try #require(try Self.note().lines?.first)
        let waiting = OfflineTestData.entry(1, operation: .setWaitingWarehouse, payload: ["delivery_note_item_id": .int(11), "quantity": .number(0.5)])
        let picked = OfflineTestData.entry(2, payload: ["delivery_note_item_id": .int(11), "location_org_stock_id": .int(91), "quantity": .number(3)])

        let waitingText = GoodsOutOfflineText.summary(waiting, line: line)
        #expect(waitingText.hasPrefix("SKO-1 · "))
        #expect(waitingText.hasSuffix(FractionGlyphs.apply("1/2")))
        #expect(GoodsOutOfflineText.summary(picked, line: line).hasSuffix(" 3"))
    }

    @Test("A queued pick shows the location's stock left and the picked quantity as fractions")
    func overlayLabels() throws {
        let pick = OfflineTestData.entry(1, payload: ["delivery_note_item_id": .int(11), "location_org_stock_id": .int(91), "quantity": .number(0.5)])

        let line = try #require(GoodsOutOfflineOverlay.apply([pick], to: try Self.note(), copySeenAt: nil).lines?.first)

        #expect(line.locations?.first?.stock == 9.5)
        #expect(line.locations?.first?.stockLabel == "9 1/2")
        #expect(line.quantityPickedLabel == "1 1/2")
        #expect(line.quantityToPickLabel == "1 1/2")
    }

    @MainActor
    @Test("A picking line shows its location stock as the web's fraction")
    func lineLocationStock() {
        let stockLabel: String = FractionGlyphs.apply("7 1/2")
        var line = GoodsOutLine(sko: "SKO-1", name: "Incense", location: "12B9", required: 3, locationStock: 7)
        line.stockLocations = [GoodsOutStockLocation(code: "12B9", kind: "Picking", stock: 7, stockLabel: stockLabel, exactStock: 7.5)]

        #expect(line.detailText(showsLocationStock: true).contains(stockLabel))
    }

    @Test("A location holding only part of an SKO is not empty")
    func fractionalStockIsNotEmpty() {
        #expect(GoodsOutStockLocation(code: "12B9", kind: "Picking", stock: 0, stockLabel: "1/2", exactStock: 0.5).isEmpty == false)
        #expect(GoodsOutStockLocation(code: "12B9", kind: "Picking", stock: 0, exactStock: 0).isEmpty)
    }
}
