import SwiftUI

struct DispatchPickView: View {
    let sessionID: Int
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var store: PickingSessionStore
    @State private var isScanning = false
    @State private var isReporting = false
    @State private var cameraFeedback: (text: String, tone: Tone)?

    init(sessionID: Int) {
        self.sessionID = sessionID
        _store = State(initialValue: PickingSessionsStore.shared.store(for: sessionID))
    }

    var body: some View {
        Group {
            if let item = store.session {
                screen(item)
            } else {
                AppScreen(title: "Pick by location", kicker: "Picking session") {
                    if let problem = store.loadError {
                        DispatchLoadProblem(title: "Couldn’t load this picking session", message: problem) { await store.load() }
                    } else {
                        DispatchSkeletonList(rows: 2)
                        DispatchSkeletonList(rows: 4)
                    }
                }
            }
        }
        .task { await store.load() }
        .onChange(of: store.event) { _, event in
            guard let event else { return }
            store.event = nil
            let toast = event.toast
            cameraFeedback = (toast.text, toast.tone)
            toasts.show(toast.text, tone: toast.tone)
            if event.isFinishing {
                isScanning = false
                router.back()
            }
        }
    }

    private func screen(_ item: MobilePickingSession) -> some View {
        let canScan = item.canRun(PickingSessionActionKey.scanPick)
        let target = canScan ? store.scanTarget : nil
        return AppScreen(
            title: "Pick by location",
            kicker: [item.reference, item.stateTitle, "\(PickingSessionFormat.percent(item.pickingPercentage))% picked"].joined(separator: PickingSessionFormat.separator),
            actions: [HeaderAction(.warning, label: "Report a problem") { isReporting = true }]
        ) {
            if let problem = store.loadError {
                DispatchLoadProblem(title: "Couldn’t refresh this picking session", message: problem) { await store.load() }
            }
            if let current = store.currentPick {
                if !canScan {
                    InlineAlert(tone: .info, title: "Scan to pick is off for this session", message: "Pick each line by hand: tap a delivery note under Put into.")
                }
                DispatchScanCard(
                    title: canScan ? "Scan to pick" : "Pick by hand",
                    detail: "Take it from this location",
                    highlight: current.title,
                    footnote: footnote(current),
                    identifier: "dispatch.pick.scan"
                ) {
                    if canScan { open() }
                }
                .disabled(!canScan)
                if canScan {
                    DispatchBarcodeEntry(placeholder: "Scan or type a barcode", onScanCamera: open) { store.scanToPick($0) }
                }
                if let target {
                    panel(target)
                }
                SectionHeading("Put into")
                CardList(current.lines) { line in
                    ListRow(
                        "Delivery note \(line.deliveryNoteReference ?? "#\(line.deliveryNoteId)")",
                        subtitle: line.subtitle,
                        action: { router.open(GoodsOutRoute.deliveryNoteID(id: line.deliveryNoteId)) }
                    ) {
                        IconTile(.trolley, tone: line.tone)
                    } trailing: {
                        DispatchCountLabel(done: PickingSessionFormat.quantity(line.quantityPicked), total: PickingSessionFormat.quantity(line.quantityRequired), tone: line.tone)
                    }
                }
            } else {
                EmptyState(icon: .checkCircle, title: "Nothing left to pick", message: "Every line in this session is picked or waiting.")
                AikuButton("Back to session", kind: .tertiary) { router.back() }
            }
            if let next = store.nextPick {
                SectionHeading("Next location")
                Card {
                    ListRow(next.title, subtitle: next.detail, icon: .locations, iconTone: .neutral, action: store.nextLocation)
                }
            }
            DispatchScanLog(entries: store.scanLog)
            DispatchNote(canScan
                ? "Each scan picks 1 for the next delivery note that needs it, or tap a delivery note to pick by hand. When every line is handled, the session moves to Picking finished; if a line is waiting it moves to Waiting."
                : "Tap a delivery note to pick its line by hand. When every line is handled, the session moves to Picking finished; if a line is waiting it moves to Waiting.")
        } footer: {
            if store.nextPick != nil {
                AikuButton("Next location", icon: .chevronRight, kind: .tertiary, action: store.nextLocation)
            }
        }
        .screenRefreshable { await store.load() }
        .aikuSheet(isPresented: $isReporting) {
            DispatchReportProblemSheet(onClose: { isReporting = false })
        }
        .fullScreenCover(isPresented: $isScanning) {
            DispatchCameraScan(
                kicker: item.reference,
                title: "Scan to pick",
                feedback: cameraFeedback,
                onClose: { isScanning = false },
                onCode: { store.scanToPick($0) }
            ) {
                if let target = store.scanTarget {
                    panel(target)
                } else if let current = store.currentPick {
                    Text("\(current.title) · \(footnote(current))")
                        .font(.aiku(14, .semibold))
                        .foregroundStyle(AikuColor.text)
                        .frame(maxWidth: .infinity, alignment: .leading)
                }
            }
        }
    }

    private func footnote(_ entry: MobilePickListEntry) -> String {
        var parts = [entry.stockLine, entry.toPickText].filter { !$0.isEmpty }
        if store.queuedScans > 0 { parts.append("\(store.queuedScans) queued") }
        return parts.joined(separator: PickingSessionFormat.separator)
    }

    private func panel(_ target: PickingSessionScanTarget) -> some View {
        DispatchScanPanel(
            title: "\(target.code) · Picked \(PickingSessionFormat.quantity(target.done))",
            subtitle: [target.name, target.place].filter { !$0.isEmpty }.joined(separator: PickingSessionFormat.separator),
            detail: "\(target.remainingText) left on this item",
            allTitle: "Pick all \(target.remainingText)",
            verb: "Pick",
            maximum: target.maximumAmount,
            isWorking: store.queuedScans > 0,
            onAll: store.pickAllScanned,
            onAmount: { store.pickScanned(amount: $0) },
            onClose: store.closeScanPanel
        )
    }

    private func open() {
        cameraFeedback = nil
        isScanning = true
    }
}
