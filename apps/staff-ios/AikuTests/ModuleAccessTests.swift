import Foundation
import Testing
@testable import Aiku

struct ModuleAccessTests {
    private func warehouse(_ json: String) throws -> MobileWarehouse {
        let body = #"{"id":3,"code":"WH1","name":"Main","organisation":{"id":1,"name":"AW UK"},"can":\#(json)}"#
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(MobileWarehouse.self, from: Data(body.utf8))
    }

    @Test func selectedWarehouseAbilitiesDecideTheModules() throws {
        let picker = try warehouse(#"{"view_locations":true,"edit_locations":false,"view_goods_out":true,"pick_and_pack":true,"view_inventory":false,"view_goods_in":false,"view_fulfilment":false}"#)

        let modules = try #require(ModuleAccess.resolve(warehouse: picker, isSignedIn: true))

        #expect(modules == ModuleAccess(inventory: false, locations: true, goodsIn: false, returns: false, goodsOut: true, fulfilment: false))
    }

    @Test func everyAbilityTheServerSendsIsRead() throws {
        let full = try warehouse(#"{"view_locations":true,"edit_locations":true,"view_goods_in":true,"book_in":true,"supervise_goods_in":true,"edit_procurement":true,"place_stock_deliveries":true,"view_returns":true,"handle_returns":true}"#)
        let older = try warehouse(#"{"view_locations":false,"edit_locations":false}"#)

        #expect(full.can.superviseGoodsIn)
        #expect(full.can.editProcurement)
        #expect(full.can.placeStockDeliveries)
        #expect(full.can.viewReturns)
        #expect(full.can.handleReturns)
        #expect(!older.can.viewReturns)
        #expect(!older.can.handleReturns)
        #expect(!older.can.superviseGoodsIn)
    }

    @Test func signedInWithoutAWarehouseShowsNoModulesUntilTheServerAnswers() {
        #expect(ModuleAccess.resolve(warehouse: nil, isSignedIn: true) == .nothing)
        #expect(ModuleAccess.resolve(warehouse: nil, isSignedIn: false) == nil)
    }

    @MainActor
    @Test func aReturnsClerkSeesGoodsInWithOnlyReturns() throws {
        let clerk = try warehouse(#"{"view_locations":false,"edit_locations":false,"view_goods_in":false,"view_returns":true,"handle_returns":true}"#)
        let modules = try #require(ModuleAccess.resolve(warehouse: clerk, isSignedIn: true))

        #expect(modules.hasGoodsInModule)
        #expect(modules.hasWarehouseModules)
        let goodsIn = ShellMenuCatalog.groups(for: modules).first { $0.id == "goodsIn" }
        #expect(goodsIn?.items.map(\.id) == ["goodsIn.returns"])
        #expect(ShellHomeContent.shortcuts(for: modules).first { $0.id == "goodsIn" }?.link == .goodsIn(.returns))
        #expect(ShellDashboardContent.tiles(for: modules).first { $0.id == "goodsIn" }?.link == .goodsIn(.returns))
        #expect(AppModule.goodsIn.sections(for: modules).map(\.id) == [.goodsInReturns])
    }

    @MainActor
    @Test func goodsInStaffWithoutTheReturnsRightDoNotSeeReturns() throws {
        let staff = try warehouse(#"{"view_locations":false,"edit_locations":false,"view_goods_in":true,"view_returns":false}"#)
        let modules = try #require(ModuleAccess.resolve(warehouse: staff, isSignedIn: true))

        let goodsIn = ShellMenuCatalog.groups(for: modules).first { $0.id == "goodsIn" }
        #expect(goodsIn?.items.map(\.id) == ["goodsIn.backlog", "goodsIn.stockDeliveries"])
        #expect(ShellHomeContent.shortcuts(for: modules).first { $0.id == "goodsIn" }?.link == .goodsIn(.backlog))
        #expect(AppModule.goodsIn.sections(for: modules).map(\.id) == [.goodsInBacklog, .stockDeliveries])
    }

    @MainActor
    @Test func fulfilmentSectionsFollowTheFulfilmentRight() {
        let withFulfilment = ModuleAccess(inventory: true, locations: false, goodsIn: true, returns: true, goodsOut: false, fulfilment: true)
        let without = ModuleAccess(inventory: true, locations: false, goodsIn: true, returns: true, goodsOut: false, fulfilment: false)

        #expect(AppModule.goodsIn.sections(for: withFulfilment).map(\.id).contains(.fulfilmentDeliveries))
        #expect(!AppModule.goodsIn.sections(for: without).map(\.id).contains(.fulfilmentDeliveries))
        #expect(!AppModule.inventory.sections(for: without).map(\.id).contains(.storedPallets))
    }

    @MainActor
    @Test func fulfilmentOnlyStaffSeeOnlyTheFulfilmentTabsLikeAndroid() {
        let fulfilmentOnly = ModuleAccess(inventory: false, locations: false, goodsIn: false, returns: false, goodsOut: false, fulfilment: true)

        #expect(AppModule.inventory.sections(for: fulfilmentOnly).map(\.id) == [.storedPallets, .storedItems])
        #expect(AppModule.goodsOut.sections(for: fulfilmentOnly).map(\.id) == [.fulfilmentReturns])
        #expect(AppModule.goodsIn.sections(for: fulfilmentOnly).map(\.id) == [.fulfilmentDeliveries])
        #expect(AppModule.inventory.sections(for: .everything).map(\.id) == [.inventoryDashboard, .skoFamilies, .skos, .storedPallets, .storedItems])
        #expect(AppModule.goodsOut.sections(for: .everything).map(\.id) == [.goodsOutBacklog, .deliveryNotes, .pickingSessions, .fulfilmentReturns, .trolleys, .pickedBays])
    }

    @Test func menuOnlyListsModulesTheUserCanOpen() {
        let modules = ModuleAccess(inventory: false, locations: false, goodsIn: false, goodsOut: true, fulfilment: false)

        let groups = ShellMenuCatalog.groups(for: modules)

        #expect(groups.map(\.id) == ["goodsOut", "clocking"])
        #expect(ShellHomeContent.shortcuts(for: modules).map(\.id) == ["goodsOut"])
        #expect(ShellDashboardContent.tiles(for: modules).map(\.id) == ["goodsOut"])
    }

    @MainActor
    @Test func staffWithoutWarehouseModulesCannotScanOrSearch() {
        let session = Session()
        session.modules = .nothing

        #expect(!session.hasWarehouseAccess)
        #expect(!session.canScan)
        #expect(ShellMenuCatalog.groups(for: session.visibleModules).map(\.id) == ["clocking"])
    }
}

struct HardwareScanBufferTests {
    private let start = Date(timeIntervalSince1970: 1_000)

    @Test func scannerKeysFollowedByEnterGiveOneCode() {
        var buffer = HardwareScanBuffer()
        for (offset, key) in "SKO-123".enumerated() {
            buffer.append(String(key), at: start.addingTimeInterval(Double(offset) * 0.02))
        }

        #expect(buffer.finish(at: start.addingTimeInterval(0.2)) == "SKO-123")
        #expect(buffer.characters.isEmpty)
    }

    @Test func enterWithNothingTypedGivesNoCode() {
        var buffer = HardwareScanBuffer()

        #expect(buffer.finish(at: start) == nil)
    }

    @Test func staleKeysAreDroppedBeforeTheNextScan() {
        var buffer = HardwareScanBuffer()
        buffer.append("XX", at: start)
        buffer.append("L-01", at: start.addingTimeInterval(5))

        #expect(buffer.finish(at: start.addingTimeInterval(5.05)) == "L-01")
    }

    @Test func enterLongAfterTheLastKeyIsIgnored() {
        var buffer = HardwareScanBuffer()
        buffer.append("DN-9", at: start)

        #expect(buffer.finish(at: start.addingTimeInterval(3)) == nil)
    }

    @Test func controlCharactersAreNotPartOfTheCode() {
        var buffer = HardwareScanBuffer()
        buffer.append("\u{1D}AB\r\n", at: start)

        #expect(buffer.finish(at: start) == "AB")
    }

    @Test func cameraModeTurnsTheScannerOff() {
        #expect(ScanInputMode.automatic.listensToScannerDevice)
        #expect(ScanInputMode.scannerDevice.listensToScannerDevice)
        #expect(!ScanInputMode.camera.listensToScannerDevice)
    }
}
