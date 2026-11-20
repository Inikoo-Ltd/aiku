import Foundation
import Testing
@testable import Aiku

private func decode<T: Decodable>(_ type: T.Type, _ json: String) throws -> T {
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    return try decoder.decode(T.self, from: Data(json.utf8))
}

private actor Calls {
    var audits: [[MobileOrgStockAuditCount]] = []
    var moves: [(Int, [MobileOrgStockMoveTarget])] = []
    var consumes: [(Int, MobileOrgStockConsumeBody)] = []
    var added: [MobileOrgStockAddLocationBody] = []
    var removed: [[Int]] = []
    var queries: [MobileOrgStocksQuery] = []
    var flows: [String?] = []

    func audit(_ counts: [MobileOrgStockAuditCount]) { audits.append(counts) }
    func move(_ link: Int, _ targets: [MobileOrgStockMoveTarget]) { moves.append((link, targets)) }
    func consume(_ link: Int, _ body: MobileOrgStockConsumeBody) { consumes.append((link, body)) }
    func add(_ body: MobileOrgStockAddLocationBody) { added.append(body) }
    func remove(_ ids: [Int]) { removed.append(ids) }
    func query(_ query: MobileOrgStocksQuery) { queries.append(query) }
    func flow(_ flow: String?) { flows.append(flow) }
}

