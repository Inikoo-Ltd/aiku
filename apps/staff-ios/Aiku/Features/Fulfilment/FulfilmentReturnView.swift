import SwiftUI

struct FulfilmentReturnView: View {
    let returnID: Int
    let reference: String
    @Environment(\.apiClient) private var apiClient
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        if let warehouseID = warehouseStore.selected?.id {
            PalletReturnScreen(live: PalletReturnLive(warehouseID: warehouseID, returnID: returnID, apiClient: apiClient), reference: reference)
                .id("\(warehouseID)-\(returnID)")
        } else {
            AppScreen(title: reference, kicker: "Fulfilment return") {
                EmptyState(icon: .goodsOut, title: "Choose a warehouse first")
            }
        }
    }
}

private enum ReturnConfirm: Identifiable, Equatable {
    case start
    case finish
    case dispatch
    case notPickedPallet(lineID: Int, reference: String)
    case deleteShipment(MobileReturnShipment)

    var id: String {
        switch self {
        case .start: "start"
        case .finish: "finish"
        case .dispatch: "dispatch"
        case .notPickedPallet(let id, _): "not-picked-\(id)"
        case .deleteShipment(let shipment): "delete-\(shipment.id)"
        }
    }
}

private enum ReturnSheet: Identifiable, Equatable {
    case palletLine(Int)
    case itemLine(Int)
    case notPickedItem(Int)
    case otherPallet(Int)

    var id: String {
        switch self {
        case .palletLine(let id): "pallet-\(id)"
        case .itemLine(let id): "item-\(id)"
        case .notPickedItem(let id): "not-picked-\(id)"
        case .otherPallet(let id): "other-\(id)"
        }
    }
}

