import SwiftUI

struct GoodsOutAddShipmentView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var selected: String?
    @State private var activeSheet: GoodsOutNoteSheet?
    private let store = GoodsOutStore.shared

    var body: some View {
        if store.isRemote {
            GoodsOutRemoteAddShipmentView(reference: reference)
        } else {
            sampleBody
        }
    }

    @ViewBuilder
    private var sampleBody: some View {
        if let note = store.note(reference) {
            AppScreen(title: "Add shipment", kicker: L10n.format("Delivery note %@ · %@", note.reference, note.state.title)) {
                GoodsOutStateStepper(state: note.state)
                Card {
                    ListRow("Delivery address", subtitle: note.deliveryAddress.labelLine(customer: note.customer), icon: .locations, action: note.canEditAddress ? { activeSheet = .editAddress } : nil) {
                        if note.canEditAddress {
                            GoodsOutLinkLabel("Edit")
                        }
                    }
                }
                GoodsOutSectionTitle(title: "API powered shippers")
                VStack(spacing: 8) {
                    ForEach(store.shipperOptions) { option in
                        GoodsOutShipperOptionRow(option: option, icon: .deliveryNote, isSelected: selectedID(note) == option.id) {
                            selected = option.id
                        }
                    }
                }
                .accessibilityElement(children: .contain)
                .accessibilityLabel("Shipping options")
                if !note.parcels.isEmpty {
                    GoodsOutSectionTitle(title: "Parcels")
                    GoodsOutParcelsCard(parcels: note.parcels)
                }
                HelperText("If shipping rules lock a shipper, it is pre-selected and changing it asks “Override the locked shipper?”. If the customer chose the shipper, customer services must change it.")
            } footer: {
                footer(note)
            }
            .aikuSheet(item: $activeSheet) { sheet in
                GoodsOutNoteSheetView(sheet: sheet, reference: note.reference, present: { activeSheet = $0 }, close: { activeSheet = nil })
            }
        }
    }

    private func selectedID(_ note: GoodsOutDeliveryNote) -> String? {
        selected ?? note.shipper.flatMap { shipper in store.shipperOptions.first { $0.id == shipper.rawValue }?.id } ?? store.shipperOptions.first?.id
    }

    @ViewBuilder
    private func footer(_ note: GoodsOutDeliveryNote) -> some View {
        if note.state != .packed {
            GoodsOutFooterStatus(title: "Not packed yet", detail: "Shipments are added once the delivery note is packed.")
        } else if !note.needsShipment {
            GoodsOutFooterStatus(title: "Collection", detail: "The customer collects it, so there is no shipment.")
        } else {
            AikuButton("Add shipment · create label", icon: .deliveryNote) {
                guard let option = store.shipperOptions.first(where: { $0.id == selectedID(note) }) else { return }
                let previous = store.addShipment(note.reference, shipper: option)
                let tracking = store.note(note.reference)?.shipments.last?.tracking ?? ""
                GoodsOutFeedback.done(L10n.format("%@ shipment added · %@", option.title, tracking), previous: previous, toasts: toasts)
                if previous != nil {
                    router.back()
                }
            }
            .accessibilityIdentifier("goodsOut.createLabel")
        }
    }
}

struct GoodsOutShipperOptionRow: View {
    let option: GoodsOutShipperOption
    var icon: AppIcon?
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 12) {
                if let icon {
                    IconTile(icon, tone: isSelected ? .info : .neutral)
                }
                VStack(alignment: .leading, spacing: 2) {
                    Text(option.title)
                        .font(.aiku(15, icon == nil ? .semibold : .bold))
                        .foregroundStyle(AikuColor.text)
                    Text(option.detail)
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text3)
                        .fixedSize(horizontal: false, vertical: true)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                if isSelected {
                    AppIcon.check.image
                        .font(.system(size: 18, weight: .semibold))
                        .foregroundStyle(AikuColor.link)
                        .accessibilityHidden(true)
                }
            }
            .padding(.horizontal, 14)
            .padding(.vertical, icon == nil ? 8 : 10)
            .frame(maxWidth: .infinity, minHeight: icon == nil ? 56 : 64, alignment: .leading)
            .background(isSelected ? AikuColor.soft : AikuColor.surface, in: RoundedRectangle(cornerRadius: 10))
            .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(isSelected ? AikuColor.primary : AikuColor.border, lineWidth: isSelected ? 2 : 1))
            .contentShape(RoundedRectangle(cornerRadius: 10))
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSelected ? [.isSelected] : [])
    }
}

