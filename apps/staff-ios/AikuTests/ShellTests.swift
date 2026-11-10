import Foundation
import Testing
@testable import Aiku

struct ShellTests {
    @Test func menuForFullAccessListsEveryGroupAndFulfilmentRows() {
        let groups = ShellMenuCatalog.groups(hasWarehouseAccess: true, hasFulfilmentAccess: true)
        let itemIDs = groups.flatMap(\.items).map(\.id)

        #expect(groups.map(\.id) == ["inventory", "locations", "goodsIn", "goodsOut", "clocking"])
        #expect(itemIDs.contains("inventory.pallets"))
        #expect(itemIDs.contains("goodsOut.fulfilmentReturns"))
    }

    @Test func menuForStaffWithoutWarehouseAccessOnlyShowsClockingAndHr() {
        let groups = ShellMenuCatalog.groups(hasWarehouseAccess: false, hasFulfilmentAccess: false)

        #expect(groups.map(\.id) == ["clocking"])
        #expect(groups.first?.items.map(\.id) == ["clocking.clocking", "clocking.timesheets"])
    }

    @Test func menuWithWarehouseButNoFulfilmentHidesFulfilmentRows() {
        let items = ShellMenuCatalog.groups(hasWarehouseAccess: true, hasFulfilmentAccess: false).flatMap(\.items)

        #expect(items.allSatisfy { $0.requirement != .fulfilment })
        #expect(items.contains { $0.id == "goodsOut.deliveryNotes" })
    }

    @Test func homeModulesForStaffOnlyShowClockingAndHr() {
        let staff = ShellHomeContent.modules(hasWarehouseAccess: false, warehouseLabel: "Main Warehouse · WH1")
        let warehouse = ShellHomeContent.modules(hasWarehouseAccess: true, warehouseLabel: "Main Warehouse · WH1")

        #expect(staff.map(\.id) == ["hr"])
        #expect(staff.first?.subtitle == "This week 29h 05m")
        #expect(warehouse.map(\.id) == ["warehouse", "hr"])
    }

    @Test func greetingFollowsTheTimeOfDay() {
        #expect(ShellHomeContent.greeting(hour: 8, firstName: "Alex") == "Good morning, Alex")
        #expect(ShellHomeContent.greeting(hour: 14, firstName: "Sam") == "Good afternoon, Sam")
        #expect(ShellHomeContent.greeting(hour: 21, firstName: "Alex") == "Good evening, Alex")
    }

    @Test func workFiltersCountItemsPerModule() {
        let items = ShellWork.items

        #expect(ShellWork.count(items, for: .all) == 6)
        #expect(ShellWork.count(items, for: .warehouse) == 5)
        #expect(ShellWork.count(items, for: .tasks) == 1)
        #expect(ShellWork.filtered(items, by: .tasks).map(\.reference) == ["[Task]"])
    }

    @Test func workForStaffWithoutWarehouseAccessDropsWarehouseItems() {
        let visible = ShellWork.visibleItems(ShellWork.items, hasWarehouseAccess: false)

        #expect(visible.allSatisfy { $0.module == .tasks })
        #expect(ShellWork.filters(for: visible) == [.all, .tasks])
    }

    @Test func searchMatchesNamesCaseInsensitivelyAndGroupsByKind() {
        let sections = ShellSearch.sections(for: "lav", scope: .all)

        #expect(sections.map(\.kind) == [.sko, .family])
        #expect(sections.first?.records.map(\.code) == ["SKO-00412", "SKO-00734", "SKO-01190"])
        #expect(sections.first?.heading == "SKOs · 3")
    }

    @Test func searchScopeLimitsTheKinds() {
        #expect(ShellSearch.sections(for: "A-", scope: .locations).map(\.kind) == [.location])
        #expect(ShellSearch.sections(for: "lav", scope: .deliveryNotes).isEmpty)
        #expect(ShellSearch.sections(for: "   ", scope: .all).isEmpty)
    }

    @Test func highlightSplitsAroundEveryMatch() {
        let segments = ShellSearch.highlight("Bath bomb – lavender 180g", query: "lav")

        #expect(segments == [
            ShellHighlightSegment(text: "Bath bomb – ", isMatch: false),
            ShellHighlightSegment(text: "lav", isMatch: true),
            ShellHighlightSegment(text: "ender 180g", isMatch: false)
        ])
    }

    @Test func rememberingMovesTheTermToTheFrontWithoutDuplicates() {
        let recents = ShellSearch.remembering("a-12-03", in: ["AR24817", "A-12-03", "bath bomb"])

        #expect(recents == ["a-12-03", "AR24817", "bath bomb"])
    }

    @Test func scanCodesAreClassifiedByFormat() {
        #expect(ShellScanCatalog.classify("A-03-02") == .location)
        #expect(ShellScanCatalog.classify(" sko-00733 ") == .sko)
        #expect(ShellScanCatalog.classify("AR24811") == .deliveryNote)
        #expect(ShellScanCatalog.classify("SDaw-0093") == .stockDelivery)
        #expect(ShellScanCatalog.classify("acme-p0231") == .pallet)
        #expect(ShellScanCatalog.classify("5060123456789") == .unknown)
    }

    @Test func scanTargetsOpenTheRightScreen() {
        #expect(ShellScanCatalog.target(for: "A-03-02")?.link == .locations(.location(code: "A-03-02")))
        #expect(ShellScanCatalog.target(for: "A-03-02")?.detail == "Area A · 6 SKOs · Operational")
        #expect(ShellScanCatalog.target(for: "SKO-00733")?.link == .inventory(.sko(reference: "SKO-00733")))
        #expect(ShellScanCatalog.target(for: "AR24811")?.link == .goodsOut(.deliveryNote(reference: "AR24811")))
        #expect(ShellScanCatalog.target(for: "SDaw-0093")?.link == .goodsIn(.stockDelivery(reference: "SDaw-0093")))
        #expect(ShellScanCatalog.target(for: "5060123456789") == nil)
    }

    @Test func scanCycleWrapsAroundTheSampleCodes() {
        var cycle = ShellScanCycle(codes: ["A", "B"])

        #expect([cycle.next(), cycle.next(), cycle.next()] == ["A", "B", "A"])
    }

    @MainActor
    @Test func inboxCountsUnreadAndMarksRead() {
        let store = ShellInboxStore()

        #expect(store.unreadCount == 4)
        store.markRead("dn-assigned")
        #expect(store.unreadCount == 3)
        store.markRead("dn-assigned")
        #expect(store.unreadCount == 3)
        store.markAllRead()
        #expect(store.unreadCount == 0)
    }

    @Test func warehouseConfirmTitleSaysKeepOrSwitch() {
        let current = ShellWarehouseChoice(organisation: "AW UK", warehouseName: "Main Warehouse", warehouseCode: "WH1")
        let spain = ShellWarehouseChoice(organisation: "AW Spain", warehouseName: "Main Warehouse", warehouseCode: "ES1")

        #expect(ShellWarehouseDirectory.confirmTitle(selected: current, current: current) == "Keep AW UK · Main Warehouse")
        #expect(ShellWarehouseDirectory.confirmTitle(selected: spain, current: current) == "Switch to AW Spain · Main Warehouse")
    }

    @Test func languageFallsBackToEnglishAndOnlyEnglishIsTranslated() {
        #expect(ShellLanguages.language(id: "xx").id == "en")
        #expect(ShellLanguages.language(id: "sk").nativeName == "Slovenčina")
        #expect(ShellLanguages.isTranslated("en"))
        #expect(!ShellLanguages.isTranslated("es"))
    }
}