private struct PalletReturnScreen: View {
    @State var live: PalletReturnLive
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @Environment(\.openURL) private var openURL
    @State private var confirm: ReturnConfirm?
    @State private var sheet: ReturnSheet?
    @State private var isScanning = false
    @State private var labelURL: URL?
    @State private var openingLabel: Int?
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        Group {
            if let detail = live.detail {
                loaded(detail)
            } else {
                AppScreen(title: reference, kicker: "Fulfilment return") {
                    if let message = live.loadError {
                        InlineAlert(title: "Couldn’t load \(reference)", message: message)
                        AikuButton("Try again", kind: .secondary) {
                            Task { await live.load() }
                        }
                    } else {
                        SkeletonRow()
                        SkeletonRow()
                        SkeletonRow()
                    }
                }
            }
        }
        .onAppear { Task { await live.load() } }
        .screenRefreshable { await live.load() }
        .onChange(of: live.event) { _, event in
            guard let event else { return }
            switch event {
            case .done(let message, let tone):
                toasts.show(message, tone: tone)
            case .lineFound(let lineID):
                if live.detail?.palletLine(lineID) != nil {
                    sheet = .palletLine(lineID)
                } else if live.detail?.itemLine(lineID) != nil {
                    sheet = .itemLine(lineID)
                }
            }
            live.clearEvent()
        }
        .quickLookPreview($labelURL)
    }

    @ViewBuilder
    private func loaded(_ detail: MobilePalletReturnDetail) -> some View {
        let listens = (detail.canScan || detail.hasLineActions) && detail.state == "picking"
        content(detail)
            .hardwareScanner(isEnabled: listens && sheet == nil && confirm == nil && !isScanning) { code in
                Task { await live.scan(code) }
            }
            .fullScreenCover(isPresented: $isScanning) {
                FulfilmentCodeCamera(title: "Scan to pick", onClose: { isScanning = false }) { code in
                    isScanning = false
                    Task { await live.scan(code) }
                }
            }
            .aikuSheet(item: $sheet) { current in
                sheetView(current, detail: detail)
            }
            .aikuConfirm(
                confirmTitle(detail),
                isPresented: Binding(get: { confirm != nil }, set: { if !$0 { confirm = nil } }),
                message: confirmMessage(detail),
                confirmTitle: confirmButton(detail),
                isDestructive: isDestructiveConfirm
            ) {
                guard let current = confirm else { return }
                confirm = nil
                Task { await run(current) }
            }
    }

    @ViewBuilder
    private func content(_ detail: MobilePalletReturnDetail) -> some View {
        let footer = footerView(detail)
        if let footer {
            AppScreen(title: detail.reference, kicker: PalletReturnText.kicker(detail)) {
                sections(detail)
            } footer: {
                footer
            }
        } else {
            AppScreen(title: detail.reference, kicker: PalletReturnText.kicker(detail)) {
                sections(detail)
            }
        }
    }

    @ViewBuilder
    private func sections(_ detail: MobilePalletReturnDetail) -> some View {
        if let message = live.loadError {
            GoodsOutLoadProblem(title: "Couldn’t refresh this screen", message: message) {
                Task { await live.load() }
            }
        }
        stateBlock(detail)
        notices(detail)
        switch detail.state {
        case "picked":
            pickedContent(detail)
        case "dispatched", "cancel":
            dispatchedContent(detail)
        default:
            if detail.isItems {
                itemsContent(detail)
            } else {
                palletsContent(detail)
            }
        }
    }

    @ViewBuilder
    private func stateBlock(_ detail: MobilePalletReturnDetail) -> some View {
        if detail.state == "cancel" {
            InlineAlert(tone: .danger, icon: .warning, title: detail.stateLabel ?? "Cancelled", message: "This return was cancelled. Nothing is picked or dispatched.")
        } else {
            let index = PalletReturnText.stages.firstIndex(of: detail.state) ?? 0
            FulfilmentProgressSteps(steps: PalletReturnText.stageTitles, currentIndex: detail.state == "dispatched" ? PalletReturnText.stages.count : index)
        }
    }

    @ViewBuilder
    private func notices(_ detail: MobilePalletReturnDetail) -> some View {
        if let unsure = live.unsure {
            InlineAlert(tone: .warn, title: PalletReturnText.unsureTitle, message: PalletReturnText.unsureMessage(unsure)) {
                LinkButton("Try again", size: 13) {
                    Task { await live.retryUnsure() }
                }
                .frame(minHeight: 44, alignment: .leading)
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.return.retryUnsure")
            }
        }
        if detail.inPickingSession {
            InlineAlert(tone: .info, title: "In a picking session", message: "This return is in a picking session; work it on the web.")
        }
        if detail.isCollection {
            InlineAlert(tone: .info, title: "Customer collects", message: "No shipment needed")
        }
    }

    @ViewBuilder
    private func palletsContent(_ detail: MobilePalletReturnDetail) -> some View {
        let next = nextPalletLine(detail)
        let listens = detail.canScan || detail.hasLineActions
        if listens, let next {
            FulfilmentScanCard(
                title: "Scan to pick",
                detail: "Scan the pallet label",
                highlight: next.pallet?.reference ?? "",
                footnote: next.location?.code,
                identifier: "fulfilment.return.scan"
            ) {
                isScanning = true
            }
        }
        if !detail.pallets.isEmpty {
            SectionHeading("Pallets", detail: "\(pickedPallets(detail)) of \(detail.pallets.count) picked")
            CardList(detail.pallets) { line in
                ListRow(
                    line.pallet?.reference ?? "#\(line.lineId)",
                    subtitle: palletLineSubtitle(line),
                    chips: [Chip(line.waitingCustomerService ? "Waiting for customer service" : (line.lineStateLabel ?? line.lineState), PalletReturnText.lineTone(line.lineState, waiting: line.waitingCustomerService))],
                    action: line.hasActions ? { sheet = .palletLine(line.lineId) } : nil
                )
                .accessibilityIdentifier("fulfilment.return.pallet.\(line.pallet?.reference ?? "\(line.lineId)")")
            }
        }
        details(detail)
        FulfilmentNote("Picking a whole pallet also returns its stored items. A pallet that can’t be picked is set as not picked and waits for customer service.")
    }

    @ViewBuilder
    private func itemsContent(_ detail: MobilePalletReturnDetail) -> some View {
        let groups = PalletGroup.grouping(detail.storedItems)
        let listens = detail.canScan || detail.hasLineActions
        let scanGroup = groups.first { $0.pallet?.id == live.selectedPalletID }
            ?? groups.first { group in group.lines.contains { $0.line.can("pick") } }
        if listens, let scanGroup {
            FulfilmentScanCard(
                title: "Scan to pick",
                detail: "Scan the pallet, then each item",
                highlight: PalletDeliveryText.joined([scanGroup.pallet?.reference, scanGroup.locationCode]),
                footnote: live.queuedScans > 0 ? "\(live.queuedScans) scan\(live.queuedScans == 1 ? "" : "s") waiting" : nil,
                identifier: "fulfilment.return.scan"
            ) {
                isScanning = true
            }
        }
        ForEach(groups) { group in
            SectionHeading("From \(PalletDeliveryText.joined([group.pallet?.reference, group.locationCode ?? "no location"]))")
            CardList(group.lines.map { ItemLineEntry(item: $0.item, line: $0.line) }) { entry in
                itemLineRow(entry.item, entry.line)
            }
        }
        details(detail)
        FulfilmentNote("If a pallet has too few, pick the rest from another pallet holding the same item.")
    }

    private func itemLineRow(_ item: MobileReturnStoredItem, _ line: MobileReturnItemLine) -> some View {
        let tone = PalletReturnText.itemLineTone(line)
        let subtitle = PalletDeliveryText.joined([
            item.storedItem.name,
            line.quantityToPick > 0 ? "To pick \(line.quantityToPick) · on pallet \(line.quantityOnPallet)" : nil,
            line.quantityNotPicked > 0 ? "\(line.quantityNotPicked) not picked" : nil,
            line.waitingCustomerService ? "Waiting for customer service" : nil
        ])
        let openable = line.hasActions || item.canPickFromOtherPallet
        return ListRow(
            item.storedItem.reference ?? "#\(line.lineId)",
            subtitle: subtitle,
            action: openable ? { sheet = .itemLine(line.lineId) } : nil
        ) {
            EmptyView()
        } trailing: {
            FulfilmentCountLabel(done: line.quantityPicked, total: line.quantityOrdered, tone: tone)
        }
        .accessibilityIdentifier("fulfilment.return.item.\(item.storedItem.reference ?? "\(line.lineId)")")
    }

    @ViewBuilder
    private func pickedContent(_ detail: MobilePalletReturnDetail) -> some View {
        pickedSummary(detail)
        if detail.inserts.total > 0 {
            Card {
                ListRow(
                    "Customer inserts",
                    subtitle: detail.inserts.unprinted > 0 ? "\(detail.inserts.unprinted) not printed" : "All printed",
                    icon: .printer,
                    iconTone: detail.inserts.unprinted > 0 ? .warn : .success,
                    trailing: {
                        if let print = detail.action("print_inserts") {
                            LinkButton(print.label.isEmpty ? "Print" : print.label) {
                                Task { await live.printInserts() }
                            }
                            .disabled(live.isWorking)
                        }
                    }
                )
            }
        }
        parcels(detail)
        shipments(detail, canPrint: warehouseStore.selected?.can.editFulfilment == true, canDelete: true)
        if detail.action("add_shipment") != nil, !detail.shipments.isEmpty {
            AikuButton("Add another shipment", icon: .plus, kind: .secondary) {
                openShipment(detail)
            }
            .accessibilityIdentifier("fulfilment.return.addAnotherShipment")
        }
        details(detail)
        FulfilmentNote("Dispatch needs every customer insert printed and, unless the customer collects, a shipment.")
    }

    private func pickedSummary(_ detail: MobilePalletReturnDetail) -> some View {
        let lines: [String]
        let isComplete: Bool
        if detail.isItems {
            let picked = detail.storedItems.reduce(0) { $0 + $1.quantityPicked }
            let notPicked = detail.storedItems.reduce(0) { $0 + $1.quantityNotPicked }
            lines = [PalletDeliveryText.plural(picked, "item picked", "items picked"), notPicked > 0 ? "\(notPicked) not picked" : nil].compactMap { $0 }
            isComplete = detail.storedItems.allSatisfy { $0.quantityNotPicked == 0 }
        } else {
            let picked = detail.pallets.filter { $0.lineState == "picked" }.compactMap { $0.pallet?.reference }
            let notPicked = detail.pallets.filter { $0.lineState == "not_picked" || $0.waitingCustomerService }.count
            lines = ["\(PalletDeliveryText.plural(picked.count, "pallet", "pallets")) picked\(picked.isEmpty ? "" : ": \(picked.joined(separator: ", "))")", notPicked > 0 ? "\(notPicked) not picked" : nil].compactMap { $0 }
            isComplete = notPicked == 0
        }
        return VStack(alignment: .leading, spacing: 4) {
            Text(isComplete ? "All picked" : (detail.stateLabel ?? detail.state))
                .font(.aiku(16, .bold))
                .foregroundStyle(AikuColor.text)
            ForEach(lines, id: \.self) { line in
                Text(line)
                    .font(.aiku(14))
                    .foregroundStyle(AikuColor.text3)
            }
        }
        .padding(14)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 12))
        .overlay(RoundedRectangle(cornerRadius: 12).stroke(AikuColor.border))
    }

    @ViewBuilder
    private func dispatchedContent(_ detail: MobilePalletReturnDetail) -> some View {
        if detail.state == "dispatched" {
            let body: String = {
                if detail.isItems {
                    let items = detail.storedItems.reduce(0) { $0 + $1.quantityPicked }
                    return "\(PalletDeliveryText.plural(items, "item", "items")) left the warehouse."
                }
                let pallets = detail.pallets.filter { $0.lineState != "not_picked" && $0.lineState != "cancel" }.count
                return "\(PalletDeliveryText.plural(pallets, "pallet is", "pallets are")) now returned."
            }()
            FulfilmentDoneHero(
                title: "\(detail.reference) dispatched",
                message: PalletDeliveryText.joined([body, PalletDeliveryText.day(detail.at("dispatched")).map { "Dispatched \($0)" }])
            )
        }
        parcels(detail)
        shipments(detail, canPrint: false, canDelete: false)
        details(detail)
        if detail.state == "dispatched", detail.platform != nil {
            FulfilmentNote("For dropshipping returns from Shopify or TikTok, dispatching also sends the fulfilment back to the customer’s shop.")
        }
        AikuButton("Next return", icon: .next) {
            router.replace(with: FulfilmentRoute.returns)
        }
        .accessibilityIdentifier("fulfilment.return.next")
    }

    @ViewBuilder
    private func parcels(_ detail: MobilePalletReturnDetail) -> some View {
        if !detail.parcels.isEmpty {
            SectionHeading("Parcels", detail: detail.parcels.count.grouped)
            CardList(detail.parcels.enumerated().map { ParcelEntry(index: $0.offset, parcel: $0.element) }) { entry in
                ListRow(
                    "Parcel \(entry.index + 1)",
                    subtitle: PalletDeliveryText.joined([
                        "\(GoodsInQuantity.decimal(entry.parcel.weight)) kg",
                        entry.parcel.dimensions.isEmpty ? nil : entry.parcel.dimensions.map(GoodsInQuantity.decimal).joined(separator: " × ") + " cm"
                    ])
                )
            }
        }
    }

    @ViewBuilder
    private func shipments(_ detail: MobilePalletReturnDetail, canPrint: Bool, canDelete: Bool) -> some View {
        if !detail.shipments.isEmpty {
            SectionHeading("Shipments")
            CardList(detail.shipments) { shipment in
                VStack(alignment: .leading, spacing: 6) {
                    ListRow(shipment.shipper ?? "Shipment", subtitle: shipment.trackingNumbers.isEmpty ? "No tracking number" : nil, icon: .goodsOut, iconTone: .success)
                    ForEach(Array(shipment.trackingNumbers.enumerated()), id: \.offset) { index, tracking in
                        Group {
                            if let url = shipment.trackingURL(index) {
                                LinkButton("Tracking \(tracking)") { openURL(url) }
                            } else {
                                Text("Tracking \(tracking)")
                                    .font(.aiku(14))
                                    .foregroundStyle(AikuColor.text2)
                            }
                        }
                        .padding(.horizontal, 14)
                    }
                    HStack(spacing: 16) {
                        if shipment.label.kind == "download", shipment.label.url != nil {
                            LinkButton(openingLabel == shipment.id ? "Opening…" : "Label PDF") {
                                Task { await openLabel(shipment) }
                            }
                            .disabled(openingLabel != nil)
                        } else if shipment.label.kind == "external", let raw = shipment.label.url, let url = URL(string: raw) {
                            LinkButton("Open label") { openURL(url) }
                        }
                        if canPrint, shipment.isPrintable {
                            LinkButton(live.working == .printLabel(shipmentID: shipment.id) ? "Printing…" : "Print label") {
                                Task { await live.printLabel(shipment.id) }
                            }
                            .disabled(live.isWorking)
                        }
                        if canDelete, shipment.canDelete {
                            Button("Delete") { confirm = .deleteShipment(shipment) }
                                .font(.aiku(14, .semibold))
                                .foregroundStyle(AikuColor.danger)
                                .frame(minHeight: 44)
                                .accessibilityIdentifier("fulfilment.return.shipment.delete.\(shipment.id)")
                        }
                    }
                    .padding(.horizontal, 14)
                }
                .padding(.bottom, 6)
            }
        }
    }

    @ViewBuilder
    private func details(_ detail: MobilePalletReturnDetail) -> some View {
        let lines: [(AppIcon, String)] = [
            detail.customer?.name.map { (.person, $0) },
            PalletDeliveryText.quoted(detail.customerReference).map { (.document, "Customer ref \($0)") },
            detail.platform?.label.map { (.deliveryNote, "Platform \($0)") },
            detail.picker?.name.map { (.person, "Picker \($0)") },
            PalletDeliveryText.day(detail.estimatedDeliveryDate).map { (.history, "Expected \($0)") }
        ].compactMap { $0 }
        if !lines.isEmpty {
            VStack(alignment: .leading, spacing: 6) {
                Text("Details")
                    .font(.aiku(13, .bold))
                    .foregroundStyle(AikuColor.text)
                ForEach(Array(lines.enumerated()), id: \.offset) { _, line in
                    HStack(spacing: 10) {
                        line.0.image
                            .font(.system(size: 15))
                            .foregroundStyle(AikuColor.text3)
                            .frame(width: 20)
                            .accessibilityHidden(true)
                        Text(line.1)
                            .font(.aiku(14))
                            .foregroundStyle(AikuColor.text2)
                    }
                }
            }
            .padding(14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 12))
            .overlay(RoundedRectangle(cornerRadius: 12).stroke(AikuColor.border))
        }
        ForEach([("Customer notes", detail.customerNotes), ("Public notes", detail.publicNotes), ("Internal notes", detail.internalNotes), ("Collection notes", detail.collectionNotes)], id: \.0) { title, text in
            if let text, !text.isEmpty {
                InlineAlert(tone: .neutral, icon: .document, title: title, message: text)
            }
        }
    }

    private func footerView(_ detail: MobilePalletReturnDetail) -> AnyView? {
        let start = detail.action("start_picking")
        let finish = detail.action("finish_picking")
        let dispatch = detail.action("dispatch")
        let addShipment = detail.action("add_shipment")
        switch detail.state {
        case "confirmed", "picking":
            if let start {
                return AnyView(AikuButton(start.label.isEmpty ? "Start picking" : start.label, look: PalletReturnActionLooks.look(for: start.key)) { confirm = .start }
                    .disabled(live.isWorking)
                    .accessibilityIdentifier("fulfilment.return.startPicking"))
            }
            if let finish {
                return AnyView(AikuButton(finish.label.isEmpty ? "Set as picked" : finish.label, look: PalletReturnActionLooks.look(for: finish.key)) { confirm = .finish }
                    .disabled(live.isWorking)
                    .accessibilityIdentifier("fulfilment.return.finishPicking"))
            }
            if detail.isItems {
                if let short = detail.storedItems.first(where: { $0.canPickFromOtherPallet }) {
                    return AnyView(AikuButton("Pick \(short.storedItem.reference ?? "") from another pallet", icon: .pallet, kind: .secondary) {
                        sheet = .otherPallet(short.id)
                    })
                }
                let ordered = detail.storedItems.reduce(0) { $0 + $1.quantityOrdered }
                let picked = detail.storedItems.reduce(0) { $0 + $1.quantityPicked }
                return AnyView(status(detail.stateLabel ?? detail.state, "\(picked) of \(ordered) picked"))
            }
            if let next = nextPalletLine(detail), next.can("pick") || next.can("not_picked") {
                return AnyView(HStack(spacing: 8) {
                    if next.can("not_picked") {
                        AikuButton("Not picked", look: PalletReturnActionLooks.look(for: "not_picked")) {
                            confirm = .notPickedPallet(lineID: next.lineId, reference: next.pallet?.reference ?? "")
                        }
                    }
                    if next.can("pick") {
                        AikuButton("Pick \(next.pallet?.reference ?? "")", look: PalletReturnActionLooks.look(for: "pick")) {
                            Task { await live.pickPallet(next.lineId) }
                        }
                        .disabled(live.isWorking)
                        .accessibilityIdentifier("fulfilment.return.pickNext")
                    }
                })
            }
            return AnyView(status(detail.stateLabel ?? detail.state, "\(pickedPallets(detail)) of \(detail.pallets.count) picked"))
        case "picked":
            return AnyView(VStack(spacing: 8) {
                if let addShipment, detail.shipments.isEmpty {
                    AikuButton(addShipment.label.isEmpty ? "Add shipment" : addShipment.label, look: PalletReturnActionLooks.look(for: addShipment.key).inFooter(isFirst: dispatch == nil)) {
                        openShipment(detail)
                    }
                    .accessibilityIdentifier("fulfilment.return.addShipment")
                }
                if let dispatch {
                    AikuButton(dispatch.label.isEmpty ? "Dispatch" : dispatch.label, look: PalletReturnActionLooks.look(for: dispatch.key)) { confirm = .dispatch }
                        .disabled(live.isWorking)
                        .accessibilityIdentifier("fulfilment.return.dispatch")
                } else if let blocked = detail.dispatchBlocked {
                    HStack(spacing: 10) {
                        AppIcon.lock.image
                            .foregroundStyle(AikuColor.warn)
                            .accessibilityHidden(true)
                        VStack(alignment: .leading, spacing: 2) {
                            Text("Dispatch")
                                .font(.aiku(15, .bold))
                                .foregroundStyle(AikuColor.text)
                            Text(blocked)
                                .font(.aiku(13))
                                .foregroundStyle(AikuColor.warn)
                        }
                        Spacer(minLength: 0)
                    }
                    .frame(minHeight: 44)
                } else if !(addShipment != nil && detail.shipments.isEmpty) {
                    status(detail.stateLabel ?? detail.state, nil)
                }
            })
        default:
            return nil
        }
    }

    private func status(_ title: String, _ body: String?) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(title)
                .font(.aiku(14, .semibold))
                .foregroundStyle(AikuColor.text)
            if let body {
                Text(body)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    @ViewBuilder
    private func sheetView(_ current: ReturnSheet, detail: MobilePalletReturnDetail) -> some View {
        switch current {
        case .palletLine(let id):
            if let line = detail.palletLine(id) {
                PalletLineSheet(live: live, line: line) {
                    sheet = nil
                    confirm = .notPickedPallet(lineID: line.lineId, reference: line.pallet?.reference ?? "")
                } onClose: { sheet = nil }
            }
        case .itemLine(let id):
            if let (item, line) = detail.itemLine(id) {
                ItemLineSheet(live: live, item: item, line: line) {
                    sheet = .notPickedItem(line.lineId)
                } onOtherPallet: {
                    sheet = .otherPallet(item.id)
                } onClose: { sheet = nil }
            }
        case .notPickedItem(let id):
            if let (item, line) = detail.itemLine(id) {
                NotPickedItemSheet(live: live, item: item, line: line) { sheet = nil }
            }
        case .otherPallet(let id):
            if let item = detail.storedItem(id) {
                OtherPalletSheet(live: live, item: item) { sheet = nil }
            }
        }
    }

    private func nextPalletLine(_ detail: MobilePalletReturnDetail) -> MobileReturnPalletLine? {
        detail.pallets.first { $0.pallet?.id == live.selectedPalletID && $0.hasActions } ?? detail.pallets.first { $0.can("pick") }
    }

    private func pickedPallets(_ detail: MobilePalletReturnDetail) -> Int {
        detail.counts.lines > 0 ? detail.counts.picked : detail.pallets.filter { $0.lineState == "picked" }.count
    }

    private func palletLineSubtitle(_ line: MobileReturnPalletLine) -> String {
        let contents = line.storedItems.map { "\($0.reference ?? "") × \($0.quantity)" }.joined(separator: ", ")
        return PalletDeliveryText.joined([line.pallet?.typeLabel, PalletReturnText.locationText(line.location), line.pallet?.customerReference, contents.isEmpty ? nil : contents])
    }

    private func openShipment(_ detail: MobilePalletReturnDetail) {
        router.open(FulfilmentRoute.palletReturnShipment(id: detail.id, reference: detail.reference))
    }

    private func openLabel(_ shipment: MobileReturnShipment) async {
        openingLabel = shipment.id
        defer { openingLabel = nil }
        switch await live.downloadLabel(shipment) {
        case .success(let url): labelURL = url
        case .failure(let problem): toasts.show(problem.message, tone: .danger)
        }
    }

    private var isDestructiveConfirm: Bool {
        switch confirm {
        case .notPickedPallet, .deleteShipment: true
        default: false
        }
    }

    private func confirmTitle(_ detail: MobilePalletReturnDetail) -> String {
        switch confirm {
        case .start: "Start picking \(detail.reference)?"
        case .finish: "Set \(detail.reference) as picked?"
        case .dispatch: "Dispatch \(detail.reference)?"
        case .notPickedPallet(_, let reference): "Set \(reference) as not picked?"
        case .deleteShipment(let shipment): "Delete \(shipment.shipper ?? "this shipment")?"
        case nil: ""
        }
    }

    private func confirmMessage(_ detail: MobilePalletReturnDetail) -> String {
        switch confirm {
        case .start: "You become the picker of this return."
        case .finish: "The return moves to Picked and is ready for its shipment and dispatch."
        case .dispatch:
            PalletDeliveryText.joined([
                detail.isItems ? "Its stored items leave the warehouse." : "Its pallets are set as returned and leave the warehouse.",
                detail.platform?.label.map { "The fulfilment is also sent to the customer’s \($0) shop." }
            ])
        case .notPickedPallet: "The pallet waits for customer service. They must resolve it before the return can be set as picked."
        case .deleteShipment: "The carrier is not told: cancel the label with them if it was already booked."
        case nil: ""
        }
    }

    private func confirmButton(_ detail: MobilePalletReturnDetail) -> String {
        switch confirm {
        case .start: detail.action("start_picking")?.label.nilIfEmpty ?? "Start picking"
        case .finish: detail.action("finish_picking")?.label.nilIfEmpty ?? "Set as picked"
        case .dispatch: detail.action("dispatch")?.label.nilIfEmpty ?? "Dispatch"
        case .notPickedPallet: "Not picked"
        case .deleteShipment: "Delete"
        case nil: "OK"
        }
    }

    private func run(_ confirm: ReturnConfirm) async {
        switch confirm {
        case .start: _ = await live.startPicking()
        case .finish: _ = await live.finishPicking()
        case .dispatch: _ = await live.dispatch()
        case .notPickedPallet(let id, _): _ = await live.notPicked(id)
        case .deleteShipment(let shipment): _ = await live.deleteShipment(shipment.id)
        }
    }
}

