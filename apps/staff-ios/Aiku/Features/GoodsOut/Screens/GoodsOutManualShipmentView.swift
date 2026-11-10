import SwiftUI

struct GoodsOutManualShipmentView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var search = ""
    @State private var selected: String?
    @State private var tracking = ""
    @State private var showsError = false
    private let store = GoodsOutStore.shared

    private var options: [GoodsOutShipperOption] {
        let query = search.trimmingCharacters(in: .whitespaces)
        guard !query.isEmpty else { return store.otherShipperOptions }
        return store.otherShipperOptions.filter { $0.title.localizedCaseInsensitiveContains(query) }
    }

    private var selectedOption: GoodsOutShipperOption? {
        let id = selected ?? store.otherShipperOptions.first?.id
        return store.otherShipperOptions.first { $0.id == id }
    }

    var body: some View {
        if let note = store.note(reference) {
            AppScreen(title: "Add shipment", kicker: "Delivery note \(note.reference) · \(note.state.title)") {
                GoodsOutStateStepper(state: note.state)
                SearchField(text: $search, placeholder: "Select shipping")
                if options.isEmpty {
                    EmptyState(icon: .search, title: "No shipper found", message: "Try another name.")
                } else {
                    VStack(spacing: 8) {
                        ForEach(options) { option in
                            GoodsOutShipperOptionRow(option: option, isSelected: selectedOption?.id == option.id) {
                                selected = option.id
                                showsError = false
                            }
                        }
                    }
                    .accessibilityElement(children: .contain)
                    .accessibilityLabel("Other shippers")
                }
                if selectedOption?.hasAPI == false {
                    AikuTextField(
                        "Tracking number",
                        text: $tracking,
                        placeholder: "[TRK-0099-1234]",
                        error: showsError ? "Enter the tracking number" : nil
                    )
                }
                HelperText("A shipper without API needs the tracking number. An API shipper creates the label and tracking number itself.")
            } footer: {
                if note.state == .packed, note.needsShipment {
                    AikuButton("Add shipment", icon: .deliveryNote) {
                        add(note)
                    }
                    .accessibilityIdentifier("goodsOut.addManualShipment")
                } else {
                    GoodsOutFooterStatus(title: "No shipment possible", detail: "Shipments are added to packed delivery notes that are not collected.")
                }
            }
        }
    }

    private func add(_ note: GoodsOutDeliveryNote) {
        guard let option = selectedOption else { return }
        let previous = store.addShipment(note.reference, shipper: option, tracking: tracking)
        guard previous != nil else {
            showsError = true
            return
        }
        let number = store.note(note.reference)?.shipments.last?.tracking ?? tracking
        GoodsOutFeedback.done("\(option.title) shipment added · \(number)", previous: previous, toasts: toasts)
        router.back()
        router.back()
    }
}
