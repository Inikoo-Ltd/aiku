import SwiftUI

struct LocationsMoveSheet: View {
    let source: LocationsLocation
    let warehouse: LocationsWarehouse
    let isRemote: Bool
    let onMove: (String, [String: Int], Bool, Bool) -> Void
    let onCancel: () -> Void
    let searchTargets: (String) async -> Void
    @State private var isSome: Bool
    @State private var query = ""
    @State private var targetCode: String?
    @State private var removeFromSource = false
    @State private var picked: Set<String>
    @State private var quantities: [String: Int]
    @State private var isScanning = false

    init(
        source: LocationsLocation,
        warehouse: LocationsWarehouse,
        startsWithSome: Bool,
        preselected: String? = nil,
        isRemote: Bool = false,
        searchTargets: @escaping (String) async -> Void = { _ in },
        onMove: @escaping (String, [String: Int], Bool, Bool) -> Void,
        onCancel: @escaping () -> Void
    ) {
        self.source = source
        self.warehouse = warehouse
        self.isRemote = isRemote
        self.searchTargets = searchTargets
        self.onMove = onMove
        self.onCancel = onCancel
        _isSome = State(initialValue: startsWithSome)
        _picked = State(initialValue: preselected.map { [$0] } ?? [])
        _quantities = State(initialValue: Dictionary(uniqueKeysWithValues: source.slots.map { ($0.skoReference, max($0.quantity, 0)) }))
        _targetCode = State(initialValue: isRemote ? nil : warehouse.moveTargets(from: source.code, query: "").first?.code)
    }

    private var movableSlots: [LocationsSlot] {
        source.slots.filter { $0.quantity > 0 }
    }

    private var targets: [LocationsLocation] {
        Array(warehouse.moveTargets(from: source.code, query: query).prefix(4))
    }

    private var plannedQuantities: [String: Int] {
        if isSome {
            return quantities.filter { picked.contains($0.key) && $0.value > 0 }
        }
        return warehouse.allQuantities(of: source.code).filter { $0.value > 0 }
    }

    private var plannedUnits: Int {
        plannedQuantities.values.reduce(0, +)
    }

    var body: some View {
        SheetScaffold(
            title: isSome ? "Move SKOs from \(source.code)" : "Move all SKOs from \(source.code)",
            subtitle: isSome ? "Choose the SKOs and quantities to move" : "Or choose some SKOs and quantities to move"
        ) {
            LinkButton(isSome ? "Move all SKOs instead" : "Choose some SKOs") {
                isSome.toggle()
            }
            .accessibilityIdentifier("locations.move.mode")
            SearchField(text: $query, placeholder: "Target location code", onScan: isRemote ? nil : { isScanning = true })
            if targets.isEmpty {
                HelperText("No operational stock location starts with “\(query)”.")
            }
            ForEach(targets) { target in
                RadioCard(target.code, subtitle: target.shortSubtitle, isSelected: targetCode == target.code) {
                    targetCode = target.code
                }
                .accessibilityIdentifier("locations.move.target.\(target.code)")
            }
            if isSome {
                SheetSectionLabel("SKOs to move")
                ForEach(movableSlots) { slot in
                    someRow(slot)
                }
            }
            CheckboxRow("Remove \(source.code) from these SKOs after moving", isOn: $removeFromSource)
            RadioCard("Warehouse transfer", subtitle: "Reason", isSelected: true) {}
            if let warning = capacityWarning {
                InlineAlert(tone: .warn, title: warning)
            }
        } footer: {
            AikuButton(primaryTitle) {
                if let targetCode {
                    onMove(targetCode, plannedQuantities, removeFromSource, !isSome)
                }
            }
            .disabled(targetCode == nil || plannedUnits == 0)
            .accessibilityIdentifier("locations.move.confirm")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
        .task(id: query) {
            guard isRemote else { return }
            try? await Task.sleep(for: .milliseconds(300))
            guard !Task.isCancelled else { return }
            await searchTargets(query)
        }
        .fullScreenCover(isPresented: $isScanning) {
            LocationsScanCover(title: "Scan target location") {
                isScanning = false
            } onScan: {
                isScanning = false
                if let first = warehouse.moveTargets(from: source.code, query: "").first {
                    query = first.code
                    targetCode = first.code
                }
            }
        }
    }

    private var primaryTitle: String {
        guard let targetCode else { return "Choose a target location" }
        if isSome {
            return "Move \(LocationsFormat.units(plannedUnits)) to \(targetCode)"
        }
        return "Move all to \(targetCode)"
    }

    private var capacityWarning: String? {
        guard let targetCode, let target = warehouse.location(code: targetCode) else { return nil }
        let weight = warehouse.movedWeight(from: source.code, quantities: plannedQuantities)
        let volume = warehouse.movedVolume(from: source.code, quantities: plannedQuantities)
        if target.exceedsWeight(adding: weight), let max = target.maxWeightKg {
            return "Over the \(LocationsFormat.number(max)) kg limit of \(target.code) (\(LocationsFormat.number(target.usedWeightKg + weight)) kg)"
        }
        if target.exceedsVolume(adding: volume), let max = target.maxVolumeM3 {
            return "Over the \(LocationsFormat.number(max)) m³ limit of \(target.code) (\(LocationsFormat.number(target.usedVolumeM3 + volume)) m³)"
        }
        return nil
    }

    private func someRow(_ slot: LocationsSlot) -> some View {
        let isPicked = picked.contains(slot.skoReference)
        return VStack(alignment: .leading, spacing: 8) {
            CheckboxRow(
                slot.skoReference,
                subtitle: "\(slot.name) · \(slot.quantity.locationsGrouped) in location",
                isOn: Binding(
                    get: { isPicked },
                    set: { isOn in
                        if isOn {
                            picked.insert(slot.skoReference)
                        } else {
                            picked.remove(slot.skoReference)
                        }
                    }
                )
            )
            .accessibilityIdentifier("locations.move.sko.\(slot.skoReference)")
            if isPicked {
                QuantityStepper(
                    value: Binding(
                        get: { min(max(quantities[slot.skoReference] ?? slot.quantity, 1), slot.quantity) },
                        set: { quantities[slot.skoReference] = $0 }
                    ),
                    range: 1...max(slot.quantity, 1)
                )
            }
        }
    }
}
