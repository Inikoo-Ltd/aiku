import SwiftUI

struct InventoryAddLocationSheet: View {
    let sko: InventorySko
    let options: (String) async throws -> [InventoryLocationOption]
    let onCancel: () -> Void
    let onSave: (InventoryLocationOption, InventoryLocationType) async throws -> String
    let onDone: (String) -> Void
    @State private var search = ""
    @State private var matches: [InventoryLocationOption] = []
    @State private var isSearching = false
    @State private var selection: InventoryLocationOption?
    @State private var type: InventoryLocationType = .storing
    @State private var error: String?
    @State private var isSaving = false
    @FocusState private var isSearchFocused: Bool

    var body: some View {
        SheetScaffold(title: L10n.format("Add location · %@", sko.reference), subtitle: "Scan or search a location") {
            InventorySearchField(text: $search, placeholder: "Location code", isFocused: $isSearchFocused) {
                if let first = matches.first {
                    search = first.code
                    selection = first
                    isSearchFocused = false
                }
            }
            if isSearching, matches.isEmpty {
                ProgressView()
                    .frame(maxWidth: .infinity, minHeight: 44)
                    .accessibilityLabel("Looking for locations")
            } else if matches.isEmpty {
                HelperText(search.trimmingCharacters(in: .whitespaces).isEmpty ? "Type a location code to find it." : L10n.format("No free location matches “%@”.", search))
            } else {
                ForEach(matches) { option in
                    RadioCard(option.code, subtitle: option.detail, isSelected: option.code == selection?.code) {
                        selection = option
                        error = nil
                    }
                }
            }
            SheetSectionLabel("Type")
            RadioCard(InventoryLocationType.storing.title, isSelected: type == .storing) {
                type = .storing
            }
            RadioCard(InventoryLocationType.picking.title, subtitle: pickingHint, isSelected: type == .picking) {
                type = .picking
            }
            if let error {
                FieldError(error)
            }
        } footer: {
            AikuButton(isSaving ? "Adding…" : selection.map { L10n.format("Add %@", $0.code) } ?? "Add location", look: AikuButtonLook(.primary, icon: .plus)) {
                save()
            }
            .disabled(selection == nil || isSaving)
            .accessibilityIdentifier("inventory.addLocation.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
        .task(id: search) {
            if !matches.isEmpty || !search.isEmpty {
                try? await Task.sleep(for: .milliseconds(300))
                guard !Task.isCancelled else { return }
            }
            isSearching = true
            defer { isSearching = false }
            do {
                matches = Array(try await options(search).prefix(4))
            } catch where error.isCancellation {
                return
            } catch {
                matches = []
                self.error = InventoryLive.message(for: error)
            }
        }
    }

    private var pickingHint: String {
        let current = sko.locations.filter { $0.type == .picking }.map(\.code)
        guard !current.isEmpty, sko.live != nil else { return "Its other picking location becomes storing" }
        return L10n.format("%@, its picking location now, becomes storing", current.joined(separator: ", "))
    }

    private func save() {
        guard let selection else { return }
        isSaving = true
        Task {
            defer { isSaving = false }
            do {
                onDone(try await onSave(selection, type))
            } catch {
                self.error = InventoryLive.message(for: error)
            }
        }
    }
}

struct InventoryRemoveLocationsSheet: View {
    let sko: InventorySko
    let warning: String
    let onCancel: () -> Void
    let onSave: (Set<String>) async throws -> String
    let onDone: (String) -> Void
    @State private var selection: Set<String>
    @State private var error: String?
    @State private var isSaving = false

    init(
        sko: InventorySko,
        warning: String,
        onCancel: @escaping () -> Void,
        onSave: @escaping (Set<String>) async throws -> String,
        onDone: @escaping (String) -> Void
    ) {
        self.sko = sko
        self.warning = warning
        self.onCancel = onCancel
        self.onSave = onSave
        self.onDone = onDone
        _selection = State(initialValue: Set(sko.locations.filter { $0.quantity == 0 && ($0.link?.exactQuantity ?? 0) == 0 }.map(\.code)))
    }

    var body: some View {
        SheetScaffold(title: L10n.format("Remove locations · %@", sko.reference), subtitle: "Choose the locations to remove") {
            ForEach(sko.locations) { location in
                RadioCard(
                    location.code,
                    subtitle: [location.typeLabel, L10n.format("%@ in location", location.quantityText)].joined(separator: " · "),
                    isSelected: selection.contains(location.code)
                ) {
                    selection = InventorySkoFilter.toggled(selection, location.code)
                    error = nil
                }
            }
            InventoryDangerNote(text: warning)
            if let error {
                FieldError(error)
            }
        } footer: {
            AikuButton(buttonTitle, look: AikuButtonLook(.dangerStrong, icon: .trash)) {
                save()
            }
            .disabled(selection.isEmpty || isSaving)
            .accessibilityIdentifier("inventory.removeLocations.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }

    private var buttonTitle: String {
        if isSaving { return "Removing…" }
        return selection.isEmpty ? "Remove locations" : L10n.format("Remove %lld locations", selection.count)
    }

    private func save() {
        isSaving = true
        Task {
            defer { isSaving = false }
            do {
                onDone(try await onSave(selection))
            } catch {
                self.error = InventoryLive.message(for: error)
            }
        }
    }
}
