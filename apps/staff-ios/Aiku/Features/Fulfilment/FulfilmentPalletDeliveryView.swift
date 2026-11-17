import SwiftUI

private enum PalletDeliveryConfirm: Identifiable, Equatable {
    case receive
    case finish
    case notReceived(id: Int, reference: String)
    case undoNotReceived(id: Int, reference: String)

    var id: String {
        switch self {
        case .receive: "receive"
        case .finish: "finish"
        case .notReceived(let id, _): "not-received-\(id)"
        case .undoNotReceived(let id, _): "undo-\(id)"
        }
    }
}

private struct SheetPallet: Identifiable {
    let id: Int
}

struct FulfilmentPalletDeliveryView: View {
    let deliveryID: Int
    let reference: String
    @Environment(\.apiClient) private var apiClient
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        if let warehouseID = warehouseStore.selected?.id {
            PalletDeliveryScreen(live: PalletDeliveryLive(warehouseID: warehouseID, deliveryID: deliveryID, apiClient: apiClient), reference: reference)
                .id("\(warehouseID)-\(deliveryID)")
        } else {
            AppScreen(title: reference, kicker: "Fulfilment delivery") {
                EmptyState(icon: .stockDelivery, title: "Choose a warehouse first")
            }
        }
    }
}

private struct PalletDeliveryScreen: View {
    @State var live: PalletDeliveryLive
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var confirm: PalletDeliveryConfirm?
    @State private var sheetPallet: SheetPallet?
    @State private var isScanningLocation = false

