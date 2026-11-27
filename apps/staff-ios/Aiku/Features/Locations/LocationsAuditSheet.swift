import SwiftUI

struct LocationsAuditSheet: View {
    let location: LocationsLocation
    var onlySko: String?
    var onFindSko: ((String) async -> LocationsFoundSko)?
    let onSave: ([LocationsAuditCount]) -> Void
    let onCancel: () -> Void

    init(
        location: LocationsLocation,
        onlySko: String? = nil,
        onFindSko: ((String) async -> LocationsFoundSko)? = nil,
        onSave: @escaping ([LocationsAuditCount]) -> Void,
        onCancel: @escaping () -> Void
    ) {
        self.location = location
        self.onlySko = onlySko
        self.onFindSko = onFindSko
        self.onSave = onSave
        self.onCancel = onCancel
    }

    var body: some View {
        if let onlySko {
            let slots = location.slots.filter { $0.skoReference == onlySko }
            LocationsAuditReviewSheet(
                slots: slots,
                counted: Dictionary(uniqueKeysWithValues: slots.map { ($0.skoReference, max($0.quantity, 0)) }),
                title: L10n.format("Audit %@ in %@", onlySko, location.code),
                subtitle: "Count this SKO in the location",
                cancelTitle: "Cancel",
                onSave: onSave,
                onCancel: onCancel
            )
        } else {
            LocationsBlindAuditSheet(location: location, onFindSko: onFindSko, onSave: onSave, onCancel: onCancel)
        }
    }
}

private struct LocationsBlindAuditSheet: View {
    let location: LocationsLocation
    let onFindSko: ((String) async -> LocationsFoundSko)?
    let onSave: ([LocationsAuditCount]) -> Void
    let onCancel: () -> Void
    @State private var counted: [String: Double] = [:]
    @State private var editing: String?
    @State private var isSummary = false
    @State private var isScanning = false
    @State private var showsNothingCounted = false
    @State private var findingSko: String?
    @State private var scanProblem: String?
    @State private var lastRefusedCode: String?
    @State private var typedCode = ""
    @State private var cameraAllowed: Bool?

    private var slots: [LocationsSlot] { location.slots }

    private var leftToCount: Int {
        slots.filter { counted[$0.skoReference] == nil }.count
    }

    var body: some View {
        if isSummary {
            LocationsAuditReviewSheet(
                slots: slots.filter { counted[$0.skoReference] != nil },
                counted: counted,
                title: L10n.format("%@: counted vs Aiku", location.code),
                subtitle: "Check each difference, give its reason, then save.",
                cancelTitle: "Back",
                onSave: onSave,
                onCancel: { isSummary = false }
            )
        } else {
            counting
        }
    }

