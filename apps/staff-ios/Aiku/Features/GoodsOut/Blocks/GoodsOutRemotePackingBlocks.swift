import SwiftUI

@MainActor
enum GoodsOutWriteFeedback {
    @discardableResult
    static func show(_ result: GoodsOutWriteResult, fallback: String, toasts: ToastCenter, showsFailure: Bool = true) -> Bool {
        if !showsFailure, !result.isDone, case .failed = result.outcome { return false }
        let shown = GoodsOutRemoteFeedback.show(result.outcome, fallback: fallback, toasts: toasts)
        if shown, let warning = result.warning {
            toasts.show(warning, tone: .warn)
        }
        return shown
    }

    static func run(
        _ action: GoodsOutNoteAction,
        on reference: String,
        fallback: String,
        toasts: ToastCenter,
        onDone: @escaping @MainActor (GoodsOutWriteResult) -> Void = { _ in }
    ) {
        Task {
            let result = await GoodsOutStore.shared.write(action, on: reference)
            if show(result, fallback: fallback, toasts: toasts) {
                onDone(result)
            }
        }
    }
}

struct GoodsOutRemotePackingFooter: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    @State private var confirming: GoodsOutAvailableAction?
    private let store = GoodsOutStore.shared

    var body: some View {
        let items = note.remoteFooterItems
        Group {
            if items.isEmpty {
                let status = note.remoteFooterStatus
                GoodsOutFooterStatus(title: status.title, detail: status.detail)
            } else {
                ForEach(items, id: \.self) { item in
                    switch item {
                    case .notice(let action):
                        GoodsOutFooterStatus(title: action.label, detail: action.disabledReason)
                    case .button(let action, let isPrimary):
                        AikuButton(
                            action.label,
                            icon: icon(for: action.key),
                            kind: isPrimary ? .primary : .tertiary,
                            isLoading: isPrimary && store.isWorking(on: note.reference)
                        ) {
                            handle(action)
                        }
                        .accessibilityIdentifier("goodsOut.footer.\(action.key)")
                    }
                }
            }
        }
        .aikuConfirm(
            confirming.map { "\($0.label)?" } ?? "",
            isPresented: Binding(get: { confirming != nil }, set: { if !$0 { confirming = nil } }),
            message: confirming.map(effect) ?? "",
            confirmTitle: confirming?.label ?? ""
        ) {
            if let action = confirming {
                confirming = nil
                run(action)
            }
        }
    }

    private func icon(for key: String) -> AppIcon? {
        switch GoodsOutActionKey(rawValue: key) {
        case .startPacking: .sko
        case .setAsPacked: .packed
        case .addShipment, .getExternalShipment, .finaliseAndDispatch, .dispatch: .deliveryNote
        case .finalise: .check
        case .takeOver: .lock
        default: nil
        }
    }

    private func effect(_ action: GoodsOutAvailableAction) -> String {
        switch GoodsOutActionKey(rawValue: action.key) {
        case .finalise: "The order is invoiced and the customer is emailed."
        case .dispatch: note.isCollection ? "The customer has picked it up." : "The customer is told it has shipped."
        case .finaliseAndDispatch: "The order is invoiced and the customer is told it has shipped."
        default: ""
        }
    }

    private func handle(_ action: GoodsOutAvailableAction) {
        switch GoodsOutActionKey(rawValue: action.key) {
        case .addShipment:
            router.open(GoodsOutRoute.addShipment(reference: note.reference))
        case .finalise, .dispatch, .finaliseAndDispatch:
            confirming = action
        default:
            run(action)
        }
    }

    private func run(_ action: GoodsOutAvailableAction) {
        let reference = note.reference
        switch GoodsOutActionKey(rawValue: action.key) {
        case .startPacking:
            GoodsOutWriteFeedback.run(.startPacking, on: reference, fallback: "Packing started for \(reference)", toasts: toasts)
        case .setAsPacked:
            GoodsOutWriteFeedback.run(.setAsPacked, on: reference, fallback: "\(reference) set as packed", toasts: toasts) { _ in
                if let packed = store.note(reference), packed.state == .packed, packed.shipments.isEmpty, packed.allows(.addShipment) {
                    router.open(GoodsOutRoute.addShipment(reference: reference))
                }
            }
        case .getExternalShipment:
            GoodsOutWriteFeedback.run(.externalShipment, on: reference, fallback: "Shipment added", toasts: toasts)
        case .finalise:
            GoodsOutWriteFeedback.run(.finalise, on: reference, fallback: "Delivery note finalised", toasts: toasts)
        case .dispatch:
            GoodsOutWriteFeedback.run(.dispatch, on: reference, fallback: "Delivery note dispatched", toasts: toasts)
        case .finaliseAndDispatch:
            GoodsOutWriteFeedback.run(.finaliseAndDispatch, on: reference, fallback: "Delivery note dispatched", toasts: toasts)
        case .takeOver:
            GoodsOutWriteFeedback.run(.takeOver, on: reference, fallback: "You can work on \(reference) now", toasts: toasts)
        default:
            toasts.show("\(action.label) isn’t available in the app yet", tone: .warn)
        }
    }
}

