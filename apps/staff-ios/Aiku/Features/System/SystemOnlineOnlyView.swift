import SwiftUI

struct SystemOnlineOnlyView: View {
    @Environment(AppRouter.self) private var router
    @State private var store = SystemSyncStore.shared

    private let steps = ["To do", "Picking", "Picked", "Packing", "Packed", "Finalised", "Dispatched"]
    private let currentStep = 4

    var body: some View {
        AppScreen(title: "AR24811", kicker: "Delivery note · Wholesale") {
            if store.isOnline {
                SystemStatusBanner("Online · \(waitingLabel)", tone: store.pendingCount == 0 ? .success : .info, actionTitle: "View") {
                    router.open(SystemRoute.syncQueue)
                }
            } else {
                SystemStatusBanner("Offline · \(waitingLabel)", tone: .warn, actionTitle: "View") {
                    router.open(SystemRoute.syncQueue)
                }
            }
            progress
            VStack(alignment: .leading, spacing: 6) {
                Text("Finalise needs a connection")
                    .font(.aiku(15, .bold))
                    .foregroundStyle(AikuColor.text)
                Text("Shipments, labels, finalise, dispatch, cancel and Mark as received talk to carriers, printers or customers, so they only work online. Your picking and packing are saved and upload when you’re back.")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .padding(14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .cardSurface()
            Card {
                ListRow("Parcel 1", subtitle: "3.4 kg · 40 × 30 × 20 cm · saved offline", icon: .sko, iconTone: .info)
            }
            HelperText("The main button comes back as soon as the phone is online and the queue has synced.")
        }
    }

    private var waitingLabel: String {
        store.pendingCount == 1 ? "1 action waiting" : "\(store.pendingCount) actions waiting"
    }

    private var progress: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 4) {
                ForEach(Array(steps.enumerated()), id: \.offset) { index, step in
                    Text(step)
                        .font(.aiku(11, .bold))
                        .foregroundStyle(stepForeground(index))
                        .padding(.horizontal, 10)
                        .frame(height: 28)
                        .background(stepBackground(index), in: Capsule())
                        .overlay(Capsule().strokeBorder(index > currentStep ? AikuColor.border : Color.clear))
                        .accessibilityAddTraits(index == currentStep ? .isSelected : [])
                }
            }
            .padding(.horizontal, 16)
        }
        .padding(.horizontal, -16)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("Delivery note progress: \(steps[currentStep])")
    }

    private func stepForeground(_ index: Int) -> Color {
        if index < currentStep { return Color.white }
        if index == currentStep { return AikuColor.onPrimary }
        return AikuColor.text3
    }

    private func stepBackground(_ index: Int) -> Color {
        if index < currentStep { return AikuColor.success }
        if index == currentStep { return AikuColor.primary }
        return AikuColor.surface
    }
}
