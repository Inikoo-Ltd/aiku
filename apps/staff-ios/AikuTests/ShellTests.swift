import Foundation
import Testing
@testable import Aiku

struct ShellTests {
    @Test func menuForFullAccessListsEveryGroupAndFulfilmentRows() {
        let groups = ShellMenuCatalog.groups(for: .everything)
        let itemIDs = groups.flatMap(\.items).map(\.id)

        #expect(groups.map(\.id) == ["inventory", "locations", "goodsIn", "goodsOut", "clocking"])
        #expect(itemIDs.contains("inventory.pallets"))
        #expect(itemIDs.contains("goodsOut.fulfilmentReturns"))
    }

    @Test func menuForStaffWithoutWarehouseAccessOnlyShowsClockingAndHr() {
        let groups = ShellMenuCatalog.groups(for: .nothing)

        #expect(groups.map(\.id) == ["clocking"])
        #expect(groups.first?.items.map(\.id) == ["clocking.clocking", "clocking.timesheets"])
    }

    @Test func menuWithWarehouseButNoFulfilmentHidesFulfilmentRows() {
        var modules = ModuleAccess.everything
        modules.fulfilment = false
        let items = ShellMenuCatalog.groups(for: modules).flatMap(\.items)

        #expect(items.allSatisfy { $0.requirement != .fulfilment })
        #expect(items.contains { $0.id == "goodsOut.deliveryNotes" })
    }

    @Test func homeModulesForStaffOnlyShowClockingAndHr() {
        let staff = ShellHomeContent.modules(hasWarehouseAccess: false, warehouseLabel: "Main Warehouse · WH1", weekTotal: "29h 05m")
        let warehouse = ShellHomeContent.modules(hasWarehouseAccess: true, warehouseLabel: "Main Warehouse · WH1", weekTotal: nil)

        #expect(staff.map(\.id) == ["hr"])
        #expect(staff.first?.subtitle == "This week 29h 05m")
        #expect(warehouse.map(\.id) == ["warehouse", "hr"])
        #expect(warehouse.last?.subtitle == "—")
    }

    @Test func greetingFollowsTheTimeOfDay() {
        #expect(ShellHomeContent.greeting(hour: 8, firstName: "Alex") == "Good morning, Alex")
        #expect(ShellHomeContent.greeting(hour: 14, firstName: "Sam") == "Good afternoon, Sam")
        #expect(ShellHomeContent.greeting(hour: 21, firstName: "Alex") == "Good evening, Alex")
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
