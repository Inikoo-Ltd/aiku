import SwiftUI

struct FulfilmentReturnShipmentView: View {
    let returnID: Int
    let reference: String
    @Environment(\.apiClient) private var apiClient
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        if let warehouseID = warehouseStore.selected?.id {
            ReturnShipmentScreen(live: PalletReturnLive(warehouseID: warehouseID, returnID: returnID, apiClient: apiClient), reference: reference)
                .id("\(warehouseID)-\(returnID)")
        } else {
            AppScreen(title: "Add shipment", kicker: reference) {
                EmptyState(icon: .goodsOut, title: "Choose a warehouse first")
            }
        }
    }
}

struct ReturnParcelDraft: Identifiable, Equatable {
    let id = UUID()
    var weight = ""
    var length = ""
    var width = ""
    var height = ""

    static func number(_ text: String) -> Double? {
        Double(text.trimmingCharacters(in: .whitespacesAndNewlines).replacingOccurrences(of: ",", with: "."))
    }

    static func from(_ parcel: MobileReturnParcel) -> ReturnParcelDraft {
        var draft = ReturnParcelDraft()
        draft.weight = GoodsInQuantity.decimal(parcel.weight)
        let dimensions = parcel.dimensions.map(GoodsInQuantity.decimal)
        if dimensions.count == 3 {
            draft.length = dimensions[0]
            draft.width = dimensions[1]
            draft.height = dimensions[2]
        }
        return draft
    }

    var problem: String? {
        guard let weight = Self.number(weight) else { return "Enter the weight." }
        guard weight > 0, weight <= 1000 else { return "The weight must be above 0 and at most 1000 kg." }
        let sides = [length, width, height].map(Self.number)
        guard sides.allSatisfy({ $0 != nil }) else { return "Enter the length, width and height." }
        guard sides.compactMap({ $0 }).allSatisfy({ $0 > 0 && $0 <= 1000 }) else { return "Each side must be above 0 and at most 1000 cm." }
        return nil
    }

    var body: MobileReturnParcelBody? {
        guard problem == nil, let weight = Self.number(weight) else { return nil }
        return MobileReturnParcelBody(weightKg: weight, dimensionsCm: [length, width, height].compactMap(Self.number))
    }
}

private struct ReturnShipmentScreen: View {
    @State var live: PalletReturnLive
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var chosenID: Int?
    @State private var tracking = ""
    @State private var search = ""
    @State private var trackingMissing = false
    @State private var parcels: [ReturnParcelDraft] = []
    @State private var showsParcelProblems = false
    @State private var savedParcels: [ReturnParcelDraft] = []

    var body: some View {
        Group {
            if let detail = live.detail {
                loaded(detail)
            } else {
                AppScreen(title: "Add shipment", kicker: reference) {
                    if let message = live.loadError {
                        InlineAlert(title: "Couldn’t load \(reference)", message: message)
                    } else {
                        SkeletonRow()
                        SkeletonRow()
                    }
                }
            }
        }
        .task {
            await live.load()
            await live.loadShippers()
            let saved = (live.detail?.parcels ?? []).map(ReturnParcelDraft.from)
            savedParcels = saved
            parcels = saved.isEmpty ? [ReturnParcelDraft()] : saved
        }
        .onChange(of: live.event) { _, event in
            guard case .done(let message, let tone) = event else { return }
            toasts.show(message, tone: tone)
            live.clearEvent()
        }
    }

    @ViewBuilder
    private func loaded(_ detail: MobilePalletReturnDetail) -> some View {
        let canAdd = detail.action("add_shipment") != nil
        let list = live.shippers
        let selected = list.available.first { $0.id == chosenID }
        let kicker = PalletDeliveryText.joined(["Fulfilment return", detail.reference])
        if canAdd, let selected {
            AppScreen(title: "Add shipment", kicker: kicker) {
                form(detail, list: list, selected: selected)
            } footer: {
                AikuButton(selected.isAPI ? "Create label" : "Add shipment", icon: .goodsOut) {
                    Task { await submit(selected) }
                }
                .disabled(live.isWorking)
                .accessibilityIdentifier("fulfilment.shipment.add")
            }
        } else {
            AppScreen(title: "Add shipment", kicker: kicker) {
                if canAdd {
                    form(detail, list: list, selected: nil)
                } else {
                    InlineAlert(tone: .info, title: "No shipment can be added", message: "Shipments are added while the return is being picked or is picked, unless the customer collects.")
                    FulfilmentNote("Shippers are the same as for delivery notes and are set up in the web app.")
                }
            }
        }
    }

