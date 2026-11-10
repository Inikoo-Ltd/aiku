import SwiftUI

struct FulfilmentPalletView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @State private var isReporting = false
    @State private var isMoving = false
    @State private var moveFeedback: String?
    private let store = FulfilmentStore.shared

    var body: some View {
        if let pallet = store.pallet(reference) {
            screen(pallet)
        } else {
            AppScreen(title: reference) {
                EmptyState(icon: .pallet, title: "Pallet not found")
            }
        }
    }

    private func screen(_ pallet: FulfilmentPallet) -> some View {
        AppScreen(title: pallet.reference, kicker: "Pallet · \(pallet.customer)") {
            summary(pallet)
            if let incident = store.incident(for: pallet.reference) {
                InlineAlert(tone: incident.kind.palletState.tone, icon: .warning, title: incident.kind.title, message: incident.subtitle)
            }
            let stocked = pallet.items.filter { $0.quantity > 0 }
            if !stocked.isEmpty {
                SectionHeading("Stored items", detail: "\(stocked.count)")
                CardList(stocked.map { FulfilmentPalletItemRow(item: store.storedItem($0.itemCode), code: $0.itemCode, quantity: $0.quantity) }) { row in
                    ListRow(row.code, subtitle: row.item?.detail) {
                        ProductThumb(size: 48, label: "Product photo of \(row.code)")
                    } trailing: {
                        Text("\(row.quantity)")
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                            .monospacedDigit()
                    }
                }
            }
            actions(pallet)
        }
        .aikuSheet(isPresented: $isReporting) {
            FulfilmentIncidentSheet(
                title: "Report incident",
                subtitle: "\(pallet.reference) · \(pallet.locationLabel)",
                kinds: [.damaged, .lost],
                helper: "Saved as the incident report with your name and the date. The pallet’s location is cleared until someone sets it back to storing.",
                confirmTitle: { "Set as \($0.title.lowercased())" },
                onSave: { kind, note in
                    store.reportIncident(
                        pallet.reference, kind: kind, note: note,
                        reportedBy: FulfilmentIncident.reporterLabel(fullName: session.profile.name),
                        date: FulfilmentIncident.dateLabel(for: .now)
                    )
                    isReporting = false
                    toasts.show("\(pallet.reference) set as \(kind.title.lowercased())", tone: .neutral)
                    router.open(FulfilmentRoute.palletIncidents)
                },
                onCancel: { isReporting = false }
            )
        }
        .fullScreenCover(isPresented: $isMoving) {
            let location = store.suggestedLocation()
            FulfilmentCameraScan(
                kicker: "Move \(pallet.reference)",
                title: "Scan the new location",
                hint: "Point at the location label",
                feedback: moveFeedback,
                onClose: { isMoving = false },
                onScan: {
                    store.move(pallet.reference, to: location)
                    moveFeedback = "\(pallet.reference) moved to \(location)"
                    toasts.show("\(pallet.reference) moved to \(location)")
                }
            ) {
                Text("Now at \(store.pallet(pallet.reference)?.locationLabel ?? pallet.locationLabel)")
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                    .frame(maxWidth: .infinity, alignment: .leading)
            }
        }
    }

    private func summary(_ pallet: FulfilmentPallet) -> some View {
        Card(padding: 16) {
            Grid(alignment: .leading, horizontalSpacing: 10, verticalSpacing: 10) {
                GridRow {
                    field("State", pallet.state.title, tone: pallet.state.tone)
                    field("Type", pallet.type.title)
                }
                GridRow {
                    field("Location", pallet.location ?? "None")
                    field("Customer ref", pallet.customerReferenceLabel)
                }
            }
        }
    }

    private func field(_ label: String, _ value: String, tone: Tone? = nil) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(label)
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text3)
            Text(value)
                .font(.aiku(15, .bold))
                .foregroundStyle(tone.map { $0 == .neutral ? AikuColor.text : $0.foreground } ?? AikuColor.text)
                .lineLimit(2)
                .minimumScaleFactor(0.8)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .accessibilityElement(children: .combine)
    }

    @ViewBuilder
    private func actions(_ pallet: FulfilmentPallet) -> some View {
        switch pallet.state {
        case .storing:
            SectionHeading("Actions")
            Card {
                ListRow("Move to another location", subtitle: "Scan the new location", icon: .locations, iconTone: .info, action: {
                    moveFeedback = nil
                    isMoving = true
                })
                .accessibilityIdentifier("fulfilment.pallet.move")
                RowDivider()
                ListRow("Stored item audit", subtitle: "Count the items on this pallet", icon: .check, iconTone: .info, action: {
                    router.open(FulfilmentRoute.storedItemAudit(reference: pallet.reference))
                })
                .accessibilityIdentifier("fulfilment.pallet.audit")
                RowDivider()
                ListRow("Set as damaged or lost", subtitle: "Report an incident", icon: .pause, iconTone: .danger, action: {
                    isReporting = true
                })
                .accessibilityIdentifier("fulfilment.pallet.incident")
            }
        case .damaged, .lost, .other:
            SectionHeading("Actions")
            Card {
                ListRow("Set back to storing", subtitle: "The incident is resolved", icon: .undo, iconTone: .info, action: {
                    store.setBackToStoring(pallet.reference)
                    toasts.show("\(pallet.reference) is Storing again")
                })
                .accessibilityIdentifier("fulfilment.pallet.resolve")
            }
        case .confirmed, .received, .bookingIn, .bookedIn, .notReceived:
            HelperText("Part of a fulfilment delivery. Actions open once it is booked in.")
        case .returning:
            HelperText("In a fulfilment return.")
        case .returned:
            HelperText("Returned to the customer.")
        }
    }
}

private struct FulfilmentPalletItemRow: Identifiable {
    let item: FulfilmentStoredItem?
    let code: String
    let quantity: Int

    var id: String { code }
}