struct GoodsOutInsertsBlock: View {
    let note: GoodsOutDeliveryNote
    let opener: GoodsOutDocumentOpener
    @Environment(ToastCenter.self) private var toasts
    private let store = GoodsOutStore.shared

    private var details: GoodsOutNotePackingDetails? { note.packingDetails }

    var body: some View {
        if let details, details.showsInserts, !note.leaflets.isEmpty || details.packagingName != nil {
            GoodsOutSectionTitle(title: "Inserts", detail: details.insertsLabel)
            Card {
                if let packaging = details.packagingName {
                    ListRow(packaging, subtitle: "Packaging", icon: .sko, iconTone: .neutral)
                }
                ForEach(Array(note.leaflets.enumerated()), id: \.element.id) { index, leaflet in
                    if index > 0 || details.packagingName != nil {
                        RowDivider()
                    }
                    leafletRow(leaflet, canOpen: leaflet.canOpenPdf && details.isEditable)
                }
            }
            if details.canPrintAllInserts, details.isEditable {
                AikuButton("Print every insert", icon: .printer, kind: .secondary, isLoading: store.isWorking(on: note.reference)) {
                    GoodsOutWriteFeedback.run(.printInserts, on: note.reference, fallback: "Inserts sent to the printer", toasts: toasts)
                }
                .accessibilityIdentifier("goodsOut.printInserts")
            }
        }
    }

    private func leafletRow(_ leaflet: GoodsOutLeaflet, canOpen: Bool) -> some View {
        let document = leaflet.apiID.map(GoodsOutDocument.insert)
        return ListRow(
            leaflet.title,
            subtitle: leaflet.copiesText,
            chips: [leaflet.chip],
            action: canOpen ? { document.map { opener.open($0, of: note.reference, toasts: toasts) } } : nil
        ) {
            IconTile(.printer, tone: leaflet.isPrinted ? .success : .warn)
        } trailing: {
            if canOpen, let document {
                GoodsOutLinkLabel(opener.isOpening(document) ? "Opening…" : "Open PDF")
            }
        }
    }
}

struct GoodsOutBoxPackingListBlock: View {
    let note: GoodsOutDeliveryNote
    let opener: GoodsOutDocumentOpener
    @Environment(ToastCenter.self) private var toasts

    var body: some View {
        if let details = note.packingDetails, details.hasBoxPackingList {
            if let missing = details.missingBoxesMessage, note.state != .finalised, note.state != .dispatched {
                InlineAlert(tone: .warn, icon: .warning, title: missing)
            }
            AikuButton(opener.isOpening(.packingList) ? "Opening…" : "Packing list PDF", icon: .document, kind: .secondary, isLoading: opener.isOpening(.packingList)) {
                opener.open(.packingList, of: note.reference, toasts: toasts)
            }
        }
    }
}

struct GoodsOutRemoteShipmentsBlock: View {
    let note: GoodsOutDeliveryNote
    let opener: GoodsOutDocumentOpener
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @Environment(\.openURL) private var openURL
    @State private var deleting: GoodsOutShipment?
    private let store = GoodsOutStore.shared

    var body: some View {
        if !note.shipments.isEmpty {
            GoodsOutSectionTitle(title: "Shipments")
            Card {
                ForEach(Array(note.shipments.enumerated()), id: \.element.id) { index, shipment in
                    if index > 0 {
                        RowDivider()
                    }
                    shipmentRow(shipment)
                }
            }
            if note.addAnotherShipment != nil {
                AikuButton("Add another shipment", icon: .deliveryNote, kind: .tertiary) {
                    router.open(GoodsOutRoute.addShipment(reference: note.reference))
                }
                .accessibilityIdentifier("goodsOut.addAnotherShipment")
            }
        }
    }