private struct ItemLineEntry: Identifiable {
    let item: MobileReturnStoredItem
    let line: MobileReturnItemLine
    var id: Int { line.lineId }
}

private struct ParcelEntry: Identifiable {
    let index: Int
    let parcel: MobileReturnParcel
    var id: Int { index }
}

extension MobileReturnPalletLine.Content: Identifiable {
    var id: String { "\(reference ?? "")-\(quantity)" }
}

private struct PalletLineSheet: View {
    let live: PalletReturnLive
    let line: MobileReturnPalletLine
    let onNotPicked: () -> Void
    let onClose: () -> Void

    var body: some View {
        SheetScaffold(title: line.pallet?.reference ?? "Pallet", subtitle: PalletDeliveryText.joined([line.pallet?.typeLabel, PalletReturnText.locationText(line.location)])) {
            Text(line.waitingCustomerService ? "Set as not picked. Customer service must resolve it before the return can be set as picked." : (line.lineStateLabel ?? line.lineState))
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text2)
            if !line.storedItems.isEmpty {
                CardList(line.storedItems) { item in
                    ListRow(item.reference ?? "", subtitle: item.name, trailing: {
                        Text(item.quantity.grouped)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                    })
                }
            }
            if let message = live.sheetError {
                FieldError(message)
            }
        } footer: {
            if line.can("pick") {
                AikuButton(line.label("pick") ?? "Pick \(line.pallet?.reference ?? "")", look: PalletReturnActionLooks.look(for: "pick")) {
                    Task { if await live.pickPallet(line.lineId) { onClose() } }
                }
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.return.line.pick")
            }
            if line.can("not_picked") {
                AikuButton(line.label("not_picked") ?? "Not picked", look: PalletReturnActionLooks.look(for: "not_picked"), action: onNotPicked)
                    .accessibilityIdentifier("fulfilment.return.line.notPicked")
            }
            if line.can("undo") {
                AikuButton(line.label("undo") ?? "Undo", look: PalletReturnActionLooks.look(for: "undo")) {
                    Task { if await live.undo(line.lineId) { onClose() } }
                }
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.return.line.undo")
            }
            AikuButton("Close", kind: .tertiary, action: onClose)
        }
    }
}

