import SwiftUI

struct InventoryAddLocationSheet: View {
    let sko: InventorySko
    let options: (String) -> [InventoryLocationOption]
    let onCancel: () -> Void
    let onSave: (String, InventoryLocationType) throws -> String
    let onDone: (String) -> Void
    @State private var search = ""
    @State private var selection: String?
    @State private var type: InventoryLocationType = .storing
    @State private var error: String?
    @FocusState private var isSearchFocused: Bool

    var body: some View {
        let matches = Array(options(search).prefix(4))
        SheetScaffold(title: "Add location · \(sko.reference)", subtitle: "Scan or search a location") {
            InventorySearchField(text: $search, placeholder: "Location code", isFocused: $isSearchFocused) {
                if let first = options("").first {
                    search = first.code
                    selection = first.code
                    isSearchFocused = false
                }
            }
            if matches.isEmpty {
                HelperText("No free location matches “\(search)”.")
            } else {
                ForEach(matches) { option in
                    RadioCard(option.code, subtitle: option.detail, isSelected: option.code == selection) {
                        selection = option.code
                        error = nil
                    }
                }
            }
            SheetSectionLabel("Type")
            RadioCard(InventoryLocationType.storing.title, isSelected: type == .storing) {
                type = .storing
            }
            RadioCard(InventoryLocationType.picking.title, subtitle: "Its other picking location becomes storing", isSelected: type == .picking) {
                type = .picking
            }
            if let error {
                FieldError(error)
            }
        } footer: {
            AikuButton(selection.map { "Add \($0)" } ?? "Add location") {
                save()
            }
            .disabled(selection == nil)
            .accessibilityIdentifier("inventory.addLocation.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }

    private func save() {
        guard let selection else { return }
        do {
            onDone(try onSave(selection, type))
        } catch let failure as InventoryStockError {
            error = failure.message
        } catch {
            self.error = "Couldn’t add the location."
        }
    }
}

struct InventoryRemoveLocationsSheet: View {
    let sko: InventorySko
    let onCancel: () -> Void
    let onSave: (Set<String>) throws -> String
    let onDone: (String) -> Void
    @State private var selection: Set<String>
    @State private var error: String?

    init(
        sko: InventorySko,
        onCancel: @escaping () -> Void,
        onSave: @escaping (Set<String>) throws -> String,
        onDone: @escaping (String) -> Void
    ) {
        self.sko = sko
        self.onCancel = onCancel
        self.onSave = onSave
        self.onDone = onDone
        _selection = State(initialValue: Set(sko.locations.filter { $0.quantity == 0 }.map(\.code)))
    }

    var body: some View {
        SheetScaffold(title: "Remove locations · \(sko.reference)", subtitle: "Choose the locations to remove") {
            ForEach(sko.locations) { location in
                RadioCard(
                    location.code,
                    subtitle: "\(location.typeLabel) · \(location.quantity) in location",
                    isSelected: selection.contains(location.code)
                ) {
                    selection = InventorySkoFilter.toggled(selection, location.code)
                    error = nil
                }
            }
            InventoryDangerNote(text: "Any stock left in a removed location will be removed and marked as lost.")
            if let error {
                FieldError(error)
            }
        } footer: {
            AikuButton(buttonTitle, kind: .danger) {
                save()
            }
            .disabled(selection.isEmpty)
            .accessibilityIdentifier("inventory.removeLocations.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }

    private var buttonTitle: String {
        switch selection.count {
        case 0: "Remove locations"
        case 1: "Remove 1 location"
        default: "Remove \(selection.count) locations"
        }
    }

    private func save() {
        do {
            onDone(try onSave(selection))
        } catch let failure as InventoryStockError {
            error = failure.message
        } catch {
            self.error = "Couldn’t remove the locations."
        }
    }
}