    @ViewBuilder
    private func form(_ detail: MobilePalletReturnDetail, list: GoodsOutShipperList, selected: GoodsOutShipperChoice?) -> some View {
        if live.isLoadingShippers, list.available.isEmpty {
            SkeletonRow()
            SkeletonRow()
        } else if let message = live.shippersError {
            InlineAlert(title: "Couldn’t load the shippers", message: message)
        } else if list.available.isEmpty {
            HelperText("No shipper is available for this return.")
        } else {
            if !list.apiShippers.isEmpty {
                SectionHeading("API shippers")
                ForEach(list.apiShippers) { shipper in
                    shipperRow(shipper, selected: selected)
                }
            }
            if !list.manualShippers.isEmpty {
                SectionHeading("Other shippers")
                if list.manualShippers.count >= 7 {
                    SearchField(text: $search, placeholder: "Search shippers")
                }
                ForEach(list.manualShippers.filter { matches($0, selected: selected) }) { shipper in
                    shipperRow(shipper, selected: selected)
                }
            }
        }
        if let selected, !selected.isAPI {
            AikuTextField(
                selected.needsTracking ? "Tracking number" : "Tracking number (optional)",
                text: $tracking,
                error: trackingMissing ? "Enter the tracking number." : nil
            )
            .accessibilityIdentifier("fulfilment.shipment.tracking")
        }
        if selected?.isAPI == true {
            parcelsEditor
        }
        if let message = live.sheetError {
            InlineAlert(title: message)
        }
        FulfilmentNote("Shippers are the same as for delivery notes and are set up in the web app.")
    }

    private func matches(_ shipper: GoodsOutShipperChoice, selected: GoodsOutShipperChoice?) -> Bool {
        let needle = search.trimmingCharacters(in: .whitespacesAndNewlines)
        return needle.isEmpty || shipper.name.localizedCaseInsensitiveContains(needle) || shipper.code.localizedCaseInsensitiveContains(needle) || shipper.id == selected?.id
    }

    private func shipperRow(_ shipper: GoodsOutShipperChoice, selected: GoodsOutShipperChoice?) -> some View {
        GoodsOutShipperOptionRow(option: shipper.option, icon: .goodsOut, isSelected: shipper.id == selected?.id) {
            chosenID = shipper.id
            trackingMissing = false
        }
        .accessibilityIdentifier("fulfilment.shipment.shipper.\(shipper.code.isEmpty ? "\(shipper.id)" : shipper.code)")
    }

    @ViewBuilder
    private var parcelsEditor: some View {
        SectionHeading("Parcels · \(parcels.count)", detail: "kg · cm")
        ForEach($parcels) { $parcel in
            let index = parcels.firstIndex { $0.id == parcel.id } ?? 0
            VStack(alignment: .leading, spacing: 6) {
                HStack {
                    Text("Parcel \(index + 1)")
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Spacer()
                    if parcels.count > 1 {
                        IconButton(.close, label: "Remove parcel \(index + 1)") {
                            parcels.removeAll { $0.id == parcel.id }
                        }
                    }
                }
                HStack(spacing: 6) {
                    AikuTextField("kg", text: $parcel.weight, keyboard: .decimalPad)
                    AikuTextField("L", text: $parcel.length, keyboard: .decimalPad)
                    AikuTextField("W", text: $parcel.width, keyboard: .decimalPad)
                    AikuTextField("H", text: $parcel.height, keyboard: .decimalPad)
                }
                if showsParcelProblems, let problem = parcel.problem {
                    FieldError(problem)
                }
            }
            .padding(12)
            .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 12))
            .overlay(RoundedRectangle(cornerRadius: 12).stroke(AikuColor.border))
        }
        if parcels.count < 50 {
            LinkButton(parcels.isEmpty ? "Add the first parcel" : "Add parcel") {
                parcels.append(ReturnParcelDraft())
            }
        }
    }

    private func submit(_ shipper: GoodsOutShipperChoice) async {
        if shipper.isAPI {
            guard !parcels.isEmpty, parcels.allSatisfy({ $0.problem == nil }) else {
                showsParcelProblems = true
                return
            }
            let changed = parcels.map(\.body) != savedParcels.map(\.body)
            if await live.createShipment(shipperID: shipper.id, tracking: nil, parcels: changed ? parcels.compactMap(\.body) : nil) {
                router.back()
            }
        } else {
            let trimmed = tracking.trimmingCharacters(in: .whitespacesAndNewlines)
            if shipper.needsTracking, trimmed.isEmpty {
                trackingMissing = true
                return
            }
            if await live.createShipment(shipperID: shipper.id, tracking: trimmed, parcels: nil) {
                router.back()
            }
        }
    }
}