    private func shipmentRow(_ shipment: GoodsOutShipment) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack(spacing: 12) {
                IconTile(.deliveryNote, tone: .success)
                VStack(alignment: .leading, spacing: 2) {
                    Text(shipment.shipper)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    if shipment.trackingNumbers.isEmpty {
                        Text("No tracking number")
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                    }
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            ForEach(Array(shipment.trackingNumbers.enumerated()), id: \.offset) { index, tracking in
                if let url = shipment.trackingURL(at: index) {
                    Button {
                        openURL(url)
                    } label: {
                        Text("Tracking \(tracking)")
                            .font(.aiku(14, .semibold))
                            .underline()
                            .foregroundStyle(AikuColor.link)
                            .frame(minHeight: 44)
                    }
                    .buttonStyle(.plain)
                } else {
                    Text("Tracking \(tracking)")
                        .font(.aiku(14))
                        .foregroundStyle(AikuColor.text2)
                        .textSelection(.enabled)
                        .frame(minHeight: 32)
                }
            }
            HStack(spacing: 16) {
                switch shipment.label {
                case .download:
                    let document = GoodsOutDocument.label(shipmentID: shipment.id)
                    action(opener.isOpening(document) ? "Opening…" : "Label PDF") {
                        opener.open(document, of: note.reference, toasts: toasts)
                    }
                case .external(let url):
                    action("Open label") { openURL(url) }
                case .none:
                    EmptyView()
                }
                if note.packingDetails?.canPrintLabels == true, shipment.isPrintable {
                    action("Print label") {
                        GoodsOutWriteFeedback.run(.printLabel(shipment.id), on: note.reference, fallback: "Label sent to the printer", toasts: toasts)
                    }
                }
                if shipment.canDelete {
                    action("Delete", tone: .danger) { deleting = shipment }
                        .accessibilityLabel("Delete the \(shipment.shipper) shipment")
                }
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
        .aikuConfirm(
            "Delete the \(deleting?.shipper ?? "") shipment?",
            isPresented: Binding(get: { deleting?.id == shipment.id }, set: { if !$0 { deleting = nil } }),
            message: "This can’t be undone. The carrier is not told: if the label was already booked, cancel it with the carrier yourself.",
            confirmTitle: "Delete shipment",
            isDestructive: true
        ) {
            deleting = nil
            GoodsOutWriteFeedback.run(.deleteShipment(shipment.id), on: note.reference, fallback: "Shipment deleted", toasts: toasts)
        }
    }

    private func action(_ title: String, tone: Tone = .info, run: @escaping () -> Void) -> some View {
        Button(action: run) {
            GoodsOutLinkLabel(title, tone: tone)
                .frame(minWidth: 44, minHeight: 44)
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
    }
}

struct GoodsOutRemoteParcelsEditor: View {
    let note: GoodsOutDeliveryNote
    @Environment(ToastCenter.self) private var toasts
    @State private var drafts: [GoodsOutParcelDraft] = []
    @State private var nextID = 0
    @State private var problem: String?
    @State private var loadedFrom: [GoodsOutParcel]?
    private let store = GoodsOutStore.shared

    private var usesBoxes: Bool { note.packingDetails?.hasBoxPackingList == true }

    var body: some View {
        GoodsOutSectionTitle(title: "Parcels (\(drafts.count))", detail: usesBoxes ? "kg · L × W × H cm · boxes" : "kg · L × W × H cm")
        Card {
            ForEach($drafts) { $draft in
                let index = drafts.firstIndex { $0.id == draft.id } ?? 0
                GoodsOutParcelDraftRow(title: "Parcel \(index + 1)", draft: $draft, usesBoxes: usesBoxes) {
                    drafts.removeAll { $0.id == draft.id }
                }
                RowDivider()
            }
            Button {
                drafts.append(newDraft())
            } label: {
                Text(drafts.isEmpty ? "+ Add a parcel" : "+ Add another parcel")
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.link)
                    .frame(maxWidth: .infinity, minHeight: 48)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("goodsOut.addParcel")
        }
        if let problem {
            InlineAlert(tone: .danger, title: problem)
        }
        if isChanged {
            AikuButton("Save parcels", icon: .check, kind: .secondary, isLoading: store.isWorking(on: note.reference)) {
                save()
            }
            .accessibilityIdentifier("goodsOut.saveParcels")
        }
        Color.clear
            .frame(height: 0)
            .onAppear { load(note.parcels) }
            .onChange(of: note.parcels) { _, parcels in load(parcels) }
    }

    private var isChanged: Bool {
        drafts != (loadedFrom ?? []).enumerated().map { GoodsOutParcelDraft(id: $0.offset, parcel: $0.element) }
    }

    private func load(_ parcels: [GoodsOutParcel]) {
        guard loadedFrom != parcels else { return }
        loadedFrom = parcels
        drafts = parcels.enumerated().map { GoodsOutParcelDraft(id: $0.offset, parcel: $0.element) }
        nextID = parcels.count
        problem = nil
    }

    private func newDraft() -> GoodsOutParcelDraft {
        defer { nextID += 1 }
        let used = drafts.compactMap { GoodsOutParcelDraft.number($0.weight) }.reduce(0, +)
        let remaining = GoodsOutWeight.rounded(max(0.1, note.weightKg - used))
        return GoodsOutParcelDraft(id: nextID, weight: drafts.isEmpty || note.weightKg > used ? GoodsOutLinePacking.text(remaining) : "")
    }

    private func save() {
        switch GoodsOutParcelDraft.inputs(drafts) {
        case .failure(let failure):
            problem = failure.message
        case .success(let parcels):
            problem = nil
            Task {
                let result = await store.write(.parcels(parcels), on: note.reference)
                if !GoodsOutWriteFeedback.show(result, fallback: "Parcels saved", toasts: toasts, showsFailure: false) {
                    problem = result.fieldErrors.sorted { $0.key < $1.key }.first?.value ?? result.failureMessage
                }
            }
        }
    }
}

private struct GoodsOutParcelDraftRow: View {
    let title: String
    @Binding var draft: GoodsOutParcelDraft
    let usesBoxes: Bool
    let remove: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Text(title)
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                if let problem = draft.sizeProblem ?? draft.weightProblem {
                    Text(problem)
                        .font(.aiku(12, .semibold))
                        .foregroundStyle(AikuColor.warn)
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                }
                Spacer(minLength: 8)
                IconButton(.trash, label: "Remove \(title)", tint: AikuColor.danger, action: remove)
            }
            HStack(spacing: 6) {
                field($draft.weight, placeholder: "kg", label: "weight in kilograms", keyboard: .decimalPad)
                field($draft.length, placeholder: "L", label: "length in centimetres", keyboard: .decimalPad)
                field($draft.width, placeholder: "W", label: "width in centimetres", keyboard: .decimalPad)
                field($draft.height, placeholder: "H", label: "height in centimetres", keyboard: .decimalPad)
                if usesBoxes {
                    field($draft.boxes, placeholder: "Boxes", label: "number of boxes", keyboard: .numberPad)
                }
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 10)
    }

    private func field(_ text: Binding<String>, placeholder: String, label: String, keyboard: UIKeyboardType) -> some View {
        TextField("", text: text, prompt: Text(placeholder).foregroundStyle(AikuColor.faint))
            .font(.aiku(14, .semibold))
            .foregroundStyle(AikuColor.text)
            .multilineTextAlignment(.center)
            .keyboardType(keyboard)
            .monospacedDigit()
            .frame(maxWidth: .infinity, minHeight: 44)
            .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
            .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(text.wrappedValue.isEmpty && placeholder != "Boxes" ? AikuColor.warn : AikuColor.borderStrong))
            .accessibilityLabel("\(title) \(label)")
    }
}

