import Foundation
import Testing
@testable import Aiku

struct OfflineLineWorkTests {
    private static func decode<T: Decodable>(_ type: T.Type, _ json: String) throws -> T {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(T.self, from: Data(json.utf8))
    }

    private static let shelf = MobileGoodsInItemLocation(locationOrgStockId: 77204, location: MobileGoodsInPlaceRef(id: 12, code: "67A6"), type: "picking", quantity: 4)

    private static let delivery = MobileStockDelivery(id: 36604, reference: "AEU10106", state: "checked", items: [
        MobileStockDeliveryItem(id: 177817, state: "checked", sko: MobileGoodsInSko(id: 9, code: "ABB-07a"), skoExpected: 30, skoChecked: 30, skoPlaced: 10, skoToPlace: 20, locations: [shelf]),
    ])

    private static let goodsReturn = MobileReturn(id: 635, reference: "R-1", state: "returning", items: [
        MobileReturnItem(id: 6427, sko: MobileGoodsInSko(id: 9, code: "ABB-07a"), expected: 3, remaining: 3, locations: [shelf]),
    ])

    private static let locationJSON = #"""
    {"id":7,"code":"A-03-02","status":"operational","area":null,"allow_stocks":true,"allow_fulfilment":false,"allow_dropshipping":false,
     "is_goods_out":false,"keep_empty_org_stocks":false,"max_weight":null,"max_volume":null,"number_org_stock_slots":2,"number_empty_stock_slots":0,
     "number_pallets":0,"stock_value":0,"audited_at":null,"can":{"edit_stocks":true,"edit_locations":true},
     "org_stocks":[
       {"id":5,"code":"SKO-1","name":"Bath bomb","location_org_stock_id":11,"quantity":"30","type":"picking","value":null,"notes":null,
        "default_wholesale_picking_location":true,"default_dropshipping_picking_location":false,"min_stock":null,"max_stock":null,"replenishment_stock":null,"version":3},
       {"id":6,"code":"SKO-2","name":"Soap","location_org_stock_id":12,"quantity":8,"type":"storing","value":null,"notes":null,
        "default_wholesale_picking_location":false,"default_dropshipping_picking_location":false,"min_stock":null,"max_stock":null,"replenishment_stock":null}
     ]}
    """#

    private static let detailJSON = #"""
    {"id":74495,"code":"RDEO-04","state":"active","quantity_in_locations":100.5,"quantity_in_locations_label":"100.5",
     "locations":[
       {"location_org_stock_id":43286,"type":"picking","quantity":40.5,"quantity_label":"40.5","version":7,"location":{"id":24108,"code":"8v5"}},
       {"location_org_stock_id":157741,"type":"storing","quantity":60,"quantity_label":"60","version":2,"location":{"id":45559,"code":"C-01"}}
     ]}
    """#

    private static func entry(_ localID: Int64, _ operation: OfflineOperation, record: OfflineRecordKey, payload: OfflinePayload, state: QueuedActionState = .waiting) -> QueueEntry {
        OfflineTestData.entry(localID, operation: operation, record: record, payload: payload, state: state)
    }

    @Test("Placing offline sends the delivery, the line, the quantity and where it goes")
    func placementPayload() throws {
        let place = try #require(GoodsInOfflinePayloads.placement(
            .place(itemID: 177817, quantity: 5, target: .link(locationOrgStockID: 77204, code: "67A6"), setAsPickingLocation: false, clientRequestID: "r1"),
            deliveryID: 36604,
            shown: Self.delivery
        ))
        let placeAll = try #require(GoodsInOfflinePayloads.placement(
            .placeAll(itemID: 177817, target: .location(locationID: 1594, code: "27A12"), setAsPickingLocation: true, clientRequestID: "r2"),
            deliveryID: 36604,
            shown: Self.delivery
        ))

        #expect(place["stock_delivery_id"] == .int(36604))
        #expect(place["stock_delivery_item_id"] == .int(177817))
        #expect(place["quantity"] == .number(5))
        #expect(place["location_org_stock_id"] == .int(77204))
        #expect(place["location_id"] == nil)
        #expect(place["set_as_picking_location"] == .bool(false))
        #expect(place["client_request_id"] == .string("r1"))
        #expect(placeAll["quantity"] == .number(20))
        #expect(placeAll["location_id"] == .int(1594))
        #expect(placeAll["location_org_stock_id"] == nil)
        #expect(GoodsInOfflinePayloads.placement(.receive, deliveryID: 36604, shown: Self.delivery) == nil)
        #expect(GoodsInOfflinePayloads.placement(.check(itemID: 177817, skoQuantity: 3), deliveryID: 36604, shown: Self.delivery) == nil)
    }

    @Test("A queued placement shows as placed on the delivery and in its location")
    func placementOverlay() throws {
        let payload = GoodsInOfflinePayloads.place(deliveryID: 36604, itemID: 177817, quantity: 5, target: .link(locationOrgStockID: 77204, code: "67A6"), setAsPickingLocation: false, clientRequestID: "r1")
        let queued = Self.entry(1, .placeStockDeliveryItem, record: .stockDelivery(36604), payload: payload)
        let synced = Self.entry(2, .placeStockDeliveryItem, record: .stockDelivery(36604), payload: payload, state: .applied)

        let item = try #require(GoodsInOfflineOverlay.apply([queued, synced], to: Self.delivery).item(177817))

        #expect(item.skoPlaced == 15)
        #expect(item.skoToPlace == 15)
        #expect(item.skoToPlaceLabel == "15")
        #expect(item.locations.first?.quantity == 9)
        #expect(GoodsInOfflineText.summary(queued, delivery: Self.delivery) == "Place · 5 × ABB-07a in 67A6")
    }

    @Test("Return puts offline take what is left when no quantity is given")
    func returnPutPayloads() throws {
        let back = try #require(GoodsInOfflinePayloads.returnPut(.returned(itemID: 6427, quantity: 1, locationOrgStockID: 77204, clientRequestID: "r1"), returnID: 635, shown: Self.goodsReturn))
        let damaged = try #require(GoodsInOfflinePayloads.returnPut(.damaged(itemID: 6427, quantity: nil, clientRequestID: "r2"), returnID: 635, shown: Self.goodsReturn))

        #expect(back.operation == .returnPutBack)
        #expect(back.payload["return_delivery_note_id"] == .int(635))
        #expect(back.payload["return_delivery_note_item_id"] == .int(6427))
        #expect(back.payload["location_org_stock_id"] == .int(77204))
        #expect(back.payload["client_request_id"] == .string("r1"))
        #expect(damaged.operation == .returnDamaged)
        #expect(damaged.payload["quantity"] == .number(3))
        #expect(damaged.payload["location_org_stock_id"] == nil)
        #expect(GoodsInOfflinePayloads.returnPut(.start, returnID: 635, shown: Self.goodsReturn) == nil)
        #expect(GoodsInOfflinePayloads.returnPut(.undoPut(putID: 9), returnID: 635, shown: Self.goodsReturn) == nil)
    }

    @Test("Queued return puts lower what is left and mark the item handled when nothing is")
    func returnOverlay() throws {
        let back = try #require(GoodsInOfflinePayloads.returnPut(.returned(itemID: 6427, quantity: 1, locationOrgStockID: 77204, clientRequestID: "r1"), returnID: 635, shown: Self.goodsReturn))
        let notReturned = try #require(GoodsInOfflinePayloads.returnPut(.notReturned(itemID: 6427, quantity: 2, clientRequestID: "r2"), returnID: 635, shown: Self.goodsReturn))
        let entries = [
            Self.entry(1, back.operation, record: .customerReturn(635), payload: back.payload),
            Self.entry(2, notReturned.operation, record: .customerReturn(635), payload: notReturned.payload),
        ]

        let item = try #require(GoodsInOfflineOverlay.apply(entries, to: Self.goodsReturn).item(6427))

        #expect(item.returned == 1)
        #expect(item.notReturned == 2)
        #expect(item.remaining == 0)
        #expect(item.isHandled)
        #expect(item.locations.first?.quantity == 5)
        #expect(GoodsInOfflineText.summary(entries[0], goodsReturn: Self.goodsReturn) == "Back to stock · 1 × ABB-07a at 67A6")
        #expect(GoodsInOfflineText.summary(entries[1], goodsReturn: Self.goodsReturn) == "Not returned · 2 × ABB-07a")
    }

    @Test("Location and SKO rows carry the version an offline count needs")
    func versionsAreRead() throws {
        let location = try Self.decode(MobileLocation.self, Self.locationJSON)
        let detail = try Self.decode(MobileOrgStockDetail.self, Self.detailJSON)

        #expect(location.versions == [11: 3])
        #expect(detail.versions == [43286: 7, 157741: 2])
    }

    @Test("An offline count sends every counted row with its version, or is not queued without one")
    func auditPayloads() throws {
        let location = try Self.decode(MobileLocation.self, Self.locationJSON)
        let detail = try Self.decode(MobileOrgStockDetail.self, Self.detailJSON)
        let counted = [MobileLocationAuditCount(locationOrgStockId: 11, quantity: 25, reason: "count_short", note: " ")]
        let skoCounts = [
            MobileOrgStockAuditCount(locationOrgStockId: 43286, quantity: 40.5),
            MobileOrgStockAuditCount(locationOrgStockId: 157741, quantity: 58, reason: "damaged"),
        ]

        let audit = try #require(StockOfflinePayloads.auditLocation(locationID: 7, counts: counted, versions: location.versions, clientRequestID: "r1"))
        let skoAudit = try #require(StockOfflinePayloads.auditOrgStock(orgStockID: 74495, counts: skoCounts, versions: detail.versions, clientRequestID: "r2"))
        let row = try #require(audit["counts"]?.arrayValue.first?.objectValue)

        #expect(audit["location_id"] == .int(7))
        #expect(audit["versions"] == .object(["11": .int(3)]))
        #expect(row["quantity"] == .number(25))
        #expect(row["reason"] == .string("count_short"))
        #expect(row["note"] == .null)
        #expect(skoAudit["org_stock_id"] == .int(74495))
        #expect(skoAudit["versions"] == .object(["43286": .int(7), "157741": .int(2)]))
        #expect(skoAudit["counts"]?.arrayValue.first?.objectValue?["reason"] == .null)
        #expect(StockOfflinePayloads.auditLocation(
            locationID: 7,
            counts: [MobileLocationAuditCount(locationOrgStockId: 12, quantity: 1, reason: "recount", note: nil)],
            versions: location.versions,
            clientRequestID: "r3"
        ) == nil)
    }

    @Test("Queued moves and counts change the stock shown on the location")
    func locationOverlay() throws {
        let location = try Self.decode(MobileLocation.self, Self.locationJSON)
        let move = StockOfflinePayloads.moveToLocation(sourceLinkID: 11, targetLocationID: 99, quantity: 10, clientRequestID: "r1")
        let count = try #require(StockOfflinePayloads.auditLocation(
            locationID: 7,
            counts: [MobileLocationAuditCount(locationOrgStockId: 11, quantity: 25, reason: "count_short", note: nil)],
            versions: location.versions,
            clientRequestID: "r2"
        ))
        let moved = Self.entry(1, .moveStock, record: .location(7), payload: move)
        let counted = Self.entry(2, .auditLocation, record: .location(7), payload: count)

        let afterMove = StockOfflineOverlay.apply([moved], to: location)
        let afterBoth = StockOfflineOverlay.apply([counted, moved], to: location)
        let names = StockOfflineText.Names(sko: { $0 == 11 ? "SKO-1" : nil }, location: { $0 == 99 ? "B-01" : nil })

        #expect(afterMove.orgStocks?.first?.quantity.value == 20)
        #expect(afterMove.orgStocks?.last?.quantity.value == 8)
        #expect(afterBoth.orgStocks?.first?.quantity.value == 15)
        #expect(StockOfflineText.summary(moved, names: names) == "Move · 10 × SKO-1 to B-01")
        #expect(StockOfflineText.summary(counted, names: names) == "Count · SKO-1")
    }

    @Test("A queued move between an SKO's locations moves its stock and keeps the total")
    func skoOverlay() throws {
        let detail = try Self.decode(MobileOrgStockDetail.self, Self.detailJSON)
        let move = StockOfflinePayloads.moveToRows(sourceLinkID: 43286, moves: [StockOfflineMove(linkID: 157741, quantity: 5)], clientRequestID: "r1")
        let count = try #require(StockOfflinePayloads.auditOrgStock(
            orgStockID: 74495,
            counts: [MobileOrgStockAuditCount(locationOrgStockId: 157741, quantity: 50, reason: "lost")],
            versions: detail.versions,
            clientRequestID: "r2"
        ))
        let moved = Self.entry(1, .moveStock, record: .orgStock(74495), payload: move)
        let counted = Self.entry(2, .auditOrgStock, record: .orgStock(74495), payload: count)

        let afterMove = StockOfflineOverlay.apply([moved], to: detail)
        let afterCount = StockOfflineOverlay.apply([counted], to: detail)
        let names = StockOfflineText.Names(linkLocation: { [43286: "8v5", 157741: "C-01"][$0] })

        #expect(move["quantity"] == .number(5))
        #expect(StockOfflineOverlay.moves(moved) == [StockOfflineMove(linkID: 157741, quantity: 5)])
        #expect(afterMove.locations.map(\.quantity.value) == [35.5, 65])
        #expect(afterMove.quantityInLocations?.value == 100.5)
        #expect(afterMove.locations.first?.quantityLabel == "35 1/2")
        #expect(afterCount.quantityInLocations?.value == 90.5)
        #expect(afterCount.quantityInLocationsLabel == "90 1/2")
        #expect(StockOfflineText.summary(moved, names: names) == "Move · 5 from 8v5 to C-01")
        #expect(StockOfflineText.summary(counted, names: names) == "Count · C-01")
    }

    @Test("Only unsent work changes what is shown")
    func syncedWorkIsNotShownTwice() throws {
        let location = try Self.decode(MobileLocation.self, Self.locationJSON)
        let move = StockOfflinePayloads.moveToLocation(sourceLinkID: 11, targetLocationID: 99, quantity: 10, clientRequestID: "r1")

        let shown = StockOfflineOverlay.apply([
            Self.entry(1, .moveStock, record: .location(7), payload: move, state: .applied),
            Self.entry(2, .moveStock, record: .location(7), payload: move, state: .needsYou),
        ], to: location)

        #expect(shown == location)
    }

    @Test("Stock writes online carry a client request id")
    func stockWritesCarryARequestID() throws {
        let client = APIClient(configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!))
        let requests = [
            client.makeRequest(for: .mobileLocationAudit(id: 7, counts: [], clientRequestID: "a1")),
            client.makeRequest(for: .mobileLocationMoveAll(id: 7, targetID: 9, removeAfterMove: false, clientRequestID: "a2")),
            client.makeRequest(for: .mobileLocationMoveSome(id: 7, targetID: 9, orgStocks: [], clientRequestID: "a3")),
            client.makeRequest(for: .mobileOrgStockAudit(warehouseID: 1, id: 74495, counts: [], clientRequestID: "a4")),
            client.makeRequest(for: .mobileLocationOrgStockMove(linkID: 11, targets: [], clientRequestID: "a5")),
        ]

        let sent = try requests.map { request in
            try #require(try JSONSerialization.jsonObject(with: try #require(request.httpBody)) as? [String: Any])["client_request_id"] as? String
        }
        let withoutID = try #require(try JSONSerialization.jsonObject(with: try #require(client.makeRequest(for: .mobileLocationAudit(id: 7, counts: [])).httpBody)) as? [String: Any])

        #expect(sent == ["a1", "a2", "a3", "a4", "a5"])
        #expect(withoutID["client_request_id"] == nil)
    }
}
