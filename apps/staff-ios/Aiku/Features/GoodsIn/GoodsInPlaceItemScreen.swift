import SwiftUI

enum GoodsInPlaceChoice: Hashable {
    case link(locationOrgStockID: Int)
    case another
}

extension MobileStockDeliveryItem {
    var preferredChoice: GoodsInPlaceChoice {
        preferredLocation.map { .link(locationOrgStockID: $0.locationOrgStockId) } ?? .another
    }
}

struct GoodsInPlaceItemScreen: View {
    let itemID: Int
    let skoCode: String
    let feed: GoodsInDeliveryFeed
    @State var finder: GoodsInLocationFinder
    @Environment(AppRouter.self) private var router

    var body: some View {
        GoodsInAccessGate(isAllowed: \.viewGoodsIn) {
            if let delivery = feed.delivery, let item = delivery.item(itemID) {
                GoodsInPlaceItemContent(feed: feed, finder: finder, delivery: delivery, item: item)
            } else if feed.delivery != nil {
                Color.clear.onAppear { router.back() }
            } else {
                GoodsInLoadingScreen(
                    title: L10n.format("Place %@", skoCode),
                    kicker: "Stock delivery",
                    failedTitle: L10n.format("Place %@ could not be loaded", skoCode),
                    problem: feed.loadError,
                    retry: { Task { await feed.load() } }
                )
            }
        }
        .onAppear {
            Task { await feed.load() }
        }
    }
}

private struct GoodsInPlaceItemContent: View {
    let feed: GoodsInDeliveryFeed
    let finder: GoodsInLocationFinder
    let delivery: MobileStockDelivery
    let item: MobileStockDeliveryItem
    @Environment(AppRouter.self) private var router
    @State private var choice: GoodsInPlaceChoice
    @State private var quantity: Double
    @State private var setAsPicking: Bool
    @State private var isLocationMissing = false

    init(feed: GoodsInDeliveryFeed, finder: GoodsInLocationFinder, delivery: MobileStockDelivery, item: MobileStockDeliveryItem) {
        self.feed = feed
        self.finder = finder
        self.delivery = delivery
        self.item = item
        _choice = State(initialValue: item.preferredChoice)
        _quantity = State(initialValue: item.skoToPlace)
        _setAsPicking = State(initialValue: item.locations.isEmpty)
    }

    private var remaining: Double { item.skoToPlace }
    private var shownQuantity: Double { min(max(quantity, 0), max(remaining, 0)) }
    private var place: MobileGoodsInAction? { delivery.availableActions.action(GoodsInActionKey.place) }

    private var chosenLink: MobileGoodsInItemLocation? {
        guard case .link(let id) = choice else { return nil }
        return item.locations.first { $0.locationOrgStockId == id }
    }

    private var target: GoodsInPlaceTarget? {
        if let chosenLink {
            return .link(locationOrgStockID: chosenLink.locationOrgStockId, code: chosenLink.location.code)
        }
        guard choice == .another, let picked = finder.picked else { return nil }
        if let linked = item.locations.first(where: { $0.location.id == picked.id }) {
            return .link(locationOrgStockID: linked.locationOrgStockId, code: linked.location.code)
        }
        return .location(locationID: picked.id, code: picked.code)
    }

    private var fieldError: String? {
        ["quantity", "location_org_stock_id", "location_id"].lazy.compactMap { feed.writeErrors[$0] }.first
    }

    var body: some View {
        screen
            .onAppear { finder.reset() }
            .hardwareScanner(isEnabled: choice != .another) { code in
                scanned(code)
            }
            .goodsInEvents(
                feed.event,
                onHandled: { feed.event = nil },
                doneText: placedText,
                onDone: afterDone
            )
    }

    @ViewBuilder
    private var screen: some View {
        let title = L10n.format("Place %@", item.code)
        let kicker = L10n.format("%@ · Booking in", delivery.reference)
        AppScreen(title: title, kicker: kicker) {
            content
        } footer: {
            footer
        }
    }

