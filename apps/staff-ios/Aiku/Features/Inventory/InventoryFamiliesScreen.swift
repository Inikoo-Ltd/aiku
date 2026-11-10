import SwiftUI

struct InventoryFamiliesScreen: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var state: InventoryFamilyState = .active
    @State private var search = ""
    @State private var isScanning = false
    @State private var focusAfterScan = false
    @FocusState private var isSearchFocused: Bool
    private let store = InventoryStore.shared

    var body: some View {
        let families = store.families(in: state, search: search)
        AppScreen(title: "SKO families", kicker: "Inventory · \(session.warehouseLabel)") {
            ModuleTabs(.inventory, current: .skoFamilies)
        } content: {
            InventoryScopeChips(
                options: InventoryFamilyState.allCases,
                selection: state,
                title: \.title,
                count: { option in
                    let count = store.familyCount(option)
                    return count > 0 ? count : nil
                },
                onSelect: { state = $0 }
            )
            InventorySearchField(text: $search, placeholder: "Code or name", isFocused: $isSearchFocused) {
                isScanning = true
            }
            if families.isEmpty {
                EmptyState(
                    icon: .skoFamilies,
                    title: search.isEmpty ? "No \(state.title.lowercased()) families" : "No families match “\(search)”",
                    message: search.isEmpty ? nil : "Try a different code or name."
                )
            } else {
                CardList(families) { family in
                    ListRow(family.title, subtitle: family.subtitle, icon: .sko, iconTone: .info) {
                        router.open(InventoryRoute.family(code: family.code))
                    }
                    .accessibilityIdentifier("inventory.family.\(family.code)")
                }
            }
        }
        .fullScreenCover(isPresented: $isScanning, onDismiss: focusSearchIfNeeded) {
            InventoryScanCover(
                kicker: "SKO families · \(session.warehouseLabel)",
                onClose: { isScanning = false },
                onScan: {
                    isScanning = false
                    router.open(InventoryRoute.sko(reference: store.skos[0].reference))
                },
                onTypeCode: {
                    focusAfterScan = true
                    isScanning = false
                }
            )
        }
    }

    private func focusSearchIfNeeded() {
        guard focusAfterScan else { return }
        focusAfterScan = false
        isSearchFocused = true
    }
}

struct InventoryFamilyDetailScreen: View {
    @Environment(AppRouter.self) private var router
    let code: String
    private let store = InventoryStore.shared

    var body: some View {
        let family = store.family(code: code)
        let skos = InventorySkoQuery.sorted(store.skos(inFamily: family.code), by: .reference)
        AppScreen(title: family.title, kicker: "Stock family · \(family.state.title)") {
            StatGrid(columns: 3) {
                StatTile("\(family.skoCount)", label: "SKOs")
                StatTile(family.stockValueLabel, label: "Stock value")
                StatTile("\(family.coverWeeks) wk", label: "Cover")
            }
            SectionHeading("SKOs")
            if skos.isEmpty {
                EmptyState(icon: .sko, title: "No SKOs in this family yet")
            } else {
                CardList(skos) { sko in
                    InventorySkoRow(reference: sko.reference, chip: sko.listChip, subtitle: sko.name, quantity: sko.stock) {
                        router.open(InventoryRoute.sko(reference: sko.reference))
                    }
                }
            }
        }
    }
}
