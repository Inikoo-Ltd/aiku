import SwiftUI

struct LocationsAuditSheet: View {
    let location: LocationsLocation
    var onlySko: String?
    let onSave: ([LocationsAuditCount]) -> Void
    let onCancel: () -> Void
    @State private var counted: [String: Int]
    @State private var reasons: [String: LocationsAuditReason] = [:]
    @State private var note = ""

    init(
        location: LocationsLocation,
        onlySko: String? = nil,
        onSave: @escaping ([LocationsAuditCount]) -> Void,
        onCancel: @escaping () -> Void
    ) {
        self.location = location
        self.onlySko = onlySko
        self.onSave = onSave
        self.onCancel = onCancel
        _counted = State(initialValue: Dictionary(uniqueKeysWithValues: location.slots.map { ($0.skoReference, max($0.quantity, 0)) }))
    }

    private var slots: [LocationsSlot] {
        guard let onlySko else { return location.slots }
        return location.slots.filter { $0.skoReference == onlySko }
    }

    private func change(for slot: LocationsSlot) -> Int {
        (counted[slot.skoReference] ?? slot.quantity) - max(slot.quantity, 0)
    }

    private var missingReasons: Int {
        slots.filter { slot in
            let difference = change(for: slot)
            guard difference != 0 else { return false }
            guard let reason = reasons[slot.skoReference] else { return true }
            return !LocationsAuditReason.options(forChange: difference).contains(reason)
        }.count
    }

    var body: some View {
        SheetScaffold(
            title: onlySko.map { "Audit \($0) in \(location.code)" } ?? "Audit \(location.code)",
            subtitle: onlySko == nil ? "Count every SKO in this location" : "Count this SKO in the location"
        ) {
            Card {
                ForEach(Array(slots.enumerated()), id: \.element.id) { index, slot in
                    if index > 0 {
                        RowDivider()
                    }
                    row(slot)
                }
            }
            HelperText("Each difference needs a reason: decreases such as Count shortage or Damaged stock, increases such as Count gain (stock found). A note is optional.")
            AikuTextField("Note (optional)", text: $note, placeholder: "Broken in box")
        } footer: {
            AikuButton(missingReasons > 0 ? "Choose a reason for each difference" : "Save audit") {
                onSave(slots.map {
                    LocationsAuditCount(
                        skoReference: $0.skoReference,
                        counted: counted[$0.skoReference] ?? $0.quantity,
                        reason: reasons[$0.skoReference],
                        note: note.trimmingCharacters(in: .whitespacesAndNewlines)
                    )
                })
            }
            .disabled(missingReasons > 0)
            .accessibilityIdentifier("locations.audit.save")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }

    private func row(_ slot: LocationsSlot) -> some View {
        let difference = change(for: slot)
        let options = LocationsAuditReason.options(forChange: difference)
        return VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 12) {
                LocationsSlotThumb(reference: slot.skoReference)
                VStack(alignment: .leading, spacing: 3) {
                    Text(slot.skoReference)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text(difference == 0 ? "was \(slot.quantity)" : "was \(slot.quantity) · \(difference.signedChange)")
                        .font(.aiku(13))
                        .foregroundStyle(difference == 0 ? AikuColor.text3 : (difference < 0 ? AikuColor.danger : AikuColor.success))
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                counter(slot)
            }
            if !options.isEmpty {
                FlowLayout(spacing: 8, lineSpacing: 8) {
                    ForEach(options, id: \.self) { reason in
                        FilterChip(reason.title, isSelected: reasons[slot.skoReference] == reason) {
                            reasons[slot.skoReference] = reason
                        }
                    }
                }
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .frame(minHeight: 60)
    }

    private func counter(_ slot: LocationsSlot) -> some View {
        let value = counted[slot.skoReference] ?? slot.quantity
        return HStack(spacing: 0) {
            stepButton("−", label: "Decrease \(slot.skoReference)", isEnabled: value > 0) {
                counted[slot.skoReference] = max(value - 1, 0)
            }
            Text("\(value)")
                .font(.aiku(16, .bold))
                .foregroundStyle(AikuColor.text)
                .monospacedDigit()
                .lineLimit(1)
                .minimumScaleFactor(0.7)
                .frame(minWidth: 40)
                .accessibilityLabel("\(slot.skoReference) counted \(value)")
            stepButton("+", label: "Increase \(slot.skoReference)", isEnabled: true) {
                counted[slot.skoReference] = value + 1
            }
        }
        .frame(height: 44)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.borderStrong))
    }

    private func stepButton(_ symbol: String, label: String, isEnabled: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(symbol)
                .font(.aiku(20))
                .foregroundStyle(isEnabled ? AikuColor.text2 : AikuColor.faint)
                .frame(width: 40, height: 44)
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .disabled(!isEnabled)
        .accessibilityLabel(label)
    }
}
