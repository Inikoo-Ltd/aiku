import SwiftUI

struct FulfilmentIncidentsView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    private let store = FulfilmentStore.shared

    var body: some View {
        AppScreen(title: "Incidents", kicker: "Stored pallets") {
            ModuleTabs(.inventory, current: .storedPallets)
        } content: {
            if store.incidents.isEmpty {
                EmptyState(icon: .checkCircle, title: "No pallet incidents")
            } else {
                CardList(store.incidents) { incident in
                    ListRow(
                        incident.palletReference,
                        subtitle: incident.subtitle,
                        chips: [Chip(incident.kind.title, incident.kind.palletState.tone)],
                        action: { router.open(FulfilmentRoute.pallet(reference: incident.palletReference)) }
                    )
                }
            }
            FulfilmentNote("An incident clears the pallet’s location. Set back to storing when it is resolved.")
            if let first = store.incidents.first {
                AikuButton("Set \(first.palletReference) back to storing", kind: .tertiary) {
                    store.setBackToStoring(first.palletReference)
                    toasts.show("\(first.palletReference) is Storing again")
                }
                .accessibilityIdentifier("fulfilment.incident.resolve")
            }
        }
    }
}