    var body: some View {
        Group {
            if let delivery = live.delivery {
                loaded(delivery)
            } else {
                AppScreen(title: reference, kicker: "Fulfilment delivery") {
                    if let message = live.loadError {
                        InlineAlert(title: "Couldn’t load delivery \(reference)", message: message)
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
        .task { await live.load() }
        .onChange(of: live.done) { _, done in
            guard let done else { return }
            toasts.show(done.message, tone: done.tone)
            live.clearDone()
        }
    }

    @ViewBuilder
    private func loaded(_ delivery: MobilePalletDeliveryDetail) -> some View {
        Group {
            switch delivery.state {
            case "booking_in":
                screen(delivery, kicker: PalletDeliveryText.joined(["Fulfilment delivery", delivery.customer?.name ?? delivery.stateLabel])) {
                    bookInContent(delivery)
                }
            case "booked_in":
                screen(delivery, kicker: kicker(delivery)) {
                    bookedInContent(delivery)
                }
            default:
                screen(delivery, kicker: kicker(delivery)) {
                    detailContent(delivery)
                }
            }
        }
        .hardwareScanner(isEnabled: delivery.canScanForBookIn && confirm == nil && sheetPallet == nil && !isScanningLocation) { code in
            Task { await scanForBookIn(code, delivery: delivery) }
        }
        .aikuSheet(item: $sheetPallet) { item in
            if let pallet = live.delivery?.pallet(item.id) {
                palletSheet(pallet)
            }
        }
        .fullScreenCover(isPresented: $isScanningLocation) {
            FulfilmentCodeCamera(title: "Scan the location", onClose: { isScanningLocation = false }) { code in
                isScanningLocation = false
                Task { await scanForBookIn(code, delivery: delivery) }
            }
        }
        .aikuConfirm(
            confirmTitle,
            isPresented: Binding(get: { confirm != nil }, set: { if !$0 { confirm = nil } }),
            message: confirmMessage,
            confirmTitle: confirmButton(delivery),
            isDestructive: { if case .notReceived = confirm { return true }; return false }()
        ) {
            guard let current = confirm else { return }
            confirm = nil
            Task { await run(current) }
        }
    }

    private func kicker(_ delivery: MobilePalletDeliveryDetail) -> String {
        PalletDeliveryText.joined(["Fulfilment delivery", delivery.customer?.name ?? delivery.stateLabel])
    }

    @ViewBuilder
    private func screen<Content: View>(_ delivery: MobilePalletDeliveryDetail, kicker: String, @ViewBuilder content: () -> Content) -> some View {
        if let footer = footerKind(delivery) {
            AppScreen(title: delivery.reference, kicker: kicker) {
                content()
            } footer: {
                footerView(footer, delivery: delivery)
            }
        } else {
            AppScreen(title: delivery.reference, kicker: kicker) {
                content()
            }
        }
    }

    @ViewBuilder
    private func status(_ delivery: MobilePalletDeliveryDetail) -> some View {
        if delivery.state == "not_received" {
            InlineAlert(tone: .danger, icon: .warning, title: delivery.stateLabel ?? "Not received", message: "None of its pallets arrived.")
        } else if let timeline = delivery.timeline, !timeline.isEmpty {
            let current = timeline.firstIndex { $0.state == delivery.state } ?? 0
            FulfilmentProgressSteps(
                steps: timeline.map { $0.label ?? $0.state },
                currentIndex: delivery.state == "booked_in" ? timeline.count : current
            )
        }
    }

    @ViewBuilder
    private func detailContent(_ delivery: MobilePalletDeliveryDetail) -> some View {
        status(delivery)
        detailsCard(delivery)
        notes(delivery)
        countsCard(delivery)
        SectionHeading(
            "Pallets",
            detail: delivery.state == "received"
                ? "\((delivery.counts.received ?? 0).grouped) received"
                : PalletDeliveryText.plural(delivery.palletList.count, "pallet", "pallets")
        )
        palletList(delivery.palletList) { pallet in sheetPallet = SheetPallet(id: pallet.id) }
        charges("Services", delivery.services ?? [])
        charges("Physical goods", delivery.physicalGoods ?? [])
        FulfilmentNote("Tap a pallet to see its stored items. After Start booking in, each pallet is booked in at a location. A pallet that did not arrive can be set as not received.")
    }

    @ViewBuilder
    private func detailsCard(_ delivery: MobilePalletDeliveryDetail) -> some View {
        let lines: [(AppIcon, String)] = [
            delivery.customer?.name.map { (.person, $0) },
            PalletDeliveryText.quoted(delivery.customerReference).map { (.document, "Customer ref \($0)") },
            PalletDeliveryText.day(delivery.estimatedDeliveryDate).map { (.history, "Expected \($0)") },
            PalletDeliveryText.day(delivery.at("received")).map { (.check, "Received \($0)") },
            PalletDeliveryText.day(delivery.at("booked_in")).map { (.pallet, "Booked in \($0)") }
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
    }

    @ViewBuilder
    private func notes(_ delivery: MobilePalletDeliveryDetail) -> some View {
        if let note = delivery.customerNotes, !note.isEmpty {
            InlineAlert(tone: .neutral, icon: .document, title: "Customer notes", message: note)
        }
        if let note = delivery.publicNotes, !note.isEmpty {
            InlineAlert(tone: .neutral, icon: .document, title: "Public notes", message: note)
        }
        if let note = delivery.internalNotes, !note.isEmpty {
            InlineAlert(tone: .warn, icon: .document, title: "Internal notes", message: note)
        }
    }

    private func countsCard(_ delivery: MobilePalletDeliveryDetail) -> some View {
        let counts = delivery.counts
        var facts = [
            GoodsInFact("Pallets", counts.pallets.grouped),
            GoodsInFact("Boxes", counts.boxes.grouped),
            GoodsInFact("Oversize", counts.oversizes.grouped),
            GoodsInFact("Total", counts.total.grouped)
        ]
        if delivery.state != "confirmed" {
            facts += [
                GoodsInFact("Received", (counts.received ?? 0).grouped),
                GoodsInFact("Booking in", (counts.bookingIn ?? 0).grouped),
                GoodsInFact("Booked in", counts.bookedIn.grouped),
                GoodsInFact("Not received", counts.notReceived.grouped)
            ]
        }
        return GoodsInFactGrid(title: "Summary", facts: facts)
    }

    @ViewBuilder
    private func charges(_ title: String, _ charges: [MobilePalletDeliveryCharge]) -> some View {
        if !charges.isEmpty {
            SectionHeading(title, detail: charges.count.grouped)
            CardList(charges.enumerated().map { ChargeItem(index: $0.offset, charge: $0.element) }) { item in
                let name = item.charge.name?.isEmpty == false ? item.charge.name! : (item.charge.code ?? "")
                ListRow(
                    name,
                    subtitle: PalletDeliveryText.joined([item.charge.name?.isEmpty == false ? item.charge.code : nil, item.charge.unitLabel])
                ) {
                    EmptyView()
                } trailing: {
                    Text(item.charge.quantity.grouped)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                }
            }
        }
    }

    @ViewBuilder
    private func palletList(_ pallets: [MobileDeliveryPallet], isOpenable: @escaping (MobileDeliveryPallet) -> Bool = { _ in true }, onOpen: @escaping (MobileDeliveryPallet) -> Void) -> some View {
        if !pallets.isEmpty {
            CardList(pallets.map(PalletItem.init)) { item in
                ListRow(
                    item.pallet.reference,
                    subtitle: PalletDeliveryText.palletSubtitle(item.pallet),
                    chips: PalletDeliveryText.palletChip(item.pallet).map { [Chip($0, PalletDeliveryText.palletTone(item.pallet))] } ?? [],
                    action: isOpenable(item.pallet) ? { onOpen(item.pallet) } : nil
                )
                .accessibilityIdentifier("fulfilment.pallet.\(item.pallet.reference)")
            }
        }
    }

    @ViewBuilder
    private func bookInContent(_ delivery: MobilePalletDeliveryDetail) -> some View {
        status(delivery)
        if let pallet = live.openPallet {
            bookInPanel(pallet)
        } else if let next = delivery.nextToBookIn {
            FulfilmentScanCard(
                title: "Scan pallet, then location",
                detail: "Next to book in: \(PalletDeliveryText.joined([next.reference, PalletDeliveryText.typeText(next), PalletDeliveryText.quoted(next.customerReference)]))",
                highlight: next.reference,
                identifier: "fulfilment.bookIn.next"
            ) {
                live.openPallet(next.id)
            }
        }
        SectionHeading("Pallets", detail: PalletDeliveryText.bookInProgress(booked: delivery.bookedInCount, of: delivery.bookInTotal))
        palletList(delivery.palletList, isOpenable: { $0.isWorkable }) { pallet in live.openPallet(pallet.id) }
        FulfilmentNote("Scan a pallet label, then the location it goes to. A booked-in pallet can be moved to another location until the delivery is set as booked in.")
    }

    @ViewBuilder
    private func bookInPanel(_ pallet: MobileDeliveryPallet) -> some View {
        @Bindable var live = live
        let canBookIn = pallet.can("book_in")
        let notReceived = pallet.action("not_received")
        VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 8) {
                Text(pallet.reference)
                    .font(.aiku(20, .bold))
                    .foregroundStyle(AikuColor.text)
                Spacer(minLength: 0)
                if let chip = PalletDeliveryText.palletChip(pallet) {
                    StatusChip(chip, tone: PalletDeliveryText.palletTone(pallet))
                }
            }
            Text(PalletDeliveryText.joined([PalletDeliveryText.typeText(pallet), PalletDeliveryText.quoted(pallet.customerReference), PalletDeliveryText.locationText(pallet)]))
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text3)
            if canBookIn {
                SectionHeading(pallet.state == "booked_in" ? "Change location" : "Scan or search its location")
                SearchField(text: $live.locationQuery, placeholder: "Search or scan a location", onScan: { isScanningLocation = true })
                    .accessibilityIdentifier("fulfilment.bookIn.locationSearch")
                if let message = live.locationError {
                    FieldError(message)
                }
                if let picked = live.pickedLocation, !live.locationResults.contains(picked) {
                    locationChoice(picked, isSelected: true)
                }
                ForEach(live.locationResults) { location in
                    locationChoice(location, isSelected: live.pickedLocation?.id == location.id)
                }
                if live.isSearchingLocations {
                    ProgressView().frame(maxWidth: .infinity)
                } else if !live.locationQuery.trimmingCharacters(in: .whitespaces).isEmpty, live.locationResults.isEmpty, live.locationError == nil, live.pickedLocation == nil {
                    Text("No pallet location starts with “\(live.locationQuery.trimmingCharacters(in: .whitespaces))”")
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
            }
            if let fieldError = live.fieldError {
                FieldError(fieldError)
            }
            if canBookIn, let target = live.pickedLocation {
                AikuButton("Book in at \(target.code)", icon: .locations) {
                    Task { await live.bookIn(pallet.id, location: target) }
                }
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.bookIn.confirm")
            }
            HStack(spacing: 8) {
                if let notReceived {
                    AikuButton(notReceived.label.isEmpty ? "Not received" : notReceived.label, kind: .tertiary) {
                        confirm = .notReceived(id: pallet.id, reference: pallet.reference)
                    }
                    .disabled(live.isWorking)
                    .accessibilityIdentifier("fulfilment.bookIn.notReceived")
                }
                AikuButton("Another pallet", kind: .tertiary) {
                    live.closePallet()
                }
            }
        }
        .padding(14)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 12))
        .overlay(RoundedRectangle(cornerRadius: 12).stroke(AikuColor.border))
        .task(id: live.locationQuery) {
            await live.searchLocations(live.locationQuery)
        }
    }

    private func locationChoice(_ location: MobileFulfilmentLocation, isSelected: Bool) -> some View {
        RadioCard(location.code, subtitle: PalletDeliveryText.plural(location.numberPallets, "pallet", "pallets"), isSelected: isSelected) {
            live.pickedLocation = location
        }
        .accessibilityIdentifier("fulfilment.bookIn.location.\(location.code)")
    }

    @ViewBuilder
    private func bookedInContent(_ delivery: MobilePalletDeliveryDetail) -> some View {
        let storing = delivery.palletList.filter { $0.state != "not_received" }
        let notReceived = delivery.palletList.filter { $0.state == "not_received" }
        status(delivery)
        FulfilmentDoneHero(
            title: "\(delivery.reference) booked in",
            message: storing.count == 1 ? "1 pallet is now Storing." : "\(storing.count.grouped) pallets are now Storing."
        )
        SectionHeading("Pallets", detail: storing.count.grouped)
        palletList(storing) { pallet in router.open(FulfilmentRoute.pallet(id: pallet.id, reference: pallet.reference)) }
        if !notReceived.isEmpty {
            SectionHeading("Not received", detail: PalletDeliveryText.plural(notReceived.count, "pallet", "pallets"))
            palletList(notReceived) { pallet in router.open(FulfilmentRoute.pallet(id: pallet.id, reference: pallet.reference)) }
        }
        AikuButton("View stored pallets", kind: .tertiary) {
            router.open(FulfilmentRoute.pallets)
        }
        AikuButton("Next delivery") {
            router.back()
        }
        .accessibilityIdentifier("fulfilment.delivery.next")
    }

    private func palletSheet(_ pallet: MobileDeliveryPallet) -> some View {
        let notReceived = pallet.action("not_received")
        let undo = pallet.action("undo_not_received")
        return SheetScaffold(title: pallet.reference, subtitle: PalletDeliveryText.joined([PalletDeliveryText.typeText(pallet), PalletDeliveryText.quoted(pallet.customerReference)])) {
            VStack(alignment: .leading, spacing: 8) {
                KeyValueRow("State", PalletDeliveryText.palletChip(pallet) ?? pallet.state)
                KeyValueRow("Location", PalletDeliveryText.locationText(pallet))
                if let notes = pallet.notes, !notes.isEmpty {
                    KeyValueRow("Notes", notes)
                }
            }
            if let items = pallet.storedItems, !items.isEmpty {
                SheetSectionLabel("Stored items")
                CardList(items) { item in
                    ListRow(item.reference ?? "#\(item.id)", subtitle: item.name) {
                        EmptyView()
                    } trailing: {
                        Text(item.quantity.grouped)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                    }
                }
            }
        } footer: {
            if let undo {
                AikuButton(undo.label.isEmpty ? "Undo not received" : undo.label, icon: .undo, kind: .secondary) {
                    sheetPallet = nil
                    confirm = .undoNotReceived(id: pallet.id, reference: pallet.reference)
                }
                .accessibilityIdentifier("fulfilment.pallet.undoNotReceived")
            }
            if let notReceived {
                AikuButton(notReceived.label.isEmpty ? "Not received" : notReceived.label, kind: .danger) {
                    sheetPallet = nil
                    confirm = .notReceived(id: pallet.id, reference: pallet.reference)
                }
                .accessibilityIdentifier("fulfilment.pallet.notReceived")
            }
            AikuButton("Close", kind: .tertiary) {
                sheetPallet = nil
            }
        }
    }

    private enum FooterKind {
        case receive(MobileFulfilmentAction)
        case startBooking(MobileFulfilmentAction)
        case finish(MobileFulfilmentAction)
        case status(title: String, body: String)
    }

    private func footerKind(_ delivery: MobilePalletDeliveryDetail) -> FooterKind? {
        let progress = PalletDeliveryText.bookInProgress(booked: delivery.bookedInCount, of: delivery.bookInTotal)
        switch delivery.state {
        case "booking_in":
            if let finish = delivery.action("finish_booking") { return .finish(finish) }
            if let blocked = delivery.finishBookingBlocked, !blocked.isEmpty { return .status(title: progress, body: blocked) }
            return .status(title: progress, body: "Setting it as booked in needs the right to edit this fulfilment shop.")
        case "confirmed":
            if let receive = delivery.action("receive") { return .receive(receive) }
            return .status(title: "Waiting to be received", body: "Marking it as received needs the right to edit this fulfilment shop.")
        case "received":
            if let start = delivery.action("start_booking") { return .startBooking(start) }
            return .status(title: "Waiting to be booked in", body: "Starting booking in needs the right to edit this fulfilment shop.")
        default:
            return nil
        }
    }

    @ViewBuilder
    private func footerView(_ kind: FooterKind, delivery: MobilePalletDeliveryDetail) -> some View {
        switch kind {
        case .receive(let action):
            AikuButton(action.label.isEmpty ? "Mark as received" : action.label, icon: .check) {
                confirm = .receive
            }
            .disabled(live.isWorking)
            .accessibilityIdentifier("fulfilment.delivery.receive")
        case .startBooking(let action):
            AikuButton(action.label.isEmpty ? "Start booking in" : action.label, icon: .pallet) {
                Task { await live.startBooking() }
            }
            .disabled(live.isWorking)
            .accessibilityIdentifier("fulfilment.delivery.startBookingIn")
        case .finish(let action):
            AikuButton(action.label.isEmpty ? "Set as booked in" : action.label, icon: .check) {
                confirm = .finish
            }
            .disabled(live.isWorking)
            .accessibilityIdentifier("fulfilment.delivery.setBookedIn")
        case .status(let title, let body):
            VStack(alignment: .leading, spacing: 2) {
                Text(title)
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.text)
                Text(body)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
        }
    }

    private var confirmTitle: String {
        switch confirm {
        case .receive: "Mark \(live.delivery?.reference ?? reference) as received?"
        case .finish: "Set \(live.delivery?.reference ?? reference) as booked in?"
        case .notReceived(_, let palletReference): "Set \(palletReference) as not received?"
        case .undoNotReceived(_, let palletReference): "Undo not received for \(palletReference)?"
        case nil: ""
        }
    }

    private var confirmMessage: String {
        switch confirm {
        case .receive: "Every pallet in this delivery is set as received."
        case .finish: "Booked-in pallets start storing at their locations. Pallets set as not received stay as they are."
        case .notReceived: "Use this when the pallet did not arrive with this delivery."
        case .undoNotReceived: "The pallet is set back to received."
        case nil: ""
        }
    }

    private func confirmButton(_ delivery: MobilePalletDeliveryDetail) -> String {
        switch confirm {
        case .receive: delivery.action("receive")?.label ?? "Mark as received"
        case .finish: delivery.action("finish_booking")?.label ?? "Set as booked in"
        case .notReceived: "Not received"
        case .undoNotReceived: "Undo not received"
        case nil: "OK"
        }
    }

    private func run(_ confirm: PalletDeliveryConfirm) async {
        switch confirm {
        case .receive: await live.receive()
        case .finish: await live.finishBooking()
        case .notReceived(let id, _): await live.notReceived(id)
        case .undoNotReceived(let id, _): await live.undoNotReceived(id)
        }
    }

    private func scanForBookIn(_ code: String, delivery: MobilePalletDeliveryDetail) async {
        if case .notOnDelivery(let scanned) = await live.scanForBookIn(code) {
            toasts.show("\(scanned) is not a pallet of \(delivery.reference). Scan the pallet label first.", tone: .info)
        }
    }
}

private struct PalletItem: Identifiable {
    let pallet: MobileDeliveryPallet
    var id: Int { pallet.id }
}

private struct ChargeItem: Identifiable {
    let index: Int
    let charge: MobilePalletDeliveryCharge
    var id: Int { index }
}

extension MobileDeliveryPalletStoredItem: Identifiable {}

struct FulfilmentCodeCamera: View {
    let title: String
    let onClose: () -> Void
    let onCode: (String) -> Void
    @State private var cameraAllowed: Bool?
    @State private var typedCode = ""

