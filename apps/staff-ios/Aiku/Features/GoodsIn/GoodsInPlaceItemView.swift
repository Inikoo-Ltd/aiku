import SwiftUI

struct GoodsInPlaceItemView: View {
    let reference: String
    let sko: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var selectedLocation: String?
    @State private var typedLocation: String?
    @State private var quantity: Double?
    @State private var codeRequest: GoodsInCodeRequest?
    private let store = GoodsInStore.shared

    private var delivery: GoodsInDelivery { store.delivery(reference) }
    private var item: GoodsInItem { delivery.item(sko) ?? delivery.items[0] }
    private var location: String? { selectedLocation ?? item.pickingLocation ?? item.locationOptions.first?.code }
    private var amount: Double { min(quantity ?? item.leftToPlace, item.leftToPlace) }

    var body: some View {
        Group {
            if item.leftToPlace > GoodsInQuantity.tolerance {
                AppScreen(title: "Place \(item.sko)", kicker: "\(reference) · Booking in") {
                    content
                } footer: {
                    HStack(spacing: 8) {
                        AikuButton("Place all", icon: .checkDouble, kind: .secondary) { place(item.leftToPlace) }
                            .accessibilityIdentifier("goodsIn.place.all")
                        AikuButton(location.map { "Place \(item.text(amount)) in \($0)" } ?? "Choose a location", look: GoodsInActionKey.look(for: GoodsInActionKey.place)) { place(amount) }
                            .disabled(location == nil || amount == 0)
                            .accessibilityIdentifier("goodsIn.place.confirm")
                    }
                }
            } else {
                AppScreen(title: "Place \(item.sko)", kicker: "\(reference) · Booking in") {
                    GoodsInPlacedItemCard(item: item)
                    InlineAlert(tone: .success, icon: .checkCircle, title: "All placed", message: item.placementSummary)
                }
            }
        }
        .aikuSheet(item: $codeRequest) { _ in
            GoodsInTypeCodeSheet(
                title: "Another location",
                subtitle: "Scan it, or type the location code",
                label: "Location code",
                placeholder: "C-04-02",
                actionTitle: "Use location"
            ) { code in
                typedLocation = code
                selectedLocation = code
                codeRequest = nil
            } onCancel: {
                codeRequest = nil
            }
        }
    }

    @ViewBuilder
    private var content: some View {
        GoodsInPlacedItemCard(item: item)
        SectionHeading("Where does it go?")
        VStack(spacing: 8) {
            ForEach(item.locationOptions) { option in
                RadioCard(option.code, subtitle: option.detail, isSelected: location == option.code) {
                    selectedLocation = option.code
                }
            }
            if let typedLocation, !item.locationOptions.contains(where: { $0.code == typedLocation }) {
                RadioCard(typedLocation, subtitle: "Typed location", isSelected: location == typedLocation) {
                    selectedLocation = typedLocation
                }
            }
            RadioCard("Another location", subtitle: "Scan it, or search the location code", isSelected: false) {
                codeRequest = GoodsInCodeRequest(purpose: .location)
            }
            .accessibilityIdentifier("goodsIn.place.anotherLocation")
        }
        HStack {
            VStack(alignment: .leading, spacing: 2) {
                Text("Quantity")
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                Text("up to \(item.text(item.leftToPlace))")
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
            }
            Spacer(minLength: 8)
            GoodsInStepper(value: Binding(get: { amount }, set: { quantity = $0 }), range: 1...max(item.leftToPlace, 1), packedIn: item.packedIn)
        }
        .padding(16)
        .cardSurface()
        Card {
            ListRow("Picking location", subtitle: item.pickingSummary ?? "None yet", icon: .locations, iconTone: .info, action: {
                router.open(InventoryRoute.sko(reference: item.sko))
            }) {
                Text("Change")
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.link)
            }
        }
        HelperText("Place part here and the rest in another location by lowering the quantity. Set as picking location is offered when the SKO has no picking location yet (see SKO-02210 on the list).")
            .padding(.horizontal, 4)
    }

    private func place(_ requested: Double) {
        guard let location else { return }
        let before = delivery.state
        let placed = store.place(reference, sko: item.sko, quantity: requested, in: location)
        guard placed > GoodsInQuantity.tolerance else { return }
        GoodsInPlacementToast.show(placed: placed, packedIn: item.packedIn, location: location, reference: reference, before: before, after: delivery.state, toasts: toasts)
        quantity = nil
        if GoodsInQuantity.isZero(item.leftToPlace) {
            router.back()
        }
    }
}

struct GoodsInPlaceNewItemView: View {
    let reference: String
    let sko: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var scannedLocation: GoodsInLocationOption?
    @State private var setsAsPicking: Bool?
    @State private var codeRequest: GoodsInCodeRequest?
    private let store = GoodsInStore.shared

