import SwiftUI

struct SectionHeader: View {
    let title: String
    var actionTitle: String?
    var action: (() -> Void)?

    var body: some View {
        HStack {
            Text(title)
                .font(.headline)
                .foregroundStyle(AikuColor.textPrimary)
            Spacer()
            if let actionTitle, let action {
                Button(action: action) {
                    Text(actionTitle)
                        .font(.subheadline.weight(.medium))
                        .foregroundStyle(AikuColor.textBody)
                        .padding(.bottom, 1)
                        .overlay(alignment: .bottom) {
                            Rectangle().fill(AikuColor.underline).frame(height: 3).offset(y: 3)
                        }
                        .frame(minHeight: 44)
                }
            }
        }
        .padding(.horizontal, 4)
    }
}

struct ProgressBar: View {
    let value: Double
    var height: CGFloat = 8

    var body: some View {
        GeometryReader { proxy in
            ZStack(alignment: .leading) {
                Capsule().fill(AikuColor.indigoMuted)
                Capsule().fill(AikuColor.indigo)
                    .frame(width: proxy.size.width * min(max(value, 0), 1))
            }
        }
        .frame(height: height)
        .accessibilityElement()
        .accessibilityValue("\(Int(value * 100)) percent")
    }
}

struct RowDivider: View {
    var body: some View {
        Rectangle().fill(AikuColor.divider).frame(height: 1)
    }
}

struct ChevronIcon: View {
    var body: some View {
        Image(systemName: AppIcon.chevron)
            .font(.system(size: 14, weight: .semibold))
            .foregroundStyle(AikuColor.textFaint)
    }
}

struct ListFooterNote: View {
    let text: String

    var body: some View {
        Text(text)
            .font(.footnote)
            .foregroundStyle(AikuColor.textSecondary)
            .multilineTextAlignment(.center)
            .frame(maxWidth: .infinity)
            .padding(6)
    }
}

struct MovementRow: View {
    let movement: StockMovement
    var showsActor = true

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: symbol)
                .font(.system(size: 14, weight: .bold))
                .foregroundStyle(tint)
                .frame(width: 32, height: 32)
                .background(tint.opacity(0.12), in: RoundedRectangle(cornerRadius: 8))
            VStack(alignment: .leading, spacing: 2) {
                Text(movement.title)
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(AikuColor.textPrimary)
                Text(showsActor ? "\(movement.detail) · \(movement.actor) · \(movement.time)" : movement.detail)
                    .font(.caption)
                    .foregroundStyle(AikuColor.textSecondary)
                    .lineLimit(2)
            }
            Spacer(minLength: 8)
            Text(movement.direction == .neutral ? "\(movement.quantityChange)" : movement.quantityChange.signedChange)
                .font(.subheadline.bold())
                .foregroundStyle(movement.direction == .neutral ? AikuColor.textBody : tint)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
    }

    private var symbol: String {
        switch movement.direction {
        case .in: "arrow.down"
        case .out: "arrow.up"
        case .neutral: "arrow.left.arrow.right"
        }
    }

    private var tint: Color {
        switch movement.direction {
        case .in: AikuColor.success
        case .out: AikuColor.danger
        case .neutral: AikuColor.indigo
        }
    }
}

struct EmptyStateView: View {
    let systemImage: String
    let title: String
    let message: String

    var body: some View {
        VStack(spacing: 10) {
            Image(systemName: systemImage)
                .font(.system(size: 32))
                .foregroundStyle(AikuColor.textFaint)
            Text(title)
                .font(.headline)
                .foregroundStyle(AikuColor.textPrimary)
            Text(message)
                .font(.subheadline)
                .foregroundStyle(AikuColor.textSecondary)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 40)
        .padding(.horizontal, 24)
        .card()
    }
}

struct QuantityStepper: View {
    @Binding var value: Int
    var range: ClosedRange<Int> = 0...9999
    let label: String

    var body: some View {
        HStack(spacing: 0) {
            stepButton("−", "Decrease \(label)") { value = max(range.lowerBound, value - 1) }
            Text("\(value)")
                .font(.headline)
                .monospacedDigit()
                .frame(minWidth: 44)
            stepButton("+", "Increase \(label)") { value = min(range.upperBound, value + 1) }
        }
        .overlay(RoundedRectangle(cornerRadius: 8).stroke(AikuColor.borderStrong))
    }

    private func stepButton(_ symbol: String, _ accessibilityLabel: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(symbol)
                .font(.title3.weight(.semibold))
                .foregroundStyle(AikuColor.textBody)
                .frame(width: 44, height: 44)
                .background(AikuColor.surfaceMuted, in: RoundedRectangle(cornerRadius: 8))
        }
        .accessibilityLabel(accessibilityLabel)
    }
}