    var body: some View {
        ZStack {
            if cameraAllowed == true, QRCodeScanner.isAvailable {
                QRCodeScanner(symbologies: QRCodeScanner.warehouseSymbologies) { code in
                    onCode(code)
                }
                .ignoresSafeArea()
            } else {
                Color.black.ignoresSafeArea()
            }
            VStack(spacing: 16) {
                HStack {
                    Text(title)
                        .font(.aiku(17, .bold))
                        .foregroundStyle(.white)
                    Spacer()
                    Button(action: onClose) {
                        AppIcon.close.image
                            .font(.system(size: 20, weight: .semibold))
                            .foregroundStyle(.white)
                            .frame(width: 44, height: 44)
                    }
                    .accessibilityLabel("Close")
                }
                .padding(.horizontal, 16)
                Spacer()
                ScanViewfinder()
                if cameraAllowed == false || !QRCodeScanner.isAvailable {
                    Text("The camera is not available. Type the code instead.")
                        .font(.aiku(14))
                        .foregroundStyle(.white)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 24)
                    SearchField(text: $typedCode, placeholder: "Type the code", onSubmit: {
                        let code = typedCode.trimmingCharacters(in: .whitespacesAndNewlines)
                        if !code.isEmpty { onCode(code) }
                    })
                    .padding(.horizontal, 16)
                }
                Spacer()
            }
        }
        .task {
            if cameraAllowed == nil {
                cameraAllowed = await CameraPermission.request()
            }
        }
    }
}