    @ViewBuilder
    private var footer: some View {
        if let place {
            if place.isDisabled {
                GoodsInDisabledNotice(action: place, fallbackTitle: "Place")
            } else if !item.canBePlaced {
                GoodsOutFooterStatus(title: "Placed")
            } else {
                HStack(spacing: 8) {
                    AikuButton("Place all", icon: .checkDouble, kind: .secondary, isLoading: isWorkingPlaceAll, fullWidth: false) {
                        run(isAll: true)
                    }
                    .disabled(feed.isWorking)
                    .accessibilityIdentifier("goodsIn.place.all")
                    AikuButton(placeTitle, look: GoodsInActionKey.look(for: GoodsInActionKey.place), isLoading: isWorkingPlace) {
                        run(isAll: false)
                    }
                    .disabled(feed.isWorking)
                    .accessibilityIdentifier("goodsIn.place.save")
                }
            }
        } else {
            GoodsOutFooterStatus(title: delivery.stateLabel, detail: "Nothing can be placed on this delivery now.")
        }
    }

    private var placeTitle: String {
        if let target { return L10n.format("Place %@ in %@", GoodsInText.number(shownQuantity), target.code) }
        return L10n.format("Place %@", GoodsInText.number(shownQuantity))
    }

    private var isWorkingPlace: Bool {
        if case .place = feed.working { return true }
        return false
    }

    private var isWorkingPlaceAll: Bool {
        if case .placeAll = feed.working { return true }
        return false
    }