private struct ItemLineSheet: View {
    let live: PalletReturnLive
    let item: MobileReturnStoredItem
    let line: MobileReturnItemLine
    let onNotPicked: () -> Void
    let onOtherPallet: () -> Void
    let onClose: () -> Void
    @State private var amount = 1

    var body: some View {
        let canPick = line.can("pick") && line.maxPick > 0
        SheetScaffold(title: item.storedItem.reference ?? "Item", subtitle: item.storedItem.name) {
            Text("From \(PalletDeliveryText.joined([line.pallet?.reference, PalletReturnText.locationText(line.location)]))")
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text2)
            Text("Picked \(line.quantityPicked) of \(line.quantityOrdered) · to pick \(line.quantityToPick) · on this pallet \(line.quantityOnPallet)")
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text2)
            if line.quantityNotPicked > 0 {
                Text("\(line.quantityNotPicked) not picked")
                    .font(.aiku(14))
                    .foregroundStyle(AikuColor.warn)
            }
            if line.waitingCustomerService {
                Text("Set as not picked. Customer service must resolve it before the return can be set as picked.")
                    .font(.aiku(14))
                    .foregroundStyle(AikuColor.warn)
            }
            if canPick {
                QuantityStepper(value: $amount, range: 1...line.maxPick)
                    .accessibilityIdentifier("fulfilment.return.item.amount")
            }
            if let message = live.sheetError {
                FieldError(message)
            }
        } footer: {
            if canPick {
                AikuButton("Pick \(amount)", look: PalletReturnActionLooks.look(for: "pick")) {
                    Task { if await live.pickItem(line.lineId, quantity: amount) { onClose() } }
                }
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.return.item.pick")
                if line.maxPick > 1 {
                    AikuButton("Pick all \(line.maxPick)", icon: .checkDouble, kind: .secondary) {
                        Task { if await live.pickItem(line.lineId, quantity: nil) { onClose() } }
                    }
                    .disabled(live.isWorking)
                    .accessibilityIdentifier("fulfilment.return.item.pickAll")
                }
            }
            if item.canPickFromOtherPallet {
                AikuButton("Pick from another pallet", icon: .pallet, kind: .secondary, action: onOtherPallet)
                    .accessibilityIdentifier("fulfilment.return.item.otherPallet")
            }
            if line.can("not_picked") {
                AikuButton(line.label("not_picked") ?? "Not picked", look: PalletReturnActionLooks.look(for: "not_picked"), action: onNotPicked)
                    .accessibilityIdentifier("fulfilment.return.item.notPicked")
            }
            if line.can("undo") {
                AikuButton(line.label("undo") ?? "Undo", look: PalletReturnActionLooks.look(for: "undo")) {
                    Task { if await live.undo(line.lineId) { onClose() } }
                }
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.return.item.undo")
            }
            AikuButton("Close", kind: .tertiary, action: onClose)
        }
    }
}

