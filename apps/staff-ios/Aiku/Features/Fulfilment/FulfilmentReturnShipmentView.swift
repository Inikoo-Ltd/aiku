import SwiftUI

struct FulfilmentReturnShipmentView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    private let store = FulfilmentStore.shared

    var body: some View {
        if let record = store.returnRecord(reference) {
            AppScreen(title: record.reference, kicker: "Fulfilment return · \(record.state.title)") {
                FulfilmentProgressSteps(steps: FulfilmentReturnState.allCases.map(\.title), currentIndex: record.state.stepIndex)
                if let shipment = record.shipment {
                    Card {
                        ListRow(
                            shipment.shipper,
                            subtitle: "Tracking \(shipment.tracking) · \(contents(record))",
                            icon: .deliveryNote,
                            iconTone: .success,
                            chips: [Chip("Label printed", .success)]
                        )
                    }
                } else {
                    EmptyState(icon: .deliveryNote, title: "No shipment yet")
                }
                FulfilmentNote("Shippers are the same as for delivery notes and are set up in the web app.")
            } footer: {
                if store.canDispatch(record.reference) {
                    AikuButton("Dispatch", icon: .deliveryNote) {
                        store.dispatch(record.reference)
                        toasts.show("\(record.reference) dispatched")
                        router.back()
                    }
                    .accessibilityIdentifier("fulfilment.return.dispatch")
                } else {
                    HelperText(record.state == .dispatched ? "Dispatched." : "Create the shipment first.")
                }
            }
        } else {
            AppScreen(title: reference) {
                EmptyState(icon: .deliveryNote, title: "Return not found")
            }
        }
    }

    private func contents(_ record: FulfilmentReturn) -> String {
        if record.type == .pallets {
            return FulfilmentItemType.pallet.counted(record.pickedPalletReferences.count)
        }
        return record.pickedUnits == 1 ? "1 item" : "\(record.pickedUnits) items"
    }
}
