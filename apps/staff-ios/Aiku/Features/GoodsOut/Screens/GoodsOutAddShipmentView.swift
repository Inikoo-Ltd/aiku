import SwiftUI

struct GoodsOutAddShipmentView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var selected: String?
    @State private var activeSheet: GoodsOutNoteSheet?
    private let store = GoodsOutStore.shared

    var body: some View {
        if let note = store.note(reference) {
            AppScreen(title: "Add shipment", kicker: "Delivery note \(note.reference) · \(note.state.title)") {
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
                GoodsOutSectionTitle(title: "Other shippers")
                Card {
                    ListRow("Choose other shipper", subtitle: "Every shipper of this warehouse · tracking number typed by hand", icon: .search, iconTone: .neutral, showsChevron: true) {
                        router.open(GoodsOutRoute.manualShipment(reference: note.reference))
                    }
                }
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
                GoodsOutFeedback.done("\(option.title) shipment added · \(tracking)", previous: previous, toasts: toasts)
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
