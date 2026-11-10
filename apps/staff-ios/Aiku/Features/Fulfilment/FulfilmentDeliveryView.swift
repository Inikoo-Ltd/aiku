import SwiftUI

struct FulfilmentDeliveryView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var isScanning = false
    @State private var scanFeedback: String?
    private let store = FulfilmentStore.shared

    var body: some View {
        if let delivery = store.delivery(reference) {
            screen(delivery)
        } else {
            AppScreen(title: reference) {
                EmptyState(icon: .stockDelivery, title: "Delivery not found")
            }
        }
    }

    @ViewBuilder
    private func screen(_ delivery: FulfilmentDelivery) -> some View {
        AppScreen(title: delivery.reference, kicker: kicker(delivery)) {
            content(delivery)
        } footer: {
            footer(delivery)
        }
        .fullScreenCover(isPresented: $isScanning) {
            camera(delivery)
        }
    }

    private func kicker(_ delivery: FulfilmentDelivery) -> String {
        delivery.state == .bookingIn ? "Fulfilment delivery · Booking in" : "Fulfilment delivery · \(delivery.customer)"
    }

    @ViewBuilder
    private func content(_ delivery: FulfilmentDelivery) -> some View {
        if let index = delivery.state.stepIndex {
            FulfilmentProgressSteps(steps: FulfilmentDeliveryState.steps.map(\.title), currentIndex: index)
        }
        switch delivery.state {
        case .confirmed, .received:
            arrivalContent(delivery)
        case .bookingIn:
            bookingContent(delivery)
        case .bookedIn:
            FulfilmentDoneHero(
                title: "\(delivery.reference) booked in",
                message: "\(FulfilmentItemType.pallet.counted(store.storedCount(of: delivery.reference))) are now Storing."
            )
            AikuButton("View stored pallets", kind: .tertiary) {
                router.open(FulfilmentRoute.pallets)
            }
            AikuButton("Next delivery") {
                router.back()
            }
            .accessibilityIdentifier("fulfilment.delivery.next")
        case .notReceived:
            InlineAlert(tone: .danger, icon: .warning, title: "Not received", message: "This delivery was set as not received.")
            palletGroups(delivery)
        }
    }

    @ViewBuilder
    private func arrivalContent(_ delivery: FulfilmentDelivery) -> some View {
        let count = delivery.palletReferences.count
        SectionHeading("Pallets", detail: delivery.state == .confirmed ? "\(count) expected" : "\(count) received")
        palletGroups(delivery)
        FulfilmentNote("Received comes from Receive on a confirmed delivery. Booking in starts with Start booking in. If nothing arrived, set the delivery as not received.")
    }

    private func palletGroups(_ delivery: FulfilmentDelivery) -> some View {
        CardList(FulfilmentPalletGroup.grouping(store.pallets(of: delivery))) { group in
            ListRow(group.title, subtitle: group.subtitle, chips: [Chip(group.typeTitle)]) {
                StatusChip(group.state.title, tone: group.state.tone)
            }
        }
    }

    @ViewBuilder
    private func bookingContent(_ delivery: FulfilmentDelivery) -> some View {
        let items = store.pallets(of: delivery)
        let location = store.suggestedLocation()
        if let current = store.currentBookingPallet(in: delivery.reference) {
            FulfilmentScanCard(
                title: "Scan pallet, then location",
                detail: palletLine(current),
                highlight: location,
                footnote: "Area \(location.prefix(1)) · fulfilment storage",
                identifier: "fulfilment.bookIn.scan"
            ) {
                scanFeedback = nil
                isScanning = true
            }
            HStack(spacing: 8) {
                AikuButton("Not received", kind: .tertiary) {
                    store.setPalletNotReceived(current.reference)
                    toasts.show("\(current.reference) set as not received", tone: .neutral)
                }
                AikuButton("Book in at \(location)") {
                    store.bookIn(current.reference, at: location)
                    toasts.show("\(current.reference) booked in at \(location)")
                }
                .accessibilityIdentifier("fulfilment.bookIn.confirm")
            }
        } else {
            InlineAlert(tone: .success, icon: .checkCircle, title: "Every pallet is handled", message: "Set the delivery as booked in.")
        }
        SectionHeading("Pallets", detail: "\(items.filter { $0.state == .bookedIn }.count) of \(items.count) booked in")
        CardList(items) { pallet in
            ListRow(
                pallet.reference,
                subtitle: "\(pallet.type.title) · \(pallet.location ?? "no location yet")",
                chips: [Chip(pallet.state.title, pallet.state.tone)]
            ) {
                if pallet.state == .bookedIn || pallet.state == .notReceived {
                    LinkButton("Undo") {
                        store.undoBooking(pallet.reference)
                    }
                    .accessibilityLabel("Undo \(pallet.reference)")
                }
            }
        }
        FulfilmentNote("The location is optional when booking in and can be set later. A booked-in or not-received pallet can be undone.")
    }

    private func palletLine(_ pallet: FulfilmentPallet) -> String {
        var parts = [pallet.reference, pallet.type.title]
        if let customerReference = pallet.customerReference {
            parts.append("“\(customerReference)”")
        }
        return parts.joined(separator: " · ")
    }

    @ViewBuilder
    private func footer(_ delivery: FulfilmentDelivery) -> some View {
        switch delivery.state {
        case .confirmed:
            HStack(spacing: 8) {
                AikuButton("Not received", kind: .danger) {
                    setNotReceived(delivery)
                }
                AikuButton("Receive") {
                    store.receive(delivery.reference)
                    toasts.show("\(delivery.reference) received")
                }
                .accessibilityIdentifier("fulfilment.delivery.receive")
            }
        case .received:
            HStack(spacing: 8) {
                AikuButton("Not received", kind: .danger) {
                    setNotReceived(delivery)
                }
                AikuButton("Start booking in") {
                    store.startBookingIn(delivery.reference)
                }
                .accessibilityIdentifier("fulfilment.delivery.startBookingIn")
            }
        case .bookingIn:
            AikuButton("Set as booked in · after the last pallet", kind: .secondary) {
                store.setBookedIn(delivery.reference)
                toasts.show("\(delivery.reference) booked in")
            }
            .disabled(!store.canSetBookedIn(delivery.reference))
            .accessibilityIdentifier("fulfilment.delivery.setBookedIn")
        case .bookedIn:
            HelperText("Booked in. Pallets are now Storing.")
        case .notReceived:
            HelperText("Not received. Nothing to book in.")
        }
    }

    private func setNotReceived(_ delivery: FulfilmentDelivery) {
        store.setDeliveryNotReceived(delivery.reference)
        toasts.show("\(delivery.reference) set as not received", tone: .neutral)
        router.back()
    }

    private func camera(_ delivery: FulfilmentDelivery) -> some View {
        let current = store.currentBookingPallet(in: delivery.reference)
        let location = store.suggestedLocation()
        return FulfilmentCameraScan(
            kicker: "Booking in · \(delivery.reference)",
            title: "Scan pallet, then location",
            feedback: scanFeedback,
            onClose: { isScanning = false },
            onScan: {
                guard let current else { return }
                store.bookIn(current.reference, at: location)
                scanFeedback = "\(current.reference) booked in at \(location)"
            }
        ) {
            if let current {
                Text("Next: \(current.reference) → \(location)")
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                    .frame(maxWidth: .infinity, alignment: .leading)
            } else {
                Text("Every pallet is handled")
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.success)
                    .frame(maxWidth: .infinity, alignment: .leading)
            }
        }
    }
}
