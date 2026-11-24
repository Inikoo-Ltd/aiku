import SwiftUI

struct InventoryMoveSheet: View {
    let sko: InventorySko
    var showsReasons = true
    let onCancel: () -> Void
    let onSave: (String, String, Int, InventoryMoveReason) async throws -> String
    let onDone: (String) -> Void
    @State private var source: String
    @State private var destination: String
    @State private var quantity: Int
    @State private var reason: InventoryMoveReason = .warehouseTransfer
    @State private var error: String?
    @State private var isSaving = false
    @State private var isConfirming = false

    init(
        sko: InventorySko,
        showsReasons: Bool = true,
        onCancel: @escaping () -> Void,
        onSave: @escaping (String, String, Int, InventoryMoveReason) async throws -> String,
        onDone: @escaping (String) -> Void
    ) {
        self.sko = sko
        self.showsReasons = showsReasons
        self.onCancel = onCancel
        self.onSave = onSave
        self.onDone = onDone
        let from = sko.locations.first { $0.quantity > 0 } ?? sko.locations.first
        let to = sko.locations.first { $0.code != from?.code && $0.type == .storing }
            ?? sko.locations.first { $0.code != from?.code }
        _source = State(initialValue: from?.code ?? "")
        _destination = State(initialValue: to?.code ?? "")
        _quantity = State(initialValue: min(12, max(1, from?.quantity ?? 1)))
    }

    var body: some View {
        SheetScaffold(title: L10n.format("Move stock · %@", sko.reference), subtitle: "Between two of its locations") {
            SheetSectionLabel("From")
            ForEach(sko.locations.filter { $0.quantity > 0 }) { location in
                RadioCard(L10n.format("From %@", location.code), subtitle: L10n.format("%@ in location", location.quantityText), isSelected: location.code == source) {
                    selectSource(location)
                }
            }
            SheetSectionLabel("To")
            ForEach(sko.locations.filter { $0.code != source }) { location in
                RadioCard(L10n.format("To %@", location.code), subtitle: L10n.format("%@ in location", location.quantityText), isSelected: location.code == destination) {
                    destination = location.code
                    error = nil
                }
            }
            InventoryQuantityField(title: "Quantity", value: $quantity, range: 1...max(1, available))
            if available > 0, quantity != available {
                LinkButton(L10n.format("Move all %@", String(available))) {
                    quantity = available
                }
                .frame(minHeight: 44)
            }
            if showsReasons {
                SheetSectionLabel("Reason")
                ForEach(InventoryMoveReason.allCases, id: \.self) { option in
                    RadioCard(option.title, isSelected: option == reason) {
                        reason = option
                    }
                }
            }
            if let error {
                FieldError(error)
            }
        } footer: {
            AikuButton(isSaving ? "Moving…" : moveTitle, icon: .next) {
                isConfirming = true
            }
            .disabled(!canSave || isSaving)
            .accessibilityIdentifier("inventory.move.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
        .aikuConfirm(
            "Move this stock?",
            isPresented: $isConfirming,
            message: StockConfirmText.moveSummary(quantity: quantity, from: source, to: destination, packedIn: sko.packedIn),
            confirmTitle: moveTitle
        ) {
            save()
        }
    }

    private var moveTitle: String {
        L10n.format("Move %@", StockConfirmText.quantityLabel(quantity, packedIn: sko.packedIn))
    }

    private var available: Int {
        sko.location(source)?.quantity ?? 0
    }

    private var canSave: Bool {
        !source.isEmpty && !destination.isEmpty && source != destination && quantity > 0 && quantity <= available
    }

    private func selectSource(_ location: InventorySkoLocation) {
        source = location.code
        if destination == location.code {
            destination = sko.locations.first { $0.code != location.code }?.code ?? ""
        }
        quantity = min(quantity, max(1, location.quantity))
        error = nil
    }

    private func save() {
        isSaving = true
        Task {
            defer { isSaving = false }
            do {
                onDone(try await onSave(source, destination, quantity, reason))
            } catch {
                self.error = InventoryLive.message(for: error)
            }
        }
    }
}