private struct NotPickedItemSheet: View {
    let live: PalletReturnLive
    let item: MobileReturnStoredItem
    let line: MobileReturnItemLine
    let onClose: () -> Void
    @State private var amount: Int

    init(live: PalletReturnLive, item: MobileReturnStoredItem, line: MobileReturnItemLine, onClose: @escaping () -> Void) {
        self.live = live
        self.item = item
        self.line = line
        self.onClose = onClose
        _amount = State(initialValue: max(line.quantityToPick, 1))
    }

    var body: some View {
        let most = max(line.quantityToPick, 1)
        SheetScaffold(title: "Set \(item.storedItem.reference ?? "") as not picked?", subtitle: "From \(PalletDeliveryText.joined([line.pallet?.reference, line.location?.code]))") {
            QuantityStepper(value: $amount, range: 1...most)
            HelperText("How many can’t be picked from this pallet.")
            if let message = live.sheetError {
                FieldError(message)
            }
        } footer: {
            AikuButton("Set \(amount) as not picked", look: PalletReturnActionLooks.look(for: "not_picked").forConfirm) {
                Task { if await live.notPicked(line.lineId, quantity: amount) { onClose() } }
            }
            .disabled(live.isWorking)
            .accessibilityIdentifier("fulfilment.return.item.confirmNotPicked")
            AikuButton("Cancel", kind: .tertiary, action: onClose)
        }
    }
}