    private var counting: some View {
        SheetScaffold(
            title: L10n.format("Audit %@", location.code),
            subtitle: "Count what is on the shelf. Aiku’s quantities show when you complete."
        ) {
            if !slots.isEmpty {
                Card {
                    ForEach(Array(slots.enumerated()), id: \.element.id) { index, slot in
                        if index > 0 {
                            RowDivider()
                        }
                        countRow(slot)
                    }
                }
            }
            if onFindSko != nil {
                AikuButton("SKO found here (scan)", icon: .plus, kind: .secondary) {
                    isScanning.toggle()
                }
                .accessibilityIdentifier("locations.audit.foundSko")
                if isScanning {
                    scanner
                }
                if let findingSko {
                    Text(L10n.format("Looking up %@…", findingSko))
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                if let scanProblem {
                    FieldError(scanProblem)
                }
            }
        } footer: {
            if showsNothingCounted, counted.isEmpty {
                InlineAlert(tone: .warn, title: "Count at least one SKO first.")
            }
            AikuButton(completeTitle, look: AikuButtonLook(.primary, icon: .check)) {
                complete()
            }
            .accessibilityIdentifier("locations.audit.complete")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
        .hardwareScanner(isEnabled: onFindSko != nil && findingSko == nil) { code in
            find(code)
        }
        .task(id: isScanning) {
            guard isScanning, cameraAllowed == nil else { return }
            cameraAllowed = await CameraPermission.request()
        }
    }

    private var completeTitle: String {
        leftToCount > 0 ? L10n.format("Complete audit · %lld left", leftToCount) : L10n.text("Complete audit")
    }

    @ViewBuilder
    private var scanner: some View {
        if cameraAllowed == true, QRCodeScanner.isAvailable {
            QRCodeScanner(symbologies: QRCodeScanner.warehouseSymbologies) { code in
                find(code)
            }
            .frame(height: 200)
            .clipShape(RoundedRectangle(cornerRadius: 12))
            .accessibilityLabel(L10n.text("Point at the item barcode"))
        } else if let cameraAllowed {
            HelperText(cameraAllowed ? "This device can’t scan barcodes. Type the code instead." : "Camera access is off. Allow it in Settings, or type the code.")
        }
        SearchField(text: $typedCode, placeholder: "Barcode or SKO code", onSubmit: {
            lastRefusedCode = nil
            find(typedCode)
        })
        .accessibilityIdentifier("locations.audit.typeCode")
    }

    private func countRow(_ slot: LocationsSlot) -> some View {
        let value = counted[slot.skoReference]
        let isEditing = editing == slot.skoReference
        return VStack(alignment: .leading, spacing: 10) {
            Button {
                editing = isEditing ? nil : slot.skoReference
            } label: {
                HStack(spacing: 12) {
                    LocationsSlotThumb(reference: slot.skoReference)
                    VStack(alignment: .leading, spacing: 3) {
                        Text(slot.skoReference)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                        if !slot.name.isEmpty {
                            Text(slot.name)
                                .font(.aiku(13))
                                .foregroundStyle(AikuColor.text3)
                                .lineLimit(2)
                                .multilineTextAlignment(.leading)
                        }
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                    if let value {
                        StatusChip(L10n.format("Counted %@", quantityText(value)), tone: .success)
                    } else {
                        StatusChip("To count")
                    }
                }
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("locations.audit.row.\(slot.skoReference)")
            if isEditing {
                DecimalQuantityStepper(value: countBinding(slot), valueText: quantityText(value ?? 0))
                    .accessibilityLabel(L10n.format("Counted in %@", slot.skoReference))
                AikuButton(L10n.format("Done: %@", quantityText(value ?? 0)), icon: .check, kind: .secondary) {
                    counted[slot.skoReference] = value ?? 0
                    editing = nil
                }
                .accessibilityIdentifier("locations.audit.done")
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .frame(minHeight: 60)
    }

    private func quantityText(_ value: Double) -> String {
        StockConfirmText.quantityLabel(value, packedIn: nil)
    }

    private func countBinding(_ slot: LocationsSlot) -> Binding<Double> {
        Binding {
            counted[slot.skoReference] ?? 0
        } set: { newValue in
            counted[slot.skoReference] = newValue
            showsNothingCounted = false
        }
    }

    private func complete() {
        guard !counted.isEmpty else {
            showsNothingCounted = true
            return
        }
        editing = nil
        isSummary = true
    }

    private func find(_ code: String) {
        let scanned = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard let onFindSko, findingSko == nil, !scanned.isEmpty, scanned != lastRefusedCode else { return }
        findingSko = scanned
        scanProblem = nil
        Task {
            let found = await onFindSko(scanned)
            findingSko = nil
            switch found {
            case .here(let reference):
                isScanning = false
                typedCode = ""
                lastRefusedCode = nil
                editing = reference
            case .notSko(let code):
                ScanFeedback.shared.rejected()
                lastRefusedCode = code
                scanProblem = L10n.format("%@ is not an SKO", code)
            case .refused(let message):
                ScanFeedback.shared.rejected()
                lastRefusedCode = scanned
                scanProblem = message
            }
        }
    }
}

private struct LocationsAuditReviewSheet: View {
    let slots: [LocationsSlot]
    let title: String
    let subtitle: String
    let cancelTitle: String
    let onSave: ([LocationsAuditCount]) -> Void
    let onCancel: () -> Void
    @State private var counted: [String: Double]
    @State private var reasons: [String: LocationsAuditReason] = [:]
    @State private var note = ""
    @State private var isConfirming = false

    init(
        slots: [LocationsSlot],
        counted: [String: Double],
        title: String,
        subtitle: String,
        cancelTitle: String,
        onSave: @escaping ([LocationsAuditCount]) -> Void,
        onCancel: @escaping () -> Void
    ) {
        self.slots = slots
        self.title = title
        self.subtitle = subtitle
        self.cancelTitle = cancelTitle
        self.onSave = onSave
        self.onCancel = onCancel
        _counted = State(initialValue: counted)
    }

    private func change(for slot: LocationsSlot) -> Double {
        countedValue(slot) - slot.quantity
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
        SheetScaffold(title: title, subtitle: subtitle) {
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
            AikuButton(missingReasons > 0 ? "Choose a reason for each difference" : "Save audit", look: AikuButtonLook(.primary, icon: .check)) {
                isConfirming = true
            }
            .disabled(missingReasons > 0)
            .accessibilityIdentifier("locations.audit.save")
            AikuButton(cancelTitle, kind: .tertiary, action: onCancel)
                .accessibilityIdentifier("locations.audit.cancel")
        }
        .aikuConfirm("Save this count?", isPresented: $isConfirming, message: auditSummary, confirmTitle: "Save audit") {
            save()
        }
    }

    private var auditSummary: String {
        slots.map { slot in
            StockConfirmText.auditLine(slot.skoReference, wasText: slot.quantityText, counted: countedValue(slot), was: slot.quantity, packedIn: nil)
        }
        .joined(separator: "\n")
    }

    private func countedValue(_ slot: LocationsSlot) -> Double {
        counted[slot.skoReference] ?? slot.quantity
    }

    private func countedText(_ slot: LocationsSlot) -> String {
        StockConfirmText.countedText(countedValue(slot), was: slot.quantity, wasText: slot.quantityText, packedIn: nil)
    }

    private func save() {
        onSave(slots.map {
            LocationsAuditCount(
                skoReference: $0.skoReference,
                counted: countedValue($0),
                reason: reasons[$0.skoReference],
                note: note.trimmingCharacters(in: .whitespacesAndNewlines)
            )
        })
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
                    Text(difference == 0 ? L10n.format("was %@", slot.quantityText) : L10n.format("was %@ · %@", slot.quantityText, difference.signedChange))
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
        let value = countedValue(slot)
        let valueText = countedText(slot)
        return HStack(spacing: 0) {
            stepButton("−", label: L10n.format("Decrease %@", slot.skoReference), isEnabled: value > 0) {
                counted[slot.skoReference] = DecimalQuantityStepper.clean(max(value - 1, 0))
            }
            Text(valueText)
                .font(.aiku(16, .bold))
                .foregroundStyle(AikuColor.text)
                .monospacedDigit()
                .lineLimit(1)
                .minimumScaleFactor(0.7)
                .frame(minWidth: 40)
                .accessibilityLabel(L10n.format("%@ counted %@", slot.skoReference, valueText))
            stepButton("+", label: L10n.format("Increase %@", slot.skoReference), isEnabled: true) {
                counted[slot.skoReference] = DecimalQuantityStepper.clean(value + 1)
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
