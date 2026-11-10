import SwiftUI

struct FulfilmentReturnView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @State private var isScanning = false
    @State private var scanFeedback: String?
    @State private var hasScanned = false
    @State private var notPickedLine: FulfilmentReturnPalletLine?
    private let store = FulfilmentStore.shared

    var body: some View {
        if let record = store.returnRecord(reference) {
            screen(record)
        } else {
            AppScreen(title: reference) {
                EmptyState(icon: .goodsOut, title: "Return not found")
            }
        }
    }

    private func screen(_ record: FulfilmentReturn) -> some View {
        AppScreen(title: record.reference, kicker: "Fulfilment return · \(record.type.title) · \(record.customer)") {
            FulfilmentProgressSteps(steps: FulfilmentReturnState.allCases.map(\.title), currentIndex: record.state.stepIndex)
            switch record.state {
            case .confirmed, .picking:
                if record.type == .pallets {
                    palletPicking(record)
                } else {
                    itemPicking(record)
                }
            case .picked:
                pickedContent(record)
            case .dispatched:
                FulfilmentDoneHero(title: "\(record.reference) dispatched", message: record.returnedMessage)
                FulfilmentNote("For dropshipping returns from Shopify or TikTok, dispatching also sends the fulfilment back to the customer’s shop.")
                AikuButton("Next return") {
                    router.back()
                }
                .accessibilityIdentifier("fulfilment.return.next")
            }
        } footer: {
            footer(record)
        }
        .aikuSheet(item: $notPickedLine) { line in
            FulfilmentIncidentSheet(
                title: "Not picked",
                subtitle: "\(line.palletReference) · \(line.location ?? "no location")",
                kinds: FulfilmentIncidentKind.allCases,
                helper: "Saved as the incident report with your name and the date. The pallet’s location is cleared until someone sets it back to storing.",
                confirmTitle: { _ in "Set as not picked" },
                onSave: { kind, note in
                    store.setNotPicked(
                        line.palletReference, inReturn: record.reference, kind: kind, note: note,
                        reportedBy: FulfilmentIncident.reporterLabel(fullName: session.profile.name),
                        date: FulfilmentIncident.dateLabel(for: .now)
                    )
                    notPickedLine = nil
                    toasts.show("\(line.palletReference) not picked · \(kind.title.lowercased())", tone: .neutral)
                    announcePickedIfDone(record.reference)
                },
                onCancel: { notPickedLine = nil }
            )
        }
        .fullScreenCover(isPresented: $isScanning) {
            camera(record.reference)
        }
    }

    @ViewBuilder
    private func palletPicking(_ record: FulfilmentReturn) -> some View {
        if let current = record.currentPalletLine {
            FulfilmentScanCard(
                title: "Scan to pick",
                detail: "Scan the pallet label",
                highlight: current.palletReference,
                footnote: current.location ?? "no location",
                identifier: "fulfilment.return.scan"
            ) {
                scanFeedback = nil
                isScanning = true
            }
        }
        CardList(record.palletLines) { line in
            ListRow(
                line.palletReference,
                subtitle: line.subtitle(type: store.pallet(line.palletReference)?.type ?? .pallet),
                chips: [line.chip]
            )
        }
        FulfilmentNote("Picking a whole pallet also returns its stored items. A pallet that can’t be picked is set as Not picked with a state and a note.")
    }

    @ViewBuilder
    private func itemPicking(_ record: FulfilmentReturn) -> some View {
        if let current = record.currentItemLine, !current.isShort {
            FulfilmentScanCard(
                title: "Scan to pick",
                detail: "Scan the pallet, then each item",
                highlight: "\(current.palletReference) · \(current.location ?? "no location")",
                identifier: "fulfilment.return.scan"
            ) {
                scanFeedback = nil
                isScanning = true
            }
            if hasScanned {
                itemPanel(current, returnReference: record.reference)
            }
        }
        ForEach(record.itemLineGroups, id: \.palletReference) { group in
            SectionHeading("From \(group.palletReference) · \(group.location ?? "no location")")
            CardList(group.lines) { line in
                ListRow(line.itemCode, subtitle: itemSubtitle(line)) {
                    ProductThumb(size: 48, label: "Product photo of \(line.itemCode)")
                } trailing: {
                    FulfilmentCountLabel(done: line.picked, total: line.required, tone: line.countTone)
                }
            }
        }
        FulfilmentNote("If a pallet has too few, pick the rest from another pallet holding the same item.")
    }

    private func itemSubtitle(_ line: FulfilmentReturnItemLine) -> String {
        let name = store.storedItem(line.itemCode)?.name ?? line.itemCode
        return line.available < line.required ? "\(name) · only \(line.available) on this pallet" : name
    }

    private func itemPanel(_ line: FulfilmentReturnItemLine, returnReference: String) -> some View {
        FulfilmentScanPanel(
            title: "\(line.itemCode) · Picked \(line.picked)",
            detail: "\(line.remaining) left on this item",
            allTitle: "Pick all \(line.remaining)",
            verb: "Pick",
            maximum: line.remaining,
            onAll: {
                store.pickItems(line.id, amount: line.remaining, inReturn: returnReference)
                announcePickedIfDone(returnReference)
            },
            onAmount: { amount in
                store.pickItems(line.id, amount: amount, inReturn: returnReference)
                announcePickedIfDone(returnReference)
            }
        )
    }

    @ViewBuilder
    private func pickedContent(_ record: FulfilmentReturn) -> some View {
        Card(padding: 16, spacing: 12) {
            Text("All picked")
                .font(.aiku(16, .bold))
                .foregroundStyle(AikuColor.text)
            Text(record.pickedSummary)
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text3)
        }
        SectionHeading("How does it leave?")
        RadioCard("Shipment", subtitle: "Choose a shipper and print labels", isSelected: record.departure == .shipment) {
            store.setDeparture(.shipment, forReturn: record.reference)
        }
        RadioCard("Customer collects", subtitle: "No shipment needed", isSelected: record.departure == .collection) {
            store.setDeparture(.collection, forReturn: record.reference)
        }
        FulfilmentNote("Picked is set by itself when every item is picked.")
    }

    @ViewBuilder
    private func footer(_ record: FulfilmentReturn) -> some View {
        switch record.state {
        case .confirmed, .picking:
            if record.type == .pallets, let current = record.currentPalletLine {
                HStack(spacing: 8) {
                    AikuButton("Not picked", kind: .tertiary) {
                        notPickedLine = current
                    }
                    .accessibilityIdentifier("fulfilment.return.notPicked")
                    AikuButton("Pick \(current.palletReference)") {
                        store.pickPallet(current.palletReference, inReturn: record.reference)
                        toasts.show("\(current.palletReference) picked")
                        announcePickedIfDone(record.reference)
                    }
                    .accessibilityIdentifier("fulfilment.return.pick")
                }
            } else if let short = record.itemLines.first(where: \.isShort) {
                let hasAlternative = store.alternativePallet(for: short.id, inReturn: record.reference) != nil
                AikuButton(
                    hasAlternative ? "Pick \(short.itemCode) from another pallet" : "Return only \(short.picked) × \(short.itemCode)",
                    kind: .secondary
                ) {
                    store.pickFromAnotherPallet(short.id, inReturn: record.reference)
                    announcePickedIfDone(record.reference)
                }
                .accessibilityIdentifier("fulfilment.return.otherPallet")
            } else {
                HelperText("Scan each item to pick it.")
            }
        case .picked:
            if record.departure == .collection {
                AikuButton("Dispatch", icon: .deliveryNote) {
                    store.dispatch(record.reference)
                    toasts.show("\(record.reference) dispatched")
                }
                .accessibilityIdentifier("fulfilment.return.dispatch")
            } else {
                AikuButton(record.shipment == nil ? "Create shipment" : "View shipment", icon: .deliveryNote) {
                    store.createShipment(forReturn: record.reference)
                    router.open(FulfilmentRoute.returnShipment(reference: record.reference))
                }
                .accessibilityIdentifier("fulfilment.return.shipment")
            }
        case .dispatched:
            HelperText("Dispatched.")
        }
    }

    private func announcePickedIfDone(_ reference: String) {
        guard store.returnRecord(reference)?.state == .picked else { return }
        toasts.show("\(reference) picked")
    }

    private func camera(_ reference: String) -> some View {
        let isPallets = store.returnRecord(reference)?.type == .pallets
        return FulfilmentCameraScan(
            kicker: "Return · \(reference)",
            title: "Scan to pick",
            feedback: scanFeedback,
            onClose: { isScanning = false },
            onScan: {
                guard let record = store.returnRecord(reference) else { return }
                if isPallets {
                    guard let current = record.currentPalletLine else { return }
                    store.pickPallet(current.palletReference, inReturn: reference)
                    scanFeedback = "\(current.palletReference) picked"
                } else if let code = store.scanNextReturnItem(inReturn: reference) {
                    hasScanned = true
                    scanFeedback = "\(code) · picked 1"
                }
                announcePickedIfDone(reference)
            }
        ) {
            if !isPallets, hasScanned, let line = store.returnRecord(reference)?.currentItemLine, !line.isShort {
                itemPanel(line, returnReference: reference)
            }
        }
    }
}