private struct OtherPalletSheet: View {
    let live: PalletReturnLive
    let item: MobileReturnStoredItem
    let onClose: () -> Void
    @State private var chosenID: Int?
    @State private var amount = 1

    var body: some View {
        let others = item.otherPallets.filter { $0.quantityAvailable > 0 }
        let shortLine = item.shortLine
        let chosen = others.first { $0.palletStoredItemId == (chosenID ?? others.first?.palletStoredItemId) }
        let most = max(min(chosen?.quantityAvailable ?? 1, item.quantityToPick), 1)
        SheetScaffold(
            title: "Pick \(item.storedItem.reference ?? "") from another pallet",
            subtitle: shortLine.map { "\($0.shortfall) short on \($0.pallet?.reference ?? "")" }
        ) {
            ForEach(others) { other in
                RadioCard(
                    other.pallet?.reference ?? "",
                    subtitle: PalletDeliveryText.joined([PalletReturnText.locationText(other.location), "\(other.quantityAvailable) available"]),
                    isSelected: other.palletStoredItemId == chosen?.palletStoredItemId
                ) {
                    chosenID = other.palletStoredItemId
                }
            }
            if chosen != nil {
                QuantityStepper(value: $amount, range: 1...most)
                if let shortLine {
                    HelperText("The same amount is set as not picked on \(shortLine.pallet?.reference ?? ""), so the total ordered stays the same.")
                }
            }
            if let message = live.sheetError {
                FieldError(message)
            }
        } footer: {
            if let chosen {
                AikuButton("Pick \(amount) from \(chosen.pallet?.reference ?? "")", look: PalletReturnActionLooks.look(for: "pick")) {
                    Task {
                        if await live.pickFromOtherPallet(chosen.palletStoredItemId, quantity: min(amount, most), fromLineID: shortLine?.lineId) { onClose() }
                    }
                }
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.return.other.pick")
            }
            AikuButton("Cancel", kind: .tertiary, action: onClose)
        }
        .onAppear {
            amount = max(min(shortLine?.shortfall ?? 1, most), 1)
        }
    }
}
