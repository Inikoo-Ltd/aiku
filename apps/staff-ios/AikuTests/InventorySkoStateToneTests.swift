import Foundation
import Testing
@testable import Aiku

@MainActor
struct InventorySkoStateToneTests {
    @Test func everySkoStateHasOneToneAsOnTheWeb() {
        #expect(InventorySkoState.active.tone == .success)
        #expect(InventorySkoState.discontinuing.tone == .warn)
        #expect(InventorySkoState.discontinued.tone == .danger)
        #expect(InventorySkoState.abnormality.tone == .danger)
        #expect(InventorySkoState.suspended.tone == .neutral)
        #expect(InventorySkoState.remoteTone("discontinuing") == .warn)
        #expect(InventorySkoState.remoteTone("something_new") == .neutral)
    }

    @Test func activeSkosShowNoStateChipAndOthersUseTheirStateTone() {
        #expect(InventorySkoState.listChip(remote: "active", label: "Active") == nil)
        #expect(InventorySkoState.listChip(remote: "discontinuing", label: "Auslaufend") == Chip("Auslaufend", .warn))
        #expect(InventorySkoState.listChip(remote: "discontinued", label: " ") == Chip("Discontinued", .danger))
        #expect(InventorySkoState.listChip(remote: "suspended", label: nil) == Chip("Suspended", .neutral))
        #expect(InventorySkoState.listChip(remote: "abnormality", label: "Abnormality") == Chip("Abnormality", .danger))
        #expect(InventorySkoState.listChip(remote: "something_new", label: nil) == nil)
    }

    @Test func skoListRowsUseTheStateTone() {
        let suspended = InventoryLive.listItem(MobileOrgStockRow(id: 1, code: "S-1", state: "suspended", stateLabel: "Suspended"), scope: .all)
        let discontinued = InventoryLive.listItem(MobileOrgStockRow(id: 2, code: "D-1", state: "discontinued", stateLabel: "Discontinued"), scope: .discontinued)
        let active = InventoryLive.listItem(MobileOrgStockRow(id: 3, code: "A-1", state: "active", stateLabel: "Active"), scope: .current)

        #expect(suspended.chip == Chip("Suspended", .neutral))
        #expect(discontinued.chip == Chip("Discontinued", .danger))
        #expect(active.chip == nil)
    }

    @Test func skoSearchRowsUseTheStateTone() {
        let discontinuing = MobileSearchRow(type: .skos, id: 1, code: "EO-01", title: "EO-01", state: "discontinuing", stateLabel: "Discontinuing")
        let active = MobileSearchRow(type: .skos, id: 2, code: "EO-02", title: "EO-02", state: "active", stateLabel: "Active")
        let family = MobileSearchRow(type: .skoFamilies, id: 3, code: "EO", title: "EO", state: "discontinued", stateLabel: "Discontinued")

        #expect(ShellSearchFormat.tone(discontinuing) == .warn)
        #expect(ShellSearchFormat.tone(active) == .success)
        #expect(ShellSearchFormat.tone(family) == .neutral)
    }

    @Test func familyStatusTilesFollowTheStateAndCoverColours() {
        let tiles = ["active", "discontinuing", "discontinued", "suspended"].map {
            InventoryFamilyStatusItem(kind: .sko, key: $0, label: "", count: 1)
        }
        let rows = ["out", "w1", "w2", "w3", "w4", "ok", "excess", "dead"].map {
            InventoryFamilyStatusItem(kind: .quantity, key: $0, label: $0.uppercased(), count: 0)
        }

        #expect(tiles.map(\.tone) == [.success, .warn, .danger, .neutral])
        #expect(tiles.first?.title == "active")
        #expect(rows.map(\.tone) == [.danger, .danger, .orange, .warn, .warn, .success, .info, .neutral])
        #expect(rows.first?.title == "OUT")
    }
}
