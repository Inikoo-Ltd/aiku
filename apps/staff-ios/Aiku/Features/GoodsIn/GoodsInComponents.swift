import SwiftUI

extension GoodsInRights {
    @MainActor
    init(session: Session) {
        self = session.access == .all ? .full : .warehouseStaff
    }
}

struct GoodsInProgressSteps: View {
    let state: GoodsInDeliveryState

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 4) {
                ForEach(Array(GoodsInDeliveryState.progressSteps.enumerated()), id: \.offset) { index, title in
                    step(title, index: index)
                }
            }
            .padding(.horizontal, 16)
        }
        .padding(.horizontal, -16)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Stock delivery progress")
    }

    private func step(_ title: String, index: Int) -> some View {
        let current = state.progressStep ?? -1
        let isDone = index < current
        let isCurrent = index == current
        return Text(title)
            .font(.aiku(11, .bold))
            .lineLimit(1)
            .fixedSize()
            .foregroundStyle(isDone ? Color.white : isCurrent ? AikuColor.onPrimary : AikuColor.text3)
            .padding(.horizontal, 10)
            .frame(height: 28)
            .background(isDone ? AikuColor.success : isCurrent ? AikuColor.primary : AikuColor.surface, in: Capsule())
            .overlay(Capsule().strokeBorder(isDone || isCurrent ? Color.clear : AikuColor.border))
            .accessibilityAddTraits(isCurrent ? .isSelected : [])
    }
}

struct GoodsInFactGrid: View {
    let title: String
    let facts: [GoodsInFact]

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(title)
                .font(.aiku(13, .bold))
                .foregroundStyle(AikuColor.text)
            LazyVGrid(columns: [GridItem(.flexible(), spacing: 12, alignment: .topLeading), GridItem(.flexible(), spacing: 12, alignment: .topLeading)], alignment: .leading, spacing: 10) {
                ForEach(facts) { fact in
                    VStack(alignment: .leading, spacing: 1) {
                        Text(fact.label)
                            .font(.aiku(11))
                            .foregroundStyle(AikuColor.text3)
                        Text(fact.value)
                            .font(.aiku(14, .semibold))
                            .foregroundStyle(AikuColor.text)
                            .lineLimit(2)
                            .minimumScaleFactor(0.8)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .accessibilityElement(children: .combine)
                }
            }
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface()
    }
}

struct GoodsInOrdererCard: View {
    let delivery: GoodsInDelivery

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text("Orderer")
                .font(.aiku(13, .bold))
                .foregroundStyle(AikuColor.text)
            line(.person, delivery.orderer)
            line(.document, delivery.purchaseOrders)
            line(.history, delivery.estimatedReceiving)
        }
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface()
    }

    private func line(_ icon: AppIcon, _ text: String) -> some View {
        HStack(spacing: 10) {
            icon.image
                .font(.system(size: 14))
                .foregroundStyle(AikuColor.text3)
                .frame(width: 16)
                .accessibilityHidden(true)
            Text(text)
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text2)
                .frame(maxWidth: .infinity, alignment: .leading)
        }
        .frame(minHeight: 28)
    }
}

struct GoodsInStepper: View {
    @Binding var value: Int
    var range: ClosedRange<Int> = 0...99999

    var body: some View {
        HStack(spacing: 10) {
            button("−", label: "Less", isEnabled: value > range.lowerBound) {
                value = max(range.lowerBound, value - 1)
            }
            Text(value.grouped)
                .font(.aiku(22, .bold))
                .foregroundStyle(AikuColor.text)
                .monospacedDigit()
                .lineLimit(1)
                .minimumScaleFactor(0.7)
                .frame(minWidth: 56)
                .accessibilityLabel("Quantity \(value)")
            button("+", label: "More", isEnabled: value < range.upperBound) {
                value = min(range.upperBound, value + 1)
            }
        }
    }

    private func button(_ symbol: String, label: String, isEnabled: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(symbol)
                .font(.aiku(20))
                .foregroundStyle(isEnabled ? AikuColor.text : AikuColor.faint)
                .frame(width: 44, height: 44)
                .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
                .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.borderStrong))
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .disabled(!isEnabled)
        .accessibilityLabel(label)
    }
}

