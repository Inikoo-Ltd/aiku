import SwiftUI

struct ScannerDevicePickingView: View {
    @Environment(AppRouter.self) private var router
    let note: DeliveryNote

    private struct ScanLog: Identifiable {
        let id: String
        let code: String
        let detail: String
        let time: String
        let isCounted: Bool
    }

    private var currentItem: PickItem? {
        note.items.first { !$0.isComplete && !$0.isOutOfStock }
    }

    private let lastScans = [
        ScanLog(id: "s3", code: "SKU-00733", detail: "+1", time: "09:41:20", isCounted: true),
        ScanLog(id: "s2", code: "SKU-00412", detail: "wrong item, not counted", time: "09:41:12", isCounted: false),
        ScanLog(id: "s1", code: "A-03-02", detail: "location", time: "09:40:58", isCounted: true),
    ]

    var body: some View {
        VStack(spacing: 0) {
            header
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    steps
                    if let item = currentItem {
                        scanNowCard(item)
                    } else {
                        EmptyStateView(systemImage: "checkmark.circle", title: "All items picked", message: "Set the delivery note as packed.")
                    }
                    lastScansSection
                }
                .padding(16)
            }
        }
        .aikuScreen()
        .bottomActionBar {
            ActionButton(title: "Not enough stock", kind: .destructiveOutline) {}
            ActionButton(title: "Skip for now", kind: .outline) {}
        }
    }

    private var header: some View {
        VStack(spacing: 12) {
            HStack(spacing: 4) {
                HeaderIconButton(systemImage: AppIcon.back, label: "Back to delivery note", isFilled: false) { router.back() }
                VStack(alignment: .leading, spacing: 0) {
                    Text("Goods out · Picking").font(.caption).foregroundStyle(AikuColor.indigoMuted)
                    Text(note.reference).font(.title3.bold()).foregroundStyle(.white)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                Text("\(note.pickedCount) / \(note.itemCount)")
                    .font(.subheadline.bold())
                    .foregroundStyle(AikuColor.navy)
                    .padding(.horizontal, 10)
                    .padding(.vertical, 6)
                    .background(AikuColor.yellow, in: Capsule())
            }
            HStack(spacing: 12) {
                Circle().fill(Color.green).frame(width: 10, height: 10)
                VStack(alignment: .leading, spacing: 0) {
                    Text("Scanner device ready").font(.subheadline.bold())
                    Text("Press the trigger to scan").font(.caption).opacity(0.85)
                }
                .foregroundStyle(AikuColor.yellow)
                Spacer()
                Button {
                    router.scan(ScanContext(title: "Goods out · Picking", reference: note.reference))
                } label: {
                    Image(systemName: "camera")
                        .foregroundStyle(.white)
                        .frame(width: 44, height: 44)
                        .background(Color.white.opacity(0.14), in: RoundedRectangle(cornerRadius: 8))
                }
                .accessibilityLabel("Switch to camera")
            }
            .padding(.leading, 14)
            .padding(.trailing, 6)
            .padding(.vertical, 6)
            .background(AikuColor.indigoDeep, in: RoundedRectangle(cornerRadius: 10))
            .overlay(RoundedRectangle(cornerRadius: 10).stroke(AikuColor.indigoRing))
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
        .padding(.bottom, 14)
        .background(AikuColor.indigo.ignoresSafeArea(edges: .top))
    }

    private var steps: some View {
        HStack(spacing: 8) {
            stepChip("Location", systemImage: "checkmark", foreground: AikuColor.success, background: AikuColor.successSoft)
            stepChip("2 · Item", foreground: AikuColor.yellow, background: AikuColor.indigoDeep)
            stepChip("3 · Quantity", foreground: AikuColor.textSecondary, background: AikuColor.surface)
        }
    }

    private func stepChip(_ title: String, systemImage: String? = nil, foreground: Color, background: Color) -> some View {
        HStack(spacing: 4) {
            if let systemImage {
                Image(systemName: systemImage).font(.caption.bold())
            }
            Text(title).font(.caption.weight(.semibold))
        }
        .foregroundStyle(foreground)
        .frame(maxWidth: .infinity, minHeight: 36)
        .background(background, in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).stroke(AikuColor.border))
    }

    private func scanNowCard(_ item: PickItem) -> some View {
        VStack(spacing: 10) {
            if let location = item.locationCode {
                Label("\(location) confirmed", systemImage: "checkmark.circle.fill")
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(AikuColor.success)
            }
            Text("Scan item").font(.subheadline).foregroundStyle(AikuColor.textSecondary)
            Text(item.skuReference).font(.system(size: 34, weight: .bold)).foregroundStyle(AikuColor.textPrimary)
            Text(item.name).font(.headline).foregroundStyle(AikuColor.textBody)
            HStack(alignment: .firstTextBaseline, spacing: 4) {
                Text("\(item.picked)").font(.system(size: 44, weight: .bold)).foregroundStyle(AikuColor.indigo)
                Text("/ \(item.required) picked").font(.title3).foregroundStyle(AikuColor.textSecondary)
            }
            Text("Each scan adds 1. Hold trigger 1 s to enter a quantity.")
                .font(.caption)
                .foregroundStyle(AikuColor.textSecondary)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity)
        .padding(20)
        .card()
    }

    private var lastScansSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("LAST SCANS")
                .font(.caption.weight(.bold))
                .tracking(0.6)
                .foregroundStyle(AikuColor.textBody)
                .padding(.horizontal, 4)
            VStack(spacing: 0) {
                ForEach(Array(lastScans.enumerated()), id: \.element.id) { index, scan in
                    if index > 0 { RowDivider() }
                    HStack(spacing: 10) {
                        Image(systemName: scan.isCounted ? "checkmark.circle.fill" : "xmark.octagon.fill")
                            .foregroundStyle(scan.isCounted ? AikuColor.success : AikuColor.danger)
                        Text(scan.code).font(.subheadline.bold()).foregroundStyle(AikuColor.textPrimary)
                        Text(scan.detail)
                            .font(.caption.weight(.semibold))
                            .foregroundStyle(scan.isCounted ? AikuColor.textSecondary : AikuColor.danger)
                            .lineLimit(1)
                            .minimumScaleFactor(0.8)
                        Spacer()
                        Text(scan.time).font(.caption).monospacedDigit().foregroundStyle(AikuColor.textSecondary)
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                }
            }
            .card()
        }
    }
}

#Preview {
    ScannerDevicePickingView(note: SampleData.deliveryNotes[0])
        .environment(AppRouter())
}