    private var delivery: GoodsInDelivery { store.delivery(reference) }
    private var item: GoodsInItem { delivery.item(sko) ?? delivery.items[0] }
    private var location: GoodsInLocationOption? { scannedLocation ?? item.suggestedLocation }
    private var isSettingPicking: Bool { setsAsPicking ?? item.locationOptions.isEmpty }

    var body: some View {
        Group {
            if item.leftToPlace > GoodsInQuantity.tolerance {
                AppScreen(title: "Place \(item.sko)", kicker: "\(reference) · Booking in") {
                    content
                } footer: {
                    AikuButton(location.map { "Place \(item.text(item.leftToPlace)) in \($0.code)" } ?? "Scan a location first", look: GoodsInActionKey.look(for: GoodsInActionKey.place)) { place() }
                        .disabled(location == nil)
                        .accessibilityIdentifier("goodsIn.placeNew.confirm")
                }
            } else {
                AppScreen(title: "Place \(item.sko)", kicker: "\(reference) · Booking in") {
                    GoodsInPlacedItemCard(item: item)
                    InlineAlert(tone: .success, icon: .checkCircle, title: "All placed", message: item.placementSummary)
                }
            }
        }
        .aikuSheet(item: $codeRequest) { _ in
            GoodsInTypeCodeSheet(
                title: "Scan location",
                subtitle: "Type the location code when the label does not scan",
                label: "Location code",
                placeholder: "A-09-04",
                actionTitle: "Use location"
            ) { code in
                scannedLocation = GoodsInLocationOption(code: code, detail: "Typed location")
                codeRequest = nil
            } onCancel: {
                codeRequest = nil
            }
        }
    }

    @ViewBuilder
    private var content: some View {
        GoodsInPlacedItemCard(item: item, chip: Chip("No picking location yet", .warn))
        GoodsInScanCard(
            title: "Scan location",
            subtitle: "or search the location code",
            code: location?.code ?? "—",
            detail: location?.detail ?? "No location yet",
            identifier: "goodsIn.placeNew.scanLocation"
        ) {
            codeRequest = GoodsInCodeRequest(purpose: .location)
        }
        Button {
            setsAsPicking = !isSettingPicking
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
                Image(systemName: isSettingPicking ? "checkmark.square.fill" : "square")
                    .font(.system(size: 22))
                    .foregroundStyle(isSettingPicking ? AikuColor.primary : AikuColor.borderStrong)
            }
            .padding(.horizontal, 14)
            .frame(minHeight: 56)
            .background(AikuColor.warnSoft, in: RoundedRectangle(cornerRadius: 10))
            .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.warnBd))
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSettingPicking ? .isSelected : [])
        .accessibilityIdentifier("goodsIn.placeNew.setPicking")
        HelperText("Ticked by default when this would be the SKO’s only location.")
            .padding(.horizontal, 4)
    }

    private func place() {
        guard let location else { return }
        let before = delivery.state
        let placed = store.place(reference, sko: item.sko, quantity: item.leftToPlace, in: location.code, setAsPicking: isSettingPicking)
        guard placed > GoodsInQuantity.tolerance else { return }
        GoodsInPlacementToast.show(placed: placed, packedIn: item.packedIn, location: location.code, reference: reference, before: before, after: delivery.state, toasts: toasts)
        router.back()
    }
}

struct GoodsInPlacedItemCard: View {
    let item: GoodsInItem
    var chip: Chip?

    var body: some View {
        HStack(spacing: 12) {
            ProductThumb(size: 72, label: "Product photo of \(item.sko)")
            VStack(alignment: .leading, spacing: 2) {
                Text(item.sko)
                    .font(.aiku(16, .bold))
                    .foregroundStyle(AikuColor.text)
                Text(subtitle)
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            if let chip {
                StatusChip(chip)
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .cardSurface()
    }

    private var subtitle: String {
        let checked = "\(item.name) · \(item.text(item.checked ?? 0)) checked"
        guard item.hasPlacements, item.leftToPlace > GoodsInQuantity.tolerance else { return checked }
        return "\(checked) · \(item.text(item.leftToPlace)) left to place"
    }
}

enum GoodsInPlacementToast {
    @MainActor
    static func show(placed: Double, packedIn: Int, location: String, reference: String, before: GoodsInDeliveryState, after: GoodsInDeliveryState, toasts: ToastCenter) {
        if after == .bookedIn, before != .bookedIn {
            toasts.show("\(GoodsInQuantity.text(placed, packedIn: packedIn)) placed in \(location) · \(reference) is Booked in")
        } else {
            toasts.show("\(GoodsInQuantity.text(placed, packedIn: packedIn)) placed in \(location)")
        }
    }
}