    @ViewBuilder
    private var content: some View {
        if let problem = feed.loadError {
            GoodsOutLoadProblem(title: "The stock delivery could not be refreshed", message: problem) {
                Task { await feed.load() }
            }
        }
        if feed.unsurePlacement != nil {
            InlineAlert(tone: .warn, title: "The placement may not be saved", message: "The connection dropped before aiku answered. Try again: aiku places it only once.") {
                LinkButton("Try again", size: 13) {
                    Task { await feed.retryPlacement() }
                }
                .frame(minHeight: 44, alignment: .leading)
                .accessibilityIdentifier("goodsIn.place.retry")
            }
        }
        itemCard
        GoodsOutSectionTitle(title: "Where does it go?")
        ForEach(item.locations, id: \.locationOrgStockId) { location in
            GoodsInChoiceRow(
                title: location.location.code,
                subtitle: detail(location),
                isSelected: choice == .link(locationOrgStockID: location.locationOrgStockId),
                identifier: "goodsIn.place.location.\(location.location.code)"
            ) {
                isLocationMissing = false
                choice = .link(locationOrgStockID: location.locationOrgStockId)
            }
        }
        GoodsInChoiceRow(
            title: "Another location",
            subtitle: choice == .another ? (finder.picked?.code ?? "Scan it, or search the location code") : "Scan it, or search the location code",
            isSelected: choice == .another,
            identifier: "goodsIn.place.another"
        ) {
            isLocationMissing = false
            choice = .another
        }
        if choice == .another {
            GoodsInLocationPicker(finder: finder) { code in
                if let linked = item.locations.first(where: { $0.location.code.caseInsensitiveCompare(code.trimmingCharacters(in: .whitespaces)) == .orderedSame }) {
                    choice = .link(locationOrgStockID: linked.locationOrgStockId)
                } else {
                    Task { await finder.scan(code) }
                }
            }
        }
        if isLocationMissing {
            Text("Choose a location first.")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.danger)
                .accessibilityIdentifier("goodsIn.place.locationMissing")
        }
        if remaining > 0 {
            GoodsOutSectionTitle(title: "Quantity", detail: L10n.format("up to %@", GoodsInText.quantity(remaining, label: item.skoToPlaceLabel)))
            GoodsInQuantityStepper(value: shownQuantity, minimum: min(1, remaining), maximum: remaining) { quantity = $0 }
        }
        if let fieldError {
            Text(fieldError)
                .font(.aiku(13))
                .foregroundStyle(AikuColor.danger)
                .accessibilityIdentifier("goodsIn.place.fieldError")
        }
        if !item.hasPickingLocation {
            setAsPickingRow
            GoodsInHelper("Ticked by default when this would be the SKO’s only location.")
        } else {
            pickingDefaults
        }
        GoodsInHelper("Place part here and the rest in another location by lowering the quantity.")
    }

    private var itemCard: some View {
        HStack(spacing: 12) {
            GoodsInPhoto(code: item.code, imageURL: item.sko?.imageUrl, size: 72)
            VStack(alignment: .leading, spacing: 2) {
                Text(item.code)
                    .font(.aiku(16, .bold))
                    .foregroundStyle(AikuColor.text)
                Text("\(item.name) · \(GoodsInText.quantity(remaining, label: item.skoToPlaceLabel)) to place")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            if !item.hasPickingLocation {
                StatusChip("No picking location yet", tone: .warn)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .cardSurface()
        .accessibilityElement(children: .combine)
    }

    private var setAsPickingRow: some View {
        Button {
            setAsPicking.toggle()
        } label: {
            HStack(spacing: 12) {
                VStack(alignment: .leading, spacing: 2) {
                    Text("Set as picking location")
                        .font(.aiku(15, .semibold))
                        .foregroundStyle(AikuColor.text)
                    Text("This SKO has no picking location yet")
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.warnInk)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                Image(systemName: setAsPicking ? "checkmark.square.fill" : "square")
                    .font(.system(size: 22))
                    .foregroundStyle(setAsPicking ? AikuColor.primary : AikuColor.borderStrong)
                    .accessibilityHidden(true)
            }
            .padding(.leading, 14)
            .padding(.trailing, 12)
            .frame(minHeight: 56)
            .background(AikuColor.warnSoft, in: RoundedRectangle(cornerRadius: 10))
            .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.warnBd))
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(setAsPicking ? [.isSelected, .isButton] : .isButton)
        .accessibilityIdentifier("goodsIn.place.setAsPicking")
    }

    @ViewBuilder
    private var pickingDefaults: some View {
        let lines = [
            item.locations.first(where: \.isDefaultWholesale).map { L10n.format("%@ for wholesale", $0.location.code) },
            item.locations.first(where: \.isDefaultDropshipping).map { L10n.format("%@ for dropshipping", $0.location.code) },
        ].compactMap { $0 }
        if !lines.isEmpty {
            Card {
                GoodsInActionLinkRow(icon: .pin, title: "Picking location", subtitle: lines.joined(separator: GoodsInText.separator), tone: .primary)
            }
        }
    }

    private func detail(_ location: MobileGoodsInItemLocation) -> String {
        [
            location.type == "picking" ? L10n.text("Picking location") : location.type == "storing" ? L10n.text("Storing") : nil,
            location.isDefaultWholesale ? L10n.text("Wholesale") : nil,
            location.isDefaultDropshipping ? L10n.text("Dropshipping") : nil,
            L10n.format("%@ in location", GoodsInText.quantity(location.quantity, label: location.quantityLabel)),
        ].compactMap { $0 }.joined(separator: GoodsInText.separator)
    }

    private func scanned(_ code: String) {
        isLocationMissing = false
        if let linked = item.locations.first(where: { $0.location.code.caseInsensitiveCompare(code.trimmingCharacters(in: .whitespaces)) == .orderedSame }) {
            choice = .link(locationOrgStockID: linked.locationOrgStockId)
        } else {
            choice = .another
            Task { await finder.scan(code) }
        }
    }

    private func run(isAll: Bool) {
        guard let target else {
            isLocationMissing = true
            return
        }
        let picking = setAsPicking && !item.hasPickingLocation
        Task {
            if isAll {
                await feed.placeAll(itemID: item.id, target: target, setAsPickingLocation: picking)
            } else {
                await feed.place(itemID: item.id, quantity: shownQuantity, target: target, setAsPickingLocation: picking)
            }
        }
    }

    private func placedText() -> String? {
        let current = feed.delivery ?? delivery
        switch feed.lastWrite {
        case let .place(itemID, quantity, target, _, _):
            return L10n.format("%@ of %@ placed in %@", GoodsInText.number(quantity), current.item(itemID)?.code ?? item.code, target.code)
        case let .placeAll(itemID, target, _, _):
            return L10n.format("All of %@ placed in %@", current.item(itemID)?.code ?? item.code, target.code)
        default:
            return nil
        }
    }

    private func afterDone() {
        guard let write = feed.lastWrite, write.isPlacement else { return }
        if let after = feed.delivery?.item(item.id), after.canBePlaced {
            quantity = after.skoToPlace
        } else {
            router.back()
        }
    }
}

struct GoodsInLocationPicker: View {
    let finder: GoodsInLocationFinder
    let onScanned: (String) -> Void
    @State private var query = ""
    @State private var isCameraOn = false
    @State private var cameraAllowed: Bool?

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            SearchField(text: $query, placeholder: "Search or scan a location", onScan: { isCameraOn.toggle() })
                .onChange(of: query) { _, text in
                    if text != finder.query { finder.search(text) }
                }
                .onChange(of: finder.query) { _, text in
                    if text != query { query = text }
                }
                .accessibilityIdentifier("goodsIn.place.search")
            if isCameraOn {
                camera
            }
            if let code = finder.lookingUp {
                InlineAlert(tone: .info, title: L10n.format("Looking up %@…", code))
            }
            if let code = finder.notALocation {
                InlineAlert(tone: .warn, title: L10n.format("%@ is not a location in this warehouse", code))
                    .accessibilityIdentifier("goodsIn.place.notALocation")
            }
            if let problem = finder.problem {
                InlineAlert(tone: .danger, title: problem)
            }
            if finder.isSearching {
                GoodsOutSkeletonCard(rows: 2)
            } else if !finder.results.isEmpty {
                Card {
                    ForEach(Array(finder.results.enumerated()), id: \.element.id) { index, location in
                        if index > 0 {
                            RowDivider()
                        }
                        ListRow(
                            location.code,
                            subtitle: GoodsInLocationFinder.areaText(location.area),
                            chips: finder.picked?.id == location.id ? [Chip("Chosen", .success)] : [],
                            action: { finder.pick(location) }
                        )
                        .accessibilityIdentifier("goodsIn.place.result.\(location.code)")
                    }
                }
            } else if !query.trimmingCharacters(in: .whitespaces).isEmpty, finder.lookingUp == nil, finder.notALocation == nil, finder.problem == nil {
                GoodsInHelper(L10n.format("No location matches “%@”.", query))
            }
        }
        .hardwareScanner { code in onScanned(code) }
        .task(id: isCameraOn) {
            if isCameraOn, cameraAllowed == nil {
                cameraAllowed = await CameraPermission.request()
            }
        }
    }

    @ViewBuilder
    private var camera: some View {
        if cameraAllowed == true, QRCodeScanner.isAvailable {
            QRCodeScanner(symbologies: QRCodeScanner.warehouseSymbologies) { code in
                isCameraOn = false
                onScanned(code)
            }
            .frame(height: 190)
            .clipShape(RoundedRectangle(cornerRadius: 12))
            .accessibilityIdentifier("goodsIn.place.camera")
        } else if cameraAllowed == false {
            GoodsInHelper("Camera access is off. Allow it in Settings, or type the code.")
        } else if cameraAllowed == true {
            GoodsInHelper("This device can’t scan barcodes. Type the code instead.")
        }
    }
}

struct GoodsInTypeCodeSheet: View {
    let title: String
    let subtitle: String
    let label: String
    let actionTitle: String
    let onSubmit: (String) -> Void
    let onCancel: () -> Void
    @State private var code = ""

    private var trimmed: String { code.trimmingCharacters(in: .whitespacesAndNewlines) }

    var body: some View {
        SheetScaffold(title: title, subtitle: subtitle) {
            AikuTextField(label, text: $code)
                .onSubmit(submit)
                .accessibilityIdentifier("goodsIn.typeCode.field")
        } footer: {
            AikuButton(actionTitle, icon: .search) { submit() }
                .disabled(trimmed.isEmpty)
                .accessibilityIdentifier("goodsIn.typeCode.submit")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }

    private func submit() {
        guard !trimmed.isEmpty else { return }
        onSubmit(trimmed)
    }
}
