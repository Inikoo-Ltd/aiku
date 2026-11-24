import SwiftUI

struct StateTimelineStep: Hashable, Sendable {
    let label: String
    let date: String?

    var isDone: Bool { date != nil }
}

struct StateTimeline: View {
    let steps: [StateTimelineStep]

    var body: some View {
        ViewThatFits(in: .horizontal) {
            rows(stepWidth: nil)
            ScrollView(.horizontal, showsIndicators: false) {
                rows(stepWidth: StateTimelineMetrics.scrollStepWidth)
            }
            .scrollBounceBehavior(.basedOnSize, axes: .horizontal)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .cardSurface()
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(L10n.text("Timeline"))
        .accessibilityValue(spokenSteps)
    }

    private var spokenSteps: String {
        steps.map { "\(L10n.text($0.label)) \($0.date ?? "–")" }.joined(separator: ", ")
    }

    private func rows(stepWidth: CGFloat?) -> some View {
        VStack(spacing: 4) {
            HStack(alignment: .bottom, spacing: 0) {
                ForEach(Array(steps.enumerated()), id: \.offset) { entry in
                    Text(L10n.text(entry.element.label))
                        .font(.aiku(11, .semibold))
                        .foregroundStyle(entry.element.isDone ? AikuColor.text : AikuColor.faint)
                        .multilineTextAlignment(.center)
                        .fixedSize(horizontal: false, vertical: true)
                        .padding(.horizontal, 6)
                        .modifier(StateTimelineStepWidth(width: stepWidth))
                }
            }
            HStack(spacing: 0) {
                ForEach(Array(steps.enumerated()), id: \.offset) { entry in
                    ZStack {
                        HStack(spacing: 0) {
                            connector(isShown: entry.offset > 0)
                            Color.clear
                                .frame(width: StateTimelineMetrics.dotSize)
                            connector(isShown: entry.offset < steps.count - 1)
                        }
                        Circle()
                            .fill(entry.element.isDone ? AikuColor.success : AikuColor.borderStrong)
                            .frame(width: StateTimelineMetrics.dotSize, height: StateTimelineMetrics.dotSize)
                    }
                    .frame(height: StateTimelineMetrics.dotSize)
                    .modifier(StateTimelineStepWidth(width: stepWidth))
                }
            }
            HStack(alignment: .top, spacing: 0) {
                ForEach(Array(steps.enumerated()), id: \.offset) { entry in
                    Text(entry.element.date ?? "–")
                        .font(.aiku(11))
                        .foregroundStyle(AikuColor.text3)
                        .multilineTextAlignment(.center)
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                        .padding(.horizontal, 2)
                        .modifier(StateTimelineStepWidth(width: stepWidth))
                }
            }
        }
        .padding(.vertical, 10)
    }

    private func connector(isShown: Bool) -> some View {
        Rectangle()
            .fill(isShown ? AikuColor.border : Color.clear)
            .frame(height: 2)
    }
}

private enum StateTimelineMetrics {
    static let sharedStepMinWidth: CGFloat = 88
    static let scrollStepWidth: CGFloat = 112
    static let dotSize: CGFloat = 12
}

private struct StateTimelineStepWidth: ViewModifier {
    let width: CGFloat?

    @ViewBuilder
    func body(content: Content) -> some View {
        if let width {
            content.frame(width: width)
        } else {
            content.frame(
                minWidth: StateTimelineMetrics.sharedStepMinWidth,
                idealWidth: StateTimelineMetrics.sharedStepMinWidth,
                maxWidth: .infinity
            )
        }
    }
}
