import Foundation
import Testing
@testable import Aiku

struct ModuleAccessTests {
    private func permissions(_ names: [String]) -> UserPermissions {
        UserPermissions(userID: 7, permissions: Set(names), roles: [], fetchedAt: .now)
    }

    private func warehouse(_ json: String) throws -> MobileWarehouse {
        let body = #"{"id":3,"code":"WH1","name":"Main","organisation":{"id":1,"name":"AW UK"},"can":\#(json)}"#
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return try decoder.decode(MobileWarehouse.self, from: Data(body.utf8))
    }

    @Test func selectedWarehouseAbilitiesDecideTheModules() throws {
        let picker = try warehouse(#"{"view_locations":true,"edit_locations":false,"view_goods_out":true,"pick_and_pack":true,"view_inventory":false,"view_goods_in":false,"view_fulfilment":false}"#)

        let modules = try #require(ModuleAccess.resolve(warehouse: picker, permissions: permissions(["incoming.3.view"])))

        #expect(modules == ModuleAccess(inventory: false, locations: true, goodsIn: false, goodsOut: true, fulfilment: false))
    }

    @Test func cachedPermissionsDecideTheModulesBeforeWarehousesLoad() throws {
        let modules = try #require(ModuleAccess.resolve(warehouse: nil, permissions: permissions(["incoming.3.view", "supervisor-stocks.3", "locations.3.view"])))

        #expect(modules.goodsIn)
        #expect(modules.inventory)
        #expect(modules.locations)
        #expect(!modules.goodsOut)
        #expect(!modules.fulfilment)
    }

    @Test func fulfilmentViewersSeeGoodsOutLikeTheWeb() throws {
        let modules = ModuleAccess(permissions: permissions(["fulfilment.3.view"]))

        #expect(modules.goodsOut)
        #expect(modules.fulfilment)
        #expect(modules.canScan)
    }

    @Test func nothingKnownKeepsTheCurrentModules() {
        #expect(ModuleAccess.resolve(warehouse: nil, permissions: nil) == nil)
    }

    @Test func menuOnlyListsModulesTheUserCanOpen() {
        let modules = ModuleAccess(inventory: false, locations: false, goodsIn: false, goodsOut: true, fulfilment: false)

        let groups = ShellMenuCatalog.groups(for: modules)

        #expect(groups.map(\.id) == ["goodsOut", "clocking"])
        #expect(ShellHomeContent.shortcuts(for: modules).map(\.id) == ["goodsOut"])
        #expect(ShellDashboardContent.tiles(for: modules).map(\.id) == ["goodsOut", "clocking"])
    }

    @MainActor
    @Test func staffWithoutWarehouseModulesCannotScanOrSearch() {
        let session = Session()
        session.modules = ModuleAccess(permissions: permissions(["hr.view"]))

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