struct GoodsInQuantityText: View {
    let value: Int
    let total: Int
    var tone: Tone = .neutral

    var body: some View {
        (Text(value.grouped).font(.aiku(16, .bold)).foregroundColor(tone == .neutral ? AikuColor.text : tone.foreground)
            + Text(" / \(total.grouped)").font(.aiku(13, .medium)).foregroundColor(AikuColor.text3))
            .monospacedDigit()
            .lineLimit(1)
            .fixedSize()
    }
}

struct GoodsInItemRow: View {
    let sko: String
    let subtitle: String
    var chip: Chip?
    let value: Int
    let total: Int
    var tone: Tone = .neutral
    var action: (() -> Void)?

    var body: some View {
        ListRow(sko, subtitle: subtitle, chips: chip.map { [$0] } ?? [], action: action) {
            ProductThumb(size: 48, label: "Product photo of \(sko)")
        } trailing: {
            GoodsInQuantityText(value: value, total: total, tone: tone)
        }
    }
}

struct GoodsInScanCard: View {
    let title: String
    let subtitle: String
    let code: String
    let detail: String
    let identifier: String
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(alignment: .leading, spacing: 6) {
                HStack(spacing: 8) {
                    AppIcon.scan.image
                        .font(.system(size: 18, weight: .semibold))
                        .accessibilityHidden(true)
                    Text(title)
                        .font(.aiku(14, .bold))
                }
                Text(subtitle)
                    .font(.aiku(13))
                Text(code)
                    .font(.aiku(22, .bold))
                    .monospacedDigit()
                Text(detail)
                    .font(.aiku(13))
                    .multilineTextAlignment(.leading)
            }
            .foregroundStyle(AikuColor.onScan)
            .padding(14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(AikuColor.scan, in: RoundedRectangle(cornerRadius: 12))
            .contentShape(RoundedRectangle(cornerRadius: 12))
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier(identifier)
    }
}

struct GoodsInDeliveryRow: View {
    let delivery: GoodsInDelivery
    let action: () -> Void

    var body: some View {
        ListRow(delivery.reference, subtitle: delivery.listSubtitle(), chips: [delivery.state.chip], action: action)
            .accessibilityIdentifier("goodsIn.delivery.\(delivery.reference)")
    }
}

struct GoodsInStatusLine: View {
    let text: String

    var body: some View {
        HStack(spacing: 8) {
            AppIcon.info.image
                .font(.system(size: 14))
                .accessibilityHidden(true)
            Text(text)
                .font(.aiku(13))
                .frame(maxWidth: .infinity, alignment: .leading)
        }
        .foregroundStyle(AikuColor.text3)
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(AikuColor.surface2, in: RoundedRectangle(cornerRadius: 10))
        .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.border))
    }
}

struct GoodsInTypeCodeSheet: View {
    let title: String
    let subtitle: String
    let label: String
    let placeholder: String
    let actionTitle: String
    let onSubmit: (String) -> Void
    let onCancel: () -> Void
    @State private var code = ""

    private var trimmed: String { code.trimmingCharacters(in: .whitespacesAndNewlines).uppercased() }

    var body: some View {
        SheetScaffold(title: title, subtitle: subtitle) {
            AikuTextField(label, text: $code, placeholder: placeholder)
                .onSubmit(submit)
        } footer: {
            AikuButton(actionTitle) { submit() }
                .disabled(trimmed.isEmpty)
                .accessibilityIdentifier("goodsIn.typeCode.submit")
            AikuButton("Cancel", kind: .tertiary, action: onCancel)
        }
    }

    private func submit() {
        guard !trimmed.isEmpty else { return }
        onSubmit(trimmed)
    }
}

struct GoodsInCodeRequest: Identifiable {
    enum Purpose {
        case scanItem
        case location
    }

    let purpose: Purpose

    var id: String {
        switch purpose {
        case .scanItem: "scanItem"
        case .location: "location"
        }
    }
}