@MainActor
struct InventoryLiveTests {
    nonisolated private static let detailJSON = #"""
    {"id":74495,"code":"RDEO-04","name":"Patchouli Reed Diffuser","state":"active","state_label":"Active","family":{"id":5048,"code":"RDEO","name":"Essential Oil Reed Diffusers"},"barcode":"505579657613","unit_barcode":"5055796576135","packed_in":1,"is_on_demand":true,"note_to_pickers":" ","note_to_packers":"wrap twice","currency_code":"GBP","stock_value":103.6,"quantity_in_locations":100.5,"quantity_in_locations_label":"100.5","quantity_reserved":3,"quantity_reserved_label":"3","quantity_to_be_picked":0,"quantity_to_be_picked_label":"0","cover":{"days":30,"out_at":"2026-12-04T00:00:00+00:00","daily_usage":1.32},"locations":[{"location_org_stock_id":43286,"type":"picking","quantity":40.5,"quantity_label":"40.5","min_stock":12,"max_stock":48,"replenishment_stock":null,"is_default_wholesale":true,"is_default_dropshipping":true,"audited_at":"2024-05-28T12:49:28+00:00","location":{"id":24108,"code":"8v5","status":"operational","allow_stocks":true,"area":null}},{"location_org_stock_id":157741,"type":"storing","quantity":60,"quantity_label":"60","replenishment_stock":24,"audited_at":null,"location":{"id":45559,"code":"C-01","status":"operational","allow_stocks":true}}],"can":{"edit_stocks":false,"edit_locations":true}}
    """#

    nonisolated private static func detail() throws -> MobileOrgStockDetail {
        try decode(MobileOrgStockDetail.self, detailJSON)
    }

    nonisolated private static func written(_ message: String?) throws -> MobileOrgStockWriteResult {
        MobileOrgStockWriteResult(data: try detail(), result: .init(status: "done", message: message))
    }

    @Test func dashboardShowsTheWebFigures() async throws {
        let remote = try decode(MobileInventoryDashboard.self, #"{"currency_code":"GBP","stock_value":412345.6,"number_locations":1736,"number_empty_locations":546,"utilisation":0.6854838709677419,"number_current_org_stocks":6979,"number_abnormalities":2,"low_stock_audits":443,"replenishments_wholesale":4,"replenishments_dropshipping":1,"below_minimum_stock":0,"number_with_minimum_stock":1,"negative_stocks":144,"wrong_weights":1,"without_products":1652}"#)
        let feed = InventoryDashboardFeed(service: InventoryService(dashboard: { _ in remote }))

        await feed.load(warehouseID: 4)

        let summary = try #require(feed.summary)
        #expect(feed.loadedWarehouseID == 4)
        #expect(summary.currentSkos == 6979)
        #expect(summary.replenishmentsLabel == "Replenishments · dropshipping 1")
        #expect(summary.belowMinimumLabel == "Below minimum stock · of 1 SKO with a minimum")
        #expect(summary.stockValueLabel == "£412,346")
        #expect(summary.utilisationLine == "Location utilisation 69% · 1,190 of 1,736 locations used")
    }

    @Test func dashboardOfflineSaysItNeedsInternet() async {
        let feed = InventoryDashboardFeed(service: InventoryService(dashboard: { _ in throw APIError.offline }))

        await feed.load(warehouseID: 4)

        #expect(feed.summary == nil)
        #expect(feed.loadError == "You need an internet connection to do this. Check the connection and try again.")
    }

    @Test func skoListSendsBucketFamilySortAndSearch() throws {
        var filter = InventoryLiveSkoFilter()
        filter.familyID = 27
        filter.sort = .stockValue
        let query = filter.query(scope: .abnormalities, search: " rose ")
        let items = Set(query.queryItems)

        #expect(items.contains(URLQueryItem(name: "bucket", value: "abnormality")))
        #expect(items.contains(URLQueryItem(name: "family_id", value: "27")))
        #expect(items.contains(URLQueryItem(name: "sort", value: "-stock_value")))
        #expect(items.contains(URLQueryItem(name: "q", value: "rose")))
        #expect(InventoryLiveSkoFilter().query(scope: .current, search: "").queryItems.contains { $0.name == "sort" } == false)
        #expect(filter.summary == "1 filter · sorted by stock value")
    }

    @Test func skoListFeedMapsRowsAndCounts() async throws {
        let page = try decode(MobileOrgStocksPage.self, #"{"data":[{"id":1,"code":"EO-01","name":"Lavender","state":"active","state_label":"Active","quantity_available":12.5,"quantity_available_label":"12.5","number_locations":2},{"id":2,"code":"EO-02","name":"Rose","state":"discontinuing","state_label":"Discontinuing","quantity_available":-3,"quantity_available_label":"-3","number_locations":1}],"meta":{"current_page":1,"last_page":3,"per_page":50,"total":120},"counts":{"current":6979,"discontinued":12,"abnormality":2,"all":7000},"currency_code":"GBP"}"#)
        let calls = Calls()
        let feed = InventorySkoListFeed(service: InventoryService(orgStocks: { _, query in
            await calls.query(query)
            return page
        }))
        let request = InventorySkoListRequest(warehouseID: 4, scope: .current, search: "eo", filter: InventoryLiveSkoFilter())

        await feed.reload(request)

        #expect(feed.items.map(\.reference) == ["EO-01", "EO-02"])
        #expect(feed.items[0].subtitle == "Lavender · 2 locations")
        #expect(feed.items[0].chip == nil)
        #expect(feed.items[0].quantityText == "12.5")
        #expect(feed.items[1].chip == Chip("Discontinuing", .neutral))
        #expect(feed.items[1].subtitle == "Rose · 1 location")
        #expect(feed.counts.map { InventorySkoScope.discontinued.count(in: $0) } == 12)
        #expect(feed.hasMorePages)
        #expect(await calls.queries.first?.search == "eo")
    }

    @Test func negativeStockShowsOneRowPerSkoLocation() async throws {
        let page = try decode(MobileOrgStocksPage.self, #"{"data":[{"id":7,"code":"EO-07","name":"Mint","state":"active","state_label":"Active","quantity_available":-5,"quantity_available_label":"-5","number_locations":2,"location_org_stock_id":70,"location":{"id":1,"code":"A-01"},"location_quantity":-3.5},{"id":7,"code":"EO-07","name":"Mint","state":"active","state_label":"Active","quantity_available":-5,"quantity_available_label":"-5","number_locations":2,"location_org_stock_id":71,"location":{"id":2,"code":"B-02"},"location_quantity":"2"}],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":2}}"#)
        let feed = InventorySkoListFeed(service: InventoryService(orgStocks: { _, _ in page }))

        await feed.reload(InventorySkoListRequest(warehouseID: 4, scope: .negative, search: "", filter: InventoryLiveSkoFilter()))

        #expect(feed.items.count == 2)
        #expect(Set(feed.items.map(\.id)).count == 2)
        #expect(feed.items.map(\.skoID) == [7, 7])
        #expect(feed.items[0].subtitle == "Mint")
        #expect(feed.items[0].quantityText == "-5")
        #expect(feed.items[0].location == InventorySkoListLocation(locationOrgStockID: 70, code: "A-01", quantityText: "−3.5", isNegative: true))
        #expect(feed.items[1].location == InventorySkoListLocation(locationOrgStockID: 71, code: "B-02", quantityText: "2", isNegative: false))
    }

    @Test func twoLocationsOfOneSkoStayTwoRowsAcrossPages() async throws {
        let row = #"{"id":7,"code":"EO-07","name":"Mint","state":"active","location_org_stock_id":%d,"location":{"id":%d,"code":"L-%d"},"location_quantity":-1}"#
        func page(_ number: Int, _ links: [Int]) throws -> MobileOrgStocksPage {
            let rows = links.map { String(format: row, $0, $0, $0) }.joined(separator: ",")
            return try decode(MobileOrgStocksPage.self, #"{"data":[\#(rows)],"meta":{"current_page":\#(number),"last_page":2,"per_page":1,"total":2}}"#)
        }
        let first = try page(1, [70])
        let second = try page(2, [70, 71])
        let feed = InventorySkoListFeed(service: InventoryService(orgStocks: { _, query in
            query.page == 1 ? first : second
        }))

        await feed.reload(InventorySkoListRequest(warehouseID: 4, scope: .negative, search: "", filter: InventoryLiveSkoFilter()))
        await feed.loadNextPage()

        #expect(feed.items.map { $0.location?.code } == ["L-70", "L-71"])
    }

    @Test func locationQuantitiesReadLikeAndroidWithoutGroupingAndTwoDecimals() {
        func line(_ quantity: Double) -> String? {
            InventoryLive.listLocation(MobileOrgStockRow(id: 1, code: "X", state: "active", locationOrgStockId: 9, location: .init(id: 1, code: "A"), locationQuantity: OptionalFlexibleDouble(quantity)))?.quantityText
        }

        #expect(line(-35907) == "−35907")
        #expect(line(-5536.714) == "−5536.71")
        #expect(line(-13.52) == "−13.52")
        #expect(line(1684) == "1684")
    }

    @Test func rowsWithoutALocationKeepTheLocationCount() throws {
        let item = InventoryLive.listItem(MobileOrgStockRow(id: 3, code: "X-1", name: "Odd", state: "active", numberLocations: 2), scope: .current)

        #expect(item.location == nil)
        #expect(item.subtitle == "Odd · 2 locations")
        #expect(item.id == InventorySkoListKey(skoID: 3, locationOrgStockID: nil))
    }

    @Test func abnormalitiesShowLastSeenWithoutStock() throws {
        let row = MobileOrgStockRow(id: 3, code: "X-1", name: "Odd", state: "abnormality", stateLabel: "Abnormality", lastSeenAt: "2026-11-02T10:00:00+00:00")
        let item = InventoryLive.listItem(row, scope: .abnormalities)

        #expect(item.chip == Chip("Abnormality", .danger))
        #expect(item.quantityText == nil)
        #expect(item.subtitle == "Odd · Last seen 02 Nov")
    }

    @Test func skoDetailMapsLocationsLabelsAndRights() throws {
        let sko = InventoryLive.sko(try Self.detail(), canEditStocks: true)

        #expect(sko.reference == "RDEO-04")
        #expect(sko.live?.id == 74495)
        #expect(sko.live?.stockLabel == "100.5")
        #expect(sko.live?.canEditStocks == false)
        #expect(sko.live?.familyTitle == "RDEO · Essential Oil Reed Diffusers")
        #expect(sko.pickerNote == nil)
        #expect(sko.packerNote == "wrap twice")
        #expect(sko.coverLabel == "30 days")
        #expect(sko.listChip == Chip("Active", .success))
        let picking = try #require(sko.location("8v5"))
        #expect(picking.quantity == 40)
        #expect(picking.quantityText == "40.5")
        #expect(picking.chip == Chip("Picking · Wholesale · Dropshipping", .info))
        #expect(picking.settingsLine == "min 12 · max 48 · audited 28 May")
        #expect(sko.location("C-01")?.settingsLine == "replenish 24 · never audited")
        #expect(sko.location("C-01")?.chip == Chip("Storing", .neutral))
    }

    @Test func skoFeedOpensAReferenceThroughTheList() async throws {
        let detail = try Self.detail()
        let page = MobileOrgStocksPage(data: [
            MobileOrgStockRow(id: 1, code: "RDEO-040", state: "active"),
            MobileOrgStockRow(id: 74495, code: "rdeo-04", state: "active"),
        ], meta: .init(currentPage: 1, lastPage: 1, perPage: 20, total: 2))
        let calls = Calls()
        let feed = InventorySkoFeed(warehouseID: 4, target: .reference("RDEO-04"), canEditStocks: true, service: InventoryService(
            orgStocks: { _, query in
                await calls.query(query)
                return page
            },
            orgStock: { _, id in
                #expect(id == 74495)
                return detail
            }
        ))

        await feed.load()

        #expect(feed.orgStockID == 74495)
        #expect(feed.sko?.reference == "RDEO-04")
        #expect(await calls.queries.first?.bucket == "all")
    }

    @Test func unknownReferenceIsNotFound() async {
        let feed = InventorySkoFeed(warehouseID: 4, target: .reference("NOPE"), canEditStocks: true, service: InventoryService(
            orgStocks: { _, _ in MobileOrgStocksPage(data: [], meta: .init(currentPage: 1, lastPage: 1, perPage: 20, total: 0)) }
        ))

        await feed.load()

        #expect(feed.isNotFound)
        #expect(feed.sko == nil)
    }

    @Test func auditSendsExactStockForUnchangedCountsAndReasonsForChanges() async throws {
        let detail = try Self.detail()
        let result = try Self.written("Stock count saved.")
        let calls = Calls()
        let feed = InventorySkoFeed(warehouseID: 4, target: .id(74495), canEditStocks: true, service: InventoryService(
            orgStock: { _, _ in detail },
            audit: { _, _, counts in
                await calls.audit(counts)
                return result
            }
        ))
        await feed.load()

        let message = try await feed.audit(counted: ["8v5": 40, "C-01": 58], reasons: ["C-01": "damaged"])

        #expect(message == "Stock count saved.")
        #expect(await calls.audits.first == [
            MobileOrgStockAuditCount(locationOrgStockId: 43286, quantity: 40.5),
            MobileOrgStockAuditCount(locationOrgStockId: 157741, quantity: 58, reason: "damaged"),
        ])
    }

    @Test func stockEditsUseTheLocationLinks() async throws {
        let detail = try Self.detail()
        let calls = Calls()
        let feed = InventorySkoFeed(warehouseID: 4, target: .id(74495), canEditStocks: true, service: InventoryService(
            orgStock: { _, _ in detail },
            move: { link, targets in
                await calls.move(link, targets)
                return try Self.written("Moved 5 from 8v5.")
            },
            consume: { link, body in
                await calls.consume(link, body)
                return try Self.written(nil)
            },
            addLocation: { _, _, body in
                await calls.add(body)
                return try Self.written("Added to A-01 as the picking location; 8v5 is now storing.")
            },
            removeLocations: { _, _, ids in
                await calls.remove(ids)
                return try Self.written(nil)
            }
        ))
        await feed.load()

        #expect(try await feed.move(from: "8v5", to: "C-01", quantity: 5) == "Moved 5 from 8v5.")
        #expect(try await feed.consume(location: "C-01", quantity: 2, note: "  ") == "Consumption recorded")
        let option = InventoryLocationOption(code: "A-01", detail: "empty", locationID: 900)
        #expect(try await feed.addLocation(option, type: .picking) == "Added to A-01 as the picking location; 8v5 is now storing.")
        #expect(try await feed.removeLocations(["C-01", "8v5"]) == "2 locations removed")

        #expect(await calls.moves.first?.0 == 43286)
        #expect(await calls.moves.first?.1 == [MobileOrgStockMoveTarget(locationOrgStockId: 157741, quantity: 5)])
        #expect(await calls.consumes.first?.0 == 157741)
        #expect(await calls.consumes.first?.1 == MobileOrgStockConsumeBody(quantity: 2, note: nil))
        #expect(await calls.added == [MobileOrgStockAddLocationBody(locationId: 900, type: "picking")])
        #expect(await calls.removed == [[43286, 157741]])
    }

    @Test func refusedEditShowsTheServerMessage() async throws {
        let detail = try Self.detail()
        let feed = InventorySkoFeed(warehouseID: 4, target: .id(74495), canEditStocks: true, service: InventoryService(
            orgStock: { _, _ in detail },
            addLocation: { _, _, _ in throw APIError.validation(message: "This location is not operational.", code: nil) }
        ))
        await feed.load()

        await #expect(throws: APIError.self) {
            try await feed.addLocation(InventoryLocationOption(code: "Z", detail: "", locationID: 1), type: .storing)
        }
        #expect(InventoryLive.message(for: APIError.validation(message: "This location is not operational.", code: nil)) == "This location is not operational.")
        await #expect(throws: InventoryStockError.locationAlreadyAssigned("C-01")) {
            try await feed.addLocation(InventoryLocationOption(code: "C-01", detail: "", locationID: 45559), type: .storing)
        }
    }

    @Test func reasonsKeepTheirKeysAndTheWebOrder() throws {
        let data = Data(#"{"data":{"increase":{"supplier_over":"Supplier Over","count_gain":"Count Gain (Stock Found)","recount":"Recount Correction"},"decrease":{"lost":"Lost","count_short":"Count Shortage"},"transfer":{"transfer":"Transfer"}}}"#.utf8)
        let reasons = try MobileStockMovementReasons.decode(from: data)

        #expect(reasons.increase.map(\.value) == ["count_gain", "recount", "supplier_over"])
        #expect(reasons.decrease.map(\.value) == ["count_short", "lost"])
        #expect(InventoryLive.reasonChoices(reasons, change: -2).map(\.title) == ["Count Shortage", "Lost"])
        #expect(InventoryLive.reasonChoices(reasons, change: 0).isEmpty)
    }

    @Test func movementsFilterByFlowAndReadAsRows() async throws {
        let page = try decode(MobileOrgStockMovementsPage.self, #"{"data":[{"id":9,"date":"2026-11-16T10:22:00+00:00","type":"audit","type_label":"Audit","flow":"audit","quantity":-2,"quantity_label":"-2","running_quantity_org_stock":98,"location":{"id":1,"code":"8v5"},"reason":"damaged","reason_label":"Damaged","note":null,"parent_reference":null,"user":{"id":3,"name":"Jo K."}}],"meta":{"current_page":1,"last_page":1,"per_page":50,"total":1}}"#)
        let calls = Calls()
        let feed = InventoryMovementsFeed(warehouseID: 4, target: .id(74495), skoCode: "RDEO-04", service: InventoryService(movements: { _, _, flow, _ in
            await calls.flow(flow)
            return page
        }))

        await feed.reload(.audit)

        #expect(await calls.flows == ["audit"])
        let row = try #require(feed.items.first)
        #expect(row.title == "8v5")
        #expect(row.chip == Chip("Audit −2", .warn))
        #expect(row.subtitle.hasPrefix("Damaged · Jo K. · 16 Nov"))
        #expect(row.subtitle.hasSuffix("running 98"))
        #expect(InventoryHistoryFilter.all.flow == nil)
    }

    @Test func locationSearchSkipsLocationsTheSkoIsAlreadyIn() async throws {
        let detail = try Self.detail()
        let locations = try decode([MobileLocation].self, #"[{"id":45559,"code":"C-01","status":"operational","area":null,"allow_stocks":true,"allow_fulfilment":false,"allow_dropshipping":false,"is_goods_out":false,"keep_empty_org_stocks":false,"max_weight":null,"max_volume":null,"number_org_stock_slots":1,"number_empty_stock_slots":0,"number_pallets":0,"stock_value":0,"audited_at":null},{"id":7,"code":"C-02","status":"operational","area":{"id":1,"code":"C","name":"Bulk"},"allow_stocks":true,"allow_fulfilment":false,"allow_dropshipping":false,"is_goods_out":false,"keep_empty_org_stocks":false,"max_weight":null,"max_volume":null,"number_org_stock_slots":0,"number_empty_stock_slots":0,"number_pallets":0,"stock_value":0,"audited_at":null},{"id":8,"code":"C-03","status":"operational","area":null,"allow_stocks":false,"allow_fulfilment":false,"allow_dropshipping":false,"is_goods_out":false,"keep_empty_org_stocks":false,"max_weight":null,"max_volume":null,"number_org_stock_slots":0,"number_empty_stock_slots":0,"number_pallets":0,"stock_value":0,"audited_at":null}]"#)
        let feed = InventorySkoFeed(warehouseID: 4, target: .id(74495), canEditStocks: true, service: InventoryService(
            orgStock: { _, _ in detail },
            findLocations: { _, _ in locations }
        ))
        await feed.load()

        let options = try await feed.locationOptions(search: "C-0")

        #expect(options == [InventoryLocationOption(code: "C-02", detail: "Area Bulk · empty", locationID: 7)])
        #expect(try await feed.locationOptions(search: " ").isEmpty)
    }
}
