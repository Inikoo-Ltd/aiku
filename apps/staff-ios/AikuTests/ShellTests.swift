import Foundation
import Testing
@testable import Aiku

@MainActor
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
        #expect(groups.first?.items.map(\.id) == ["clocking.clocking", "clocking.timesheets", "clocking.leave", "clocking.adjustments", "clocking.overtime", "clocking.calendar"])
        #expect(groups.first?.title == "Clocking & Attendance")
        #expect(groups.first?.note == nil)
    }

    @Test func menuWithWarehouseButNoFulfilmentHidesFulfilmentRows() {
        var modules = ModuleAccess.everything
        modules.fulfilment = false
        let items = ShellMenuCatalog.groups(for: modules).flatMap(\.items)

        #expect(items.allSatisfy { $0.requirement != .fulfilment })
        #expect(items.contains { $0.id == "goodsOut.deliveryNotes" })
    }

    @Test func homeShowsTheWarehouseAsOneRowAndNoClockingTile() {
        let staff = ShellHomeContent.modules(hasWarehouseAccess: false, warehouseLabel: "Main Warehouse · WH1")
        let warehouse = ShellHomeContent.modules(hasWarehouseAccess: true, warehouseLabel: "Main Warehouse · WH1")

        #expect(staff.isEmpty)
        #expect(warehouse.map(\.id) == ["warehouse"])
        #expect(warehouse.first?.icon == .faWarehouse)
        #expect(ShellHomeContent.conflictsText(needsYou: 0) == "Nothing needs you")
        #expect(ShellHomeContent.conflictsText(needsYou: 2) == "2 need you")
    }

    @Test func homeShortcutsOpenTheBacklogsTheDashboardAndTheWarehouse() {
        let links = Dictionary(uniqueKeysWithValues: ShellHomeContent.shortcuts(for: .everything).map { ($0.id, $0.link) })

        #expect(links["goodsOut"] == .goodsOut(.backlog))
        #expect(links["goodsIn"] == .goodsIn(.backlog))
        #expect(links["inventory"] == .inventory(.dashboard))
        #expect(links["locations"] == .locations(.warehouse))
        #expect(ShellDashboardContent.tiles(for: .everything).map(\.link) == [.inventory(.dashboard), .locations(.warehouse), .goodsIn(.backlog), .goodsOut(.backlog)])
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

    @Test func languageListMatchesTheWebAndFallsBackToEnglish() {
        #expect(AppLanguages.all.count == 23)
        #expect(AppLanguages.language(id: "xx").id == "en")
        #expect(AppLanguages.language(id: "sk").nativeName == "Slovenčina")
    }
}

@MainActor
struct ShellProfileLoadingTests {
    @Test func noSampleNameOrWarehouseShowsBeforeTheProfileLoads() {
        let session = Session()

        #expect(!session.hasProfile)
        #expect(session.profile.name.isEmpty)
        #expect(session.warehouseLabel.isEmpty)
        #expect(session.fullContextLabel.isEmpty)
        #expect(ShellHomeContent.context(hasWarehouseAccess: true, organisation: session.profile.organisation, warehouseName: session.profile.warehouseName) == nil)
        #expect(ShellHomeContent.context(hasWarehouseAccess: false, organisation: session.profile.organisation, warehouseName: session.profile.warehouseName) == nil)
    }

    @Test func theHeaderFillsInAsTheProfileAndWarehouseArrive() {
        let session = Session()

        session.apply(MobileProfileResponse(id: 1, username: "andi", name: "Andi Ferdiawan", position: nil, jobTitle: nil, jobPositions: [], organisation: .init(id: 2, code: nil, name: "Ancient Wisdom Ltd")))
        #expect(session.hasProfile)
        #expect(session.profile.firstName == "Andi")
        #expect(ShellHomeContent.context(hasWarehouseAccess: false, organisation: session.profile.organisation, warehouseName: session.profile.warehouseName) == "Ancient Wisdom Ltd · Customer services")
        #expect(ShellHomeContent.context(hasWarehouseAccess: true, organisation: session.profile.organisation, warehouseName: session.profile.warehouseName) == nil)

        session.apply(warehouse: "Europa Drive", code: "ED", organisation: "Ancient Wisdom Ltd")
        #expect(ShellHomeContent.context(hasWarehouseAccess: true, organisation: session.profile.organisation, warehouseName: session.profile.warehouseName) == "Ancient Wisdom Ltd · Europa Drive")
        #expect(session.warehouseLabel == "Europa Drive · ED")
    }

