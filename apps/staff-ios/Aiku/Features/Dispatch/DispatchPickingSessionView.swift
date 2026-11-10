import SwiftUI

struct DispatchPickingSessionView: View {
    let reference: String
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var tab: DispatchSessionTab = .byLocation
    private let store = DispatchStore.shared

    var body: some View {
        if let item = store.session(reference) {
            screen(item)
        } else {
            AppScreen(title: reference) {
                EmptyState(icon: .trolley, title: "Picking session not found")
            }
        }
    }

    private func screen(_ item: DispatchPickingSession) -> some View {
        AppScreen(title: item.reference, kicker: "Picking session · \(item.kind.title)") {
            summary(item)
            if !item.trolleys.isEmpty {
                SectionHeading("Trolleys")
                CardList(item.trolleys.map { DispatchSessionTrolleyRow(code: $0, notes: item.noteCount(onTrolley: $0)) }) { row in
                    ListRow(
                        row.code,
                        subtitle: row.notes == 1 ? "1 delivery note" : "\(row.notes) delivery notes",
                        icon: .trolley,
                        iconTone: .info
                    )
                }
            }
            if let bay = item.bay {
                InlineAlert(tone: .warn, icon: .pause, title: "Waiting in \(bay)", message: "Picked goods wait in the bay until missing products arrive.")
            }
            SegmentedControl(options: DispatchSessionTab.allCases, selection: $tab, title: \.title)
            lines(item)
        } footer: {
            footer(item)
        }
    }

    private func summary(_ item: DispatchPickingSession) -> some View {
        Card(padding: 14, spacing: 12) {
            HStack(spacing: 12) {
                IconTile(.person, tone: .info)
                VStack(alignment: .leading, spacing: 2) {
                    Text("Picker")
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text3)
                    Text(item.pickerLabel)
                        .font(.aiku(15, .semibold))
                        .foregroundStyle(AikuColor.text)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                StatusChip(item.state.title, tone: item.state.tone)
            }
            HStack(spacing: 8) {
                stat("\(item.deliveryNoteCount)", "delivery notes")
                stat("\(item.itemCount)", "items")
                stat("\(item.trolleys.count)", item.trolleys.count == 1 ? "trolley" : "trolleys")
            }
        }
    }

    private func stat(_ value: String, _ label: String) -> some View {
        VStack(spacing: 2) {
            Text(value)
                .font(.aiku(18, .bold))
                .foregroundStyle(AikuColor.text)
            Text(label)
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text3)
                .lineLimit(1)
                .minimumScaleFactor(0.8)
        }
        .frame(maxWidth: .infinity)
        .accessibilityElement(children: .combine)
    }

    @ViewBuilder
    private func lines(_ item: DispatchPickingSession) -> some View {
        switch tab {
        case .items:
            if item.skoLines.isEmpty {
                EmptyState(icon: .sko, title: "No items to pick")
            } else {
                CardList(item.skoLines) { line in
                    ListRow(line.sko, subtitle: line.skoName, action: { router.open(InventoryRoute.sko(reference: line.sko)) }) {
                        ProductThumb(size: 48, label: "Product photo of \(line.sko)")
                    } trailing: {
                        DispatchCountLabel(done: line.picked, total: line.required, tone: line.picked >= line.required ? .success : .neutral)
                    }
                }
            }
        case .byLocation:
            if item.locations.isEmpty {
                EmptyState(icon: .locations, title: "No locations to pick")
            } else {
                CardList(item.locations) { location in
                    ListRow(
                        location.location,
                        subtitle: location.summary,
                        icon: .locations,
                        iconTone: location.isOpen ? .info : .success,
                        action: { router.open(LocationsRoute.location(code: location.location)) }
                    )
                }
            }
        case .byDelivery:
            CardList(item.deliveryNoteLines) { note in
                ListRow(
                    note.deliveryNote,
                    subtitle: note.subtitle,
                    icon: .deliveryNote,
                    iconTone: .info,
                    action: { router.open(GoodsOutRoute.deliveryNote(reference: note.deliveryNote)) }
                )
            }
        }
    }

    @ViewBuilder
    private func footer(_ item: DispatchPickingSession) -> some View {
        switch item.state {
        case .inProcess, .picking:
            HStack(spacing: 8) {
                AikuButton("Add delivery notes", kind: .tertiary) {
                    router.open(GoodsOutRoute.deliveryNotes)
                }
                AikuButton(item.state == .inProcess ? "Start picking" : "Continue picking") {
                    if item.state == .inProcess {
                        store.startPicking(item.reference)
                        toasts.show("Trolleys \(item.trolleys.joined(separator: ", ")) in use")
                    }
                    router.open(DispatchRoute.pickingSessionPick(reference: item.reference))
                }
                .accessibilityIdentifier("dispatch.session.pick")
            }
        case .waiting:
            HelperText("Waiting for missing products.")
        case .pickingFinished:
            AikuButton("Pack by scan") {
                router.open(DispatchRoute.pickingSessionPack(reference: item.reference))
            }
            .accessibilityIdentifier("dispatch.session.pack")
        case .packed:
            HelperText("Packed. The trolleys are free.")
        }
    }
}

private struct DispatchSessionTrolleyRow: Identifiable {
    let code: String
    let notes: Int

    var id: String { code }
}