struct GoodsOutRemoteAddShipmentView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var list: GoodsOutShipperList?
    @State private var loadProblem: String?
    @State private var chosenID: Int?
    @State private var tracking = ""
    @State private var search = ""
    @State private var trackingError: String?
    @State private var problem: String?
    @State private var followUp: GoodsOutShipmentFollowUp?
    private let store = GoodsOutStore.shared

    private static let searchFrom = 7

    var body: some View {
        Group {
            if let note = store.note(reference), note.isDetailed {
                screen(note)
            } else if let error = store.noteError(reference: reference) {
                AppScreen(title: "Add shipment", kicker: L10n.format("Delivery note %@", reference)) {
                    GoodsOutLoadProblem(title: L10n.format("Couldn’t load delivery note %@", reference), message: error) {
                        Task { await store.loadNote(reference: reference) }
                    }
                }
            } else {
                AppScreen(title: "Add shipment", kicker: L10n.format("Delivery note %@", reference)) {
                    GoodsOutSkeletonCard(rows: 3)
                }
                .task { await store.loadNote(reference: reference) }
            }
        }
        .task(id: reference) { await loadShippers() }
    }

    private func selected(_ list: GoodsOutShipperList) -> GoodsOutShipperChoice? {
        let shown = list.apiShippers + list.manualShippers
        return shown.first { $0.id == chosenID } ?? list.defaultChoice()
    }

    private func screen(_ note: GoodsOutDeliveryNote) -> some View {
        let chosen = list.flatMap(selected)
        return AppScreen(title: "Add shipment", kicker: L10n.format("Delivery note %@ · %@", note.reference, note.remote?.stateLabel ?? note.state.title)) {
            GoodsOutStateStepper(state: note.state)
            Card {
                ListRow("Delivery address", subtitle: note.addressText ?? "No delivery address", icon: .locations)
            }
            if let notice = list?.lockNotice {
                InlineAlert(tone: .info, icon: .lock, title: notice.title, message: notice.message)
            }
            shippers(chosen: chosen)
            if let chosen, !chosen.isAPI {
                AikuTextField(
                    chosen.needsTracking ? "Tracking number" : "Tracking number (optional)",
                    text: $tracking,
                    error: trackingError
                )
                .onChange(of: tracking) { _, _ in trackingError = nil }
            }
            if let problem {
                InlineAlert(tone: .danger, title: problem)
            }
            HelperText(chosen?.isAPI == false
                ? "eBay and Shopify orders need a tracking number when the shipper has no API. An API shipper creates the label and tracking number itself."
                : "A locked or preferred shipper is chosen for you. Choosing another one asks a supervisor to confirm. If the customer chose the shipper, customer services must change it.")
        } footer: {
            if note.allows(.addShipment), let chosen {
                AikuButton(chosen.isAPI ? "Add shipment · create label" : "Add shipment", icon: .deliveryNote, isLoading: store.isWorking(on: reference)) {
                    add(chosen)
                }
                .accessibilityIdentifier("goodsOut.createLabel")
            } else if !note.allows(.addShipment) {
                GoodsOutFooterStatus(title: "No shipment possible", detail: note.action(.addShipment)?.disabledReason ?? "Shipments can’t be added to this delivery note now.")
            }
        }
        .aikuConfirm(
            "Use another shipper?",
            isPresented: Binding(get: { if case .confirmOverride = followUp { return true }; return false }, set: { if !$0, case .confirmOverride = followUp { followUp = nil } }),
            message: overrideMessage,
            confirmTitle: overrideConfirmTitle
        ) {
            if case .confirmOverride(_, let request) = followUp {
                followUp = nil
                submit(request.overridingLock)
            }
        }
        .aikuSheet(item: Binding(get: { addressRetry }, set: { if $0 == nil { followUp = nil } })) { retry in
            GoodsOutRemoteAddressSheet(note: note, retry: retry, onRetried: { result, request in
                followUp = nil
                handle(result, after: request)
            }, close: { followUp = nil })
        }
    }

    @ViewBuilder
    private func shippers(chosen: GoodsOutShipperChoice?) -> some View {
        if let list {
            if list.apiShippers.isEmpty, list.manualShippers.isEmpty {
                EmptyState(icon: .deliveryNote, title: "No shipper", message: "No shipper is available for this delivery note.")
            }
            if !list.apiShippers.isEmpty {
                GoodsOutSectionTitle(title: "API powered shippers")
                choices(list.apiShippers, list: list, chosen: chosen, icon: .deliveryNote)
            }
            if !list.manualShippers.isEmpty {
                GoodsOutSectionTitle(title: "Other shippers")
                if list.manualShippers.count >= Self.searchFrom {
                    SearchField(text: $search, placeholder: L10n.text("Select shipping"))
                }
                let query = search.trimmingCharacters(in: .whitespaces)
                choices(
                    list.manualShippers.filter { query.isEmpty || $0.name.localizedCaseInsensitiveContains(query) || $0.code.localizedCaseInsensitiveContains(query) || $0.id == chosen?.id },
                    list: list,
                    chosen: chosen,
                    icon: nil
                )
            }
        } else if let loadProblem {
            GoodsOutLoadProblem(title: "Couldn’t load the shippers", message: loadProblem) {
                Task { await loadShippers() }
            }
        } else {
            GoodsOutSkeletonCard(rows: 3)
        }
    }

    private func choices(_ shippers: [GoodsOutShipperChoice], list: GoodsOutShipperList, chosen: GoodsOutShipperChoice?, icon: AppIcon?) -> some View {
        VStack(spacing: 8) {
            ForEach(shippers) { shipper in
                var option = shipper.option
                if let badge = list.badge(for: shipper) {
                    option = GoodsOutShipperOption(id: option.id, title: "\(option.title) · \(badge)", detail: option.detail, hasAPI: option.hasAPI)
                }
                return GoodsOutShipperOptionRow(option: option, icon: icon ?? .deliveryNote, isSelected: chosen?.id == shipper.id) {
                    chosenID = shipper.id
                    trackingError = nil
                }
            }
        }
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Shipping options")
    }

    private var addressRetry: GoodsOutAddressRetry? {
        if case .fixAddress(let message, let shipperID, let overrideLock) = followUp {
            return GoodsOutAddressRetry(message: message, shipperID: shipperID, overrideLock: overrideLock)
        }
        return nil
    }

    private var overrideMessage: String {
        if case .confirmOverride(let message, _) = followUp { return message }
        return ""
    }

    private var overrideConfirmTitle: String {
        guard case .confirmOverride(_, let request) = followUp else { return "" }
        guard let name = list?.shippers.first(where: { $0.id == request.shipperID })?.name else { return L10n.text("Use this shipper") }
        return L10n.format("Use %@", name)
    }

    private func loadShippers() async {
        loadProblem = nil
        switch await store.shipperList(for: reference) {
        case .success(let loaded):
            list = loaded
        case .failure(let failure):
            if !failure.message.isEmpty {
                loadProblem = failure.message
            }
        }
    }

    private func add(_ shipper: GoodsOutShipperChoice) {
        let typed = tracking.trimmingCharacters(in: .whitespacesAndNewlines)
        if !shipper.isAPI, shipper.needsTracking, typed.isEmpty {
            trackingError = "Enter the tracking number for this shipper."
            return
        }
        submit(.add(shipperID: shipper.id, tracking: shipper.isAPI || typed.isEmpty ? nil : typed, overrideLock: false))
    }

    private func submit(_ request: GoodsOutShipmentRequest) {
        problem = nil
        Task {
            let result = await store.write(request.action, on: reference)
            handle(result, after: request)
        }
    }

    private func handle(_ result: GoodsOutWriteResult, after request: GoodsOutShipmentRequest) {
        if case .failed(let message) = result.outcome {
            if let next = GoodsOutShipmentFollowUp.after(request, errors: result.fieldErrors) {
                followUp = next
                return
            }
            trackingError = result.fieldErrors["tracking"]
            let shown = ["message", "shipper", "sender", "others", "shipper_id"].compactMap { result.fieldErrors[$0] }
            problem = shown.isEmpty ? (trackingError == nil && !message.isEmpty ? message : nil) : shown.joined(separator: " ")
            return
        }
        GoodsOutWriteFeedback.show(result, fallback: "Shipment added", toasts: toasts)
        router.back()
    }
}
