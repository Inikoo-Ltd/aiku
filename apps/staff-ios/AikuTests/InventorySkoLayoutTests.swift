import Foundation
import Testing
@testable import Aiku

private func decodeDetail(_ json: String) throws -> MobileOrgStockDetail {
    let decoder = JSONDecoder()
    decoder.keyDecodingStrategy = .convertFromSnakeCase
    return try decoder.decode(MobileOrgStockDetail.self, from: Data(json.utf8))
}

struct InventorySkoLayoutTests {
    private static let detailJSON = #"""
    {"id":81,"code":"JBB-01","name":"Jasmine bath bomb","state":"active","packed_in":12,"quantity_in_locations":2.915667,"quantity_in_locations_label":"2 11/12","locations":[{"location_org_stock_id":11,"type":"picking","quantity":2.915667,"quantity_label":"2 11/12","min_stock":12,"max_stock":48,"is_default_wholesale":true,"is_default_dropshipping":true,"audited_at":"2025-10-14","location":{"id":21,"code":"WAREHOUSE-A-AISLE-12-BAY-04-LEVEL-3"}},{"location_org_stock_id":12,"type":"storing","quantity":0,"quantity_label":"0","replenishment_stock":24,"is_default_dropshipping":true,"audited_at":null,"location":{"id":22,"code":"C-01"}},{"location_org_stock_id":13,"type":"storing","quantity":-0.5,"quantity_label":"-1/2","min_stock":6,"location":{"id":23,"code":"D-02"}}]}
    """#

    private func liveSko() throws -> InventorySko {
        InventoryLive.sko(try decodeDetail(Self.detailJSON), canEditStocks: true)
    }

    private var rose: InventorySko {
        InventorySampleData.skos.first { $0.reference == "SKO-00733" }!
    }

    @Test func unitsAreTheSkoQuantityTimesTheUnitsPerSko() {
        #expect(InventorySkoUnits.total(2.915667, packedIn: 12) == 35)
        #expect(InventorySkoUnits.total(3, packedIn: 6) == 18)
        #expect(InventorySkoUnits.total(0.5, packedIn: 4) == 2)
        #expect(InventorySkoUnits.total(1.25, packedIn: 3) == 3.8)
        #expect(InventorySkoUnits.total(0, packedIn: 12) == 0)
    }

    @Test func singleUnitSkosAndNegativeStockHaveNoUnitsLine() {
        #expect(InventorySkoUnits.total(5, packedIn: 1) == nil)
        #expect(InventorySkoUnits.total(5, packedIn: nil) == nil)
        #expect(InventorySkoUnits.total(5, packedIn: 0.5) == nil)
        #expect(InventorySkoUnits.total(-1.5, packedIn: 12) == nil)
    }

    @Test func unitsTextSaysUnitsInTotal() {
        #expect(InventorySkoUnits.text(35) == "35 units in total")
        #expect(InventorySkoUnits.text(1) == "1 unit in total")
        #expect(InventorySkoUnits.text(3.8) == "3.8 units in total")
        #expect(InventorySkoUnits.text(1200) == "1,200 units in total")
        #expect(InventorySkoUnits.text(0) == "0 units in total")
    }

    @Test func liveSkoShowsUnitsInTotalOnlyWhenPackedInMoreThanOne() throws {
        let sko = try liveSko()
        #expect(sko.packedIn == 12)
        #expect(sko.stockQuantity == 2.915667)
        #expect(sko.unitsInTotalText() == "35 units in total")
        #expect(sko.location("WAREHOUSE-A-AISLE-12-BAY-04-LEVEL-3")?.unitsInTotalText(packedIn: sko.packedIn) == "35 units in total")
        #expect(sko.location("C-01")?.unitsInTotalText(packedIn: sko.packedIn) == "0 units in total")
        #expect(sko.location("D-02")?.unitsInTotalText(packedIn: sko.packedIn) == nil)
        #expect(sko.location("C-01")?.unitsInTotalText(packedIn: 1) == nil)
    }

    @Test func sampleSkoUsesItsUnitsPerSko() {
        #expect(rose.packedIn == 6)
        #expect(rose.unitsInTotalText() == "216 units in total")
        let single = InventorySampleData.skos.first { $0.unitsPerSko == 1 }
        #expect(single?.unitsInTotalText() == nil)
    }

    @Test func negativeLocationsUseTheExactQuantity() throws {
        let sko = try liveSko()
        let negative = try #require(sko.location("D-02"))
        #expect(negative.quantity == 0)
        #expect(negative.isNegative)
        #expect(sko.location("C-01")?.isNegative == false)
    }

    @Test func auditedTextShowsTheDateWithTheYear() throws {
        let sko = try liveSko()
        let picking = try #require(sko.location("WAREHOUSE-A-AISLE-12-BAY-04-LEVEL-3"))
        #expect(picking.lastAudited == "14 Oct 2025")
        #expect(picking.auditedText() == "Last audited 14 Oct 2025")
        #expect(sko.location("C-01")?.auditedText() == "Never audited")
        #expect(sko.location("D-02")?.auditedText() == "Never audited")
        #expect(rose.location("A-03-02")?.auditedText() == "Last audited 28 Oct")
        #expect(rose.location("B-02-01")?.auditedText() == "Never audited")
    }

    @Test func defaultsFooterNamesTheChannels() throws {
        let sko = try liveSko()
        #expect(sko.location("WAREHOUSE-A-AISLE-12-BAY-04-LEVEL-3")?.defaultsText() == "Default for Wholesale · Dropshipping")
        #expect(sko.location("C-01")?.defaultsText() == "Default for Dropshipping")
        #expect(sko.location("D-02")?.defaultsText() == nil)
        #expect(rose.location("A-03-02")?.defaultsText() == "Default for Wholesale")
        #expect(rose.location("B-02-01")?.defaultsText() == "Default for Dropshipping")
        #expect(rose.location("C-04-02")?.defaultsText() == nil)
    }

    @Test func minMaxTextShowsOnlyTheLevelsThatAreSet() throws {
        #expect(InventorySkoLocation.levels(minimum: "12", maximum: "48", replenish: "24") == "min 12 · max 48")
        #expect(InventorySkoLocation.levels(minimum: "12", maximum: nil, replenish: nil) == "min 12")
        #expect(InventorySkoLocation.levels(minimum: nil, maximum: "48", replenish: nil) == "max 48")
        #expect(InventorySkoLocation.levels(minimum: nil, maximum: nil, replenish: "24") == "replenish 24")
        #expect(InventorySkoLocation.levels(minimum: nil, maximum: nil, replenish: nil) == nil)
        let sko = try liveSko()
        #expect(sko.location("WAREHOUSE-A-AISLE-12-BAY-04-LEVEL-3")?.levelsText == "min 12 · max 48")
        #expect(sko.location("D-02")?.levelsText == "min 6")
        #expect(rose.location("A-03-02")?.levelsText == "min 12 · max 48")
        #expect(rose.location("C-04-02")?.levelsText == "replenish 24")
    }

    @Test func footerPutsTheDefaultsBeforeTheLevels() throws {
        let sko = try liveSko()
        #expect(sko.location("WAREHOUSE-A-AISLE-12-BAY-04-LEVEL-3")?.footerLines() == ["Default for Wholesale · Dropshipping", "min 12 · max 48"])
        #expect(sko.location("C-01")?.footerLines() == ["Default for Dropshipping", "replenish 24"])
        #expect(sko.location("D-02")?.footerLines() == ["min 6"])
        let bare = InventorySkoLocation(code: "E-01", type: .storing, quantity: 3)
        #expect(bare.footerLines().isEmpty)
    }

    @Test func textsGoThroughTheTranslation() throws {
        let french = [
            "Default for %@": "Par défaut : %@",
            "Wholesale": "Gros",
            "Last audited %@": "Dernier audit %@",
            "Never audited": "Jamais audité",
            "%@ units in total": "%@ unités au total",
        ]
        let translate: (String) -> String = { french[$0] ?? $0 }
        let sko = try liveSko()
        let picking = try #require(sko.location("WAREHOUSE-A-AISLE-12-BAY-04-LEVEL-3"))
        #expect(picking.defaultsText(translate: translate) == "Par défaut : Gros · Dropshipping")
        #expect(picking.auditedText(translate: translate) == "Dernier audit 14 Oct 2025")
        #expect(sko.location("C-01")?.auditedText(translate: translate) == "Jamais audité")
        #expect(sko.unitsInTotalText(translate: translate) == "35 unités au total")
    }
}
