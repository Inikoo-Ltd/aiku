import SwiftUI

struct DispatchPackView: View {
    let sessionID: Int
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var store: PickingSessionStore
    @State private var isScanning = false
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
                AppScreen(title: "Pack by scan", kicker: "Picking session") {
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
        .hardwareScanner(isEnabled: !isScanning && store.session?.canRun(PickingSessionActionKey.scanPack) == true) { store.scanToPack($0) }
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
        let canScan = item.canRun(PickingSessionActionKey.scanPack)
        let target = canScan ? store.scanTarget : nil
        let nextNote = item.nextNoteToPack
        return AppScreen(
            title: "Pack by scan",
            kicker: [item.reference, item.stateTitle, "packing \(PickingSessionFormat.percent(item.packingPercentage))%"].joined(separator: PickingSessionFormat.separator)) {
            if let problem = store.loadError {
                DispatchLoadProblem(title: "Couldn’t refresh this picking session", message: problem) { await store.load() }
            }
            if canScan {
                DispatchScanCard(
                    title: "Scan to pack",
                    detail: "Scan each item into its delivery note",
                    highlight: nextNote.map { "Delivery note \($0.reference)" } ?? "Every delivery note is packed",
                    footnote: footnote(nextNote),
                    identifier: "dispatch.pack.scan"
                ) {
                    open()
                }
                DispatchBarcodeEntry(placeholder: "Scan or type a barcode", onScanCamera: open) { store.scanToPack($0) }
                if let target {
                    panel(target)
                }
            } else if item.sessionState == .packingFinished {
                InlineAlert(tone: .success, icon: .checkCircle, title: "Packed", message: "Every delivery note is packed and the trolleys are free.")
            } else {
                InlineAlert(tone: .info, title: "Scan to pack isn’t available now", message: "Open a delivery note below to pack it by hand.")
            }
            SectionHeading("Delivery notes")
            if item.deliveryNotes.isEmpty {
                EmptyState(icon: .deliveryNote, title: "Nothing to pack")
            } else {
                CardList(item.deliveryNotes) { note in
                    ListRow(
                        note.reference,
                        subtitle: [PickingSessionFormat.count(note.numberItems, "item"), note.isForCollection ? "Collection" : nil]
                            .compactMap { $0 }
                            .joined(separator: PickingSessionFormat.separator),
                        chips: [Chip(note.stateTitle, note.stateTone)],
                        action: { router.open(GoodsOutRoute.deliveryNoteID(id: note.id)) }
                    ) {
                        EmptyView()
                    } trailing: {
                        Text("\(PickingSessionFormat.percent(note.packingPercentage))%")
                            .font(.aiku(16, .bold))
                            .foregroundStyle(note.packingTone == .neutral ? AikuColor.text : note.packingTone.foreground)
                            .monospacedDigit()
                            .accessibilityLabel("\(PickingSessionFormat.percent(note.packingPercentage)) percent packed")
                    }
                }
            }
            DispatchScanLog(entries: store.scanLog)
            DispatchNote("The session finishes packing by itself when every delivery note is packed, and the trolleys are freed.")
        }
        .screenRefreshable { await store.load() }
        .fullScreenCover(isPresented: $isScanning) {
            DispatchCameraScan(
                kicker: item.reference,
                title: "Scan to pack",
                feedback: cameraFeedback,
                onClose: { isScanning = false },
                onCode: { store.scanToPack($0) }
            ) {
                if let target = store.scanTarget {
                    panel(target)
                }
            }
        }
    }

    private func footnote(_ note: MobileSessionDeliveryNote?) -> String? {
        var parts: [String] = []
        if let note {
            parts.append(PickingSessionFormat.count(note.numberItems, "item"))
            parts.append("packing \(PickingSessionFormat.percent(note.packingPercentage))%")
        }
        if store.queuedScans > 0 { parts.append("\(store.queuedScans) queued") }
        return parts.isEmpty ? nil : parts.joined(separator: PickingSessionFormat.separator)
    }

    private func panel(_ target: PickingSessionScanTarget) -> some View {
        DispatchScanPanel(
            title: "\(target.code) · Packed \(PickingSessionFormat.quantity(target.done))",
            subtitle: [target.name, target.place].filter { !$0.isEmpty }.joined(separator: PickingSessionFormat.separator),
            detail: "\(target.remainingText) left to pack on this item",
            allTitle: "Pack all \(target.remainingText)",
            verb: "Pack",
            maximum: target.maximumAmount,
            isWorking: store.queuedScans > 0,
            onAll: store.packAllScanned,
            onAmount: { store.packScanned(amount: $0) },
            onClose: store.closeScanPanel
        )
    }

    private func open() {
        cameraFeedback = nil
        isScanning = true
    }
}
