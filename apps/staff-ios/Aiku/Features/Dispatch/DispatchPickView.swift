import SwiftUI

struct DispatchPickView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var isScanning = false
    @State private var isReporting = false
    @State private var hasScanned = false
    @State private var scanFeedback: String?
    private let store = DispatchStore.shared

    var body: some View {
        if let item = store.session(reference) {
            screen(item)
        } else {
            AppScreen(title: "Pick by location") {
                EmptyState(icon: .trolley, title: "Picking session not found")
            }
        }
    }

    private func screen(_ item: DispatchPickingSession) -> some View {
        AppScreen(
            title: "Pick by location",
            kicker: "\(item.reference) · \(item.state.title) · \(item.pickedCount) of \(item.itemCount)",
            actions: [HeaderAction(.warning, label: "Report a problem") { isReporting = true }]
        ) {
            if let location = item.currentLocation {
                DispatchScanCard(
                    title: "Scan to pick",
                    detail: "Take it from this location",
                    highlight: location.location,
                    footnote: "\(location.skoLine) · \(location.remaining) to pick",
                    identifier: "dispatch.pick.scan"
                ) {
                    scanFeedback = nil
                    isScanning = true
                }
                if hasScanned {
                    panel(location, session: item)
                }
                SectionHeading("Put into")
                CardList(location.notes) { note in
                    ListRow(
                        "\(note.trolley) · delivery note \(note.deliveryNote)",
                        subtitle: location.sko,
                        chips: note.isWaiting ? [Chip("Waiting", .warn)] : [],
                        action: { router.open(GoodsOutRoute.deliveryNote(reference: note.deliveryNote)) }
                    ) {
                        IconTile(.trolley, tone: note.tone)
                    } trailing: {
                        DispatchCountLabel(done: note.picked, total: note.required, tone: note.tone)
                    }
                }
                if let next = item.nextLocation {
                    SectionHeading("Next location")
                    Card {
                        ListRow(
                            next.location,
                            subtitle: next.summary,
                            icon: .locations,
                            iconTone: .neutral,
                            action: { router.open(LocationsRoute.location(code: next.location)) }
                        )
                    }
                }
            } else {
                InlineAlert(
                    tone: item.state == .waiting ? .warn : .success,
                    icon: item.state == .waiting ? .pause : .checkCircle,
                    title: item.state == .waiting ? "Waiting" : "Picking finished",
                    message: item.state == .waiting ? "The rest waits in \(item.bay ?? "a picked bay")." : "Every delivery note is picked."
                )
                AikuButton("Back to session", kind: .tertiary) {
                    router.back()
                }
            }
            DispatchNote("Each scan picks 1 for the delivery note on top of the list. When every note is picked, the session moves to Picking finished; if a line is waiting it moves to Waiting.")
        }
        .aikuSheet(isPresented: $isReporting) {
            DispatchReportProblemSheet(onClose: { isReporting = false })
        }
        .fullScreenCover(isPresented: $isScanning) {
            DispatchCameraScan(
                kicker: item.reference,
                title: "Scan to pick",
                feedback: scanFeedback,
                onClose: { isScanning = false },
                onScan: {
                    guard let note = store.scanPick(reference) else { return }
                    hasScanned = true
                    scanFeedback = "Picked 1 · \(note.trolley) · \(note.deliveryNote)"
                    announce()
                }
            ) {
                if hasScanned, let current = store.session(reference), let location = current.currentLocation {
                    panel(location, session: current)
                }
            }
        }
    }

    private func panel(_ location: DispatchPickLocation, session: DispatchPickingSession) -> some View {
        VStack(spacing: 8) {
            DispatchScanPanel(
                title: "\(location.sko) · Picked \(location.picked)",
                detail: "\(location.remaining) left on this item",
                allTitle: "Pick all \(location.remaining)",
                verb: "Pick",
                maximum: location.remaining,
                photoLabel: "Product photo of \(location.sko)",
                onAll: {
                    store.pickAll(session.reference, at: location.location)
                    announce()
                },
                onAmount: { amount in
                    store.pick(session.reference, at: location.location, amount: amount)
                    announce()
                }
            )
            LinkButton("Not on the shelf · set as waiting") {
                store.setWaiting(session.reference, at: location.location)
                toasts.show("Delivery note set as waiting", tone: .warn)
                announce()
            }
            .accessibilityIdentifier("dispatch.pick.waiting")
        }
    }

    private func announce() {
        guard let current = store.session(reference) else { return }
        switch current.state {
        case .pickingFinished:
            isScanning = false
            toasts.show("\(current.reference) picking finished")
            router.back()
        case .waiting:
            isScanning = false
            toasts.show("\(current.reference) waiting · goods in \(current.bay ?? "a picked bay")", tone: .warn)
        default:
            break
        }
    }
}