    @Test func signingOutForgetsTheLastUser() {
        let session = Session()
        session.apply(MobileProfileResponse(id: 1, username: "sandra", name: "Sandra Sadowska", position: nil, jobTitle: nil, jobPositions: [], organisation: nil))
        session.apply(warehouse: "Europa Drive", code: "ED", organisation: "Ancient Wisdom Ltd")

        session.forgetProfile()

        #expect(!session.hasProfile)
        #expect(session.fullContextLabel.isEmpty)
        #expect(!session.hasWarehouseContext)
    }
}

@MainActor
struct ShellProfileRowsTests {
    private func isolatedDefaults() -> UserDefaults {
        let name = "aiku.tests.profile.\(UUID().uuidString)"
        let defaults = UserDefaults(suiteName: name)!
        defaults.removePersistentDomain(forName: name)
        return defaults
    }

    @Test func profileRowsShowRealValuesLikeAndroid() {
        #expect(ShellProfileContent.clockingValue(isClockedIn: true) == "Clocked in")
        #expect(ShellProfileContent.clockingValue(isClockedIn: false) == "Clocked out")
        #expect(ShellProfileContent.clockingValue(isClockedIn: nil) == "—")
        #expect(ShellProfileContent.weekValue("3h 05m") == "This week 3h 05m")
        #expect(ShellProfileContent.weekValue(nil) == "—")
        #expect(ShellProfileContent.versionValue(name: "0.1.0", build: "7") == "0.1.0 (7)")
        #expect(ShellProfileContent.versionValue(name: "0.1.0", build: nil) == "0.1.0")
        #expect(ShellProfileContent.versionValue(name: nil, build: nil) == "—")
        #expect(ShellProfileContent.notificationsValue(isAllowed: true) == "On")
        #expect(ShellProfileContent.notificationsValue(isAllowed: false) == "Off")
        #expect(ShellProfileContent.notificationsAllowed(.authorized))
        #expect(ShellProfileContent.notificationsAllowed(.provisional))
        #expect(!ShellProfileContent.notificationsAllowed(.denied))
        #expect(!ShellProfileContent.notificationsAllowed(.notDetermined))
        #expect(!ShellProfileContent.hasNotificationSettings(.notDetermined))
        #expect(ShellProfileContent.hasNotificationSettings(.denied))
    }

    @Test func syncAndSignOutTextsCountLikeAndroid() {
        #expect(ShellProfileContent.syncValue(waiting: 0, needsYou: 0) == "All synced")
        #expect(ShellProfileContent.syncValue(waiting: 3, needsYou: 0) == "3 waiting")
        #expect(ShellProfileContent.syncValue(waiting: 3, needsYou: 1) == "1 needs you")
        #expect(ShellProfileContent.syncValue(waiting: 3, needsYou: 2) == "2 need you")
        #expect(ShellProfileContent.signOutWaitingMessage(1) == "1 action from you is waiting; it will be sent when you sign in again on this phone.")
        #expect(ShellProfileContent.signOutWaitingMessage(4) == "4 actions from you are waiting; they will be sent when you sign in again on this phone.")
    }

    @Test func countedTextsFollowEachLanguagesPlurals() {
        let store = LanguageStore(defaults: isolatedDefaults(), bundle: .main, preferredLanguages: ["en"])
        #expect(store.format("%lld need you", [1]) == "1 needs you")

        store.select("bg")
        #expect(store.format("%lld waiting", [1]) == "1 чака")
        #expect(store.format("%lld waiting", [3]) == "3 чакат")

        store.select("id")
        #expect(store.format("This week %@", ["3j 5m"]) == "Minggu ini 3j 5m")
        #expect(store.format("Not in the catalogue %@", ["x"]) == "Not in the catalogue x")
        #expect(store.format("Use %@", ["Deutsch"]) == "Gunakan Deutsch")
        #expect(store.format("Language set to %@", ["Deutsch"]) == "Bahasa diatur ke Deutsch")
        #expect(store.text("Work") == "Pekerjaan")
    }

    @Test func eachLanguageIsNamedInTheAppLanguageUnderItsOwnNameLikeAndroid() {
        let german = AppLanguages.language(id: "de")

        #expect(german.name(in: Locale(identifier: "en")) == "German")
        #expect(german.name(in: Locale(identifier: "id")) == "Jerman")
        #expect(AppLanguages.language(id: "zh-Hans").name(in: Locale(identifier: "en")).hasPrefix("Chinese"))
    }
}
