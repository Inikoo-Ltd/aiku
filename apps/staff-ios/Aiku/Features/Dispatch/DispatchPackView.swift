import SwiftUI

struct DispatchPackView: View {
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
            AppScreen(title: "Pack by scan") {
                EmptyState(icon: .packed, title: "Picking session not found")
            }
        }
    }

    private func screen(_ item: DispatchPickingSession) -> some View {
        AppScreen(
            title: "Pack by scan",
            kicker: "\(item.reference) · \(item.state.title) · packing \(item.packedPercent)%",
            actions: [HeaderAction(.warning, label: "Report a problem") { isReporting = true }]
        ) {
            if let note = item.currentPackNote {
                DispatchScanCard(
                    title: "Scan to pack",
                    detail: "Put it in this box",
                    highlight: "Delivery note \(note.deliveryNote) · Box \(note.box)",
                    identifier: "dispatch.pack.scan"
                ) {
                    scanFeedback = nil
                    isScanning = true
                }
                if hasScanned {
                    panel(note, session: item)
                }
            } else if item.state == .packed {
                InlineAlert(tone: .success, icon: .checkCircle, title: "Packed", message: "Every delivery note is packed and the trolleys are free.")
            } else {
                InlineAlert(tone: .info, icon: .info, title: "Not ready to pack", message: "Finish picking first.")
            }
            SectionHeading("Delivery notes")
            if item.packNotes.isEmpty {
                EmptyState(icon: .deliveryNote, title: "Nothing to pack yet")
            } else {
                CardList(item.packNotes) { note in
                    ListRow(
                        note.deliveryNote,
                        subtitle: note.subtitle,
                        chips: note.chip.map { [$0] } ?? [],
                        action: { router.open(GoodsOutRoute.deliveryNote(reference: note.deliveryNote)) }
                    ) {
                        EmptyView()
                    } trailing: {
                        DispatchCountLabel(done: note.packed, total: note.items, tone: note.tone)
                    }
                }
            }
            DispatchNote("The session finishes packing by itself when every delivery note is packed, and the trolleys are freed.")
        }
        .aikuSheet(isPresented: $isReporting) {
            DispatchReportProblemSheet(onClose: { isReporting = false })
        }
        .fullScreenCover(isPresented: $isScanning) {
            DispatchCameraScan(
                kicker: item.reference,
                title: "Scan to pack",
                feedback: scanFeedback,
                onClose: { isScanning = false },
                onScan: {
                    guard let note = store.scanPack(reference) else { return }
                    hasScanned = true
                    scanFeedback = "Packed 1 · \(note.deliveryNote) · Box \(note.box)"
                    announce()
                }
            ) {
                if hasScanned, let current = store.session(reference), let note = current.currentPackNote {
                    panel(note, session: current)
                }
            }
        }
    }

    private func panel(_ note: DispatchPackNote, session: DispatchPickingSession) -> some View {
        DispatchScanPanel(
            title: "\(note.deliveryNote) · Packed \(note.packed)",
            detail: "\(note.remaining) left on this delivery note",
            allTitle: "Pack all \(note.remaining)",
            verb: "Pack",
            maximum: note.remaining,
            onAll: {
                store.packAll(session.reference, deliveryNote: note.deliveryNote)
                announce()
            },
            onAmount: { amount in
                store.pack(session.reference, deliveryNote: note.deliveryNote, amount: amount)
                announce()
            }
        )
    }

    private func announce() {
        guard let current = store.session(reference), current.state == .packed else { return }
        isScanning = false
        toasts.show("\(current.reference) packed · trolleys freed")
        router.back()
    }
}