struct GoodsOutPackScanScreen: View {
    let reference: String
    let close: () -> Void
    @State private var feedback: (text: String, tone: Tone)?
    @State private var isSending = false
    private let store = GoodsOutStore.shared

    var body: some View {
        DispatchCameraScan(kicker: "Delivery note \(reference)", title: "Scan to pack", feedback: feedback, onClose: close, onCode: scan) {
            if let note = store.note(reference), let next = note.nextLineToPack {
                VStack(alignment: .leading, spacing: 4) {
                    Text(next.sko)
                        .font(.aiku(17, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text("\(next.packing?.toPackText ?? String(next.packRemaining)) left to pack · \(note.packedProgress)")
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            } else {
                Text("Every line is packed")
                    .font(.aiku(15, .semibold))
                    .foregroundStyle(AikuColor.success)
                    .frame(maxWidth: .infinity, alignment: .leading)
            }
        }
    }

    private func scan(_ code: String) {
        guard !isSending else { return }
        isSending = true
        Task {
            let result = await store.scanToPack(reference, barcode: code)
            isSending = false
            switch result {
            case .packed(let sko, let message, let lineDone):
                feedback = (message ?? (lineDone ? "\(sko) · line packed" : "\(sko) +1"), .success)
            case .refused(let message):
                feedback = (message, .danger)
            }
        }
    }
}

struct GoodsOutNotInTheAppScreen: View {
    let title: String
    let message: String

    var body: some View {
        AppScreen(title: title, kicker: "Goods out") {
            EmptyState(icon: .info, title: "Not in the app yet", message: message)
        }
    }
}
