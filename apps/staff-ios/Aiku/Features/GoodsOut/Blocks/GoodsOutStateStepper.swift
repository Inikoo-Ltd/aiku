import SwiftUI

struct GoodsOutStateStepper: View {
    let state: GoodsOutDeliveryNoteState

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 4) {
                ForEach(GoodsOutStepper.steps(for: state)) { step in
                    Text(step.title)
                        .font(.aiku(11, .bold))
                        .foregroundStyle(foreground(step.status))
                        .lineLimit(1)
                        .padding(.horizontal, 10)
                        .frame(height: 28)
                        .background(background(step.status), in: Capsule())
                        .overlay(Capsule().strokeBorder(step.status == .upcoming ? AikuColor.border : .clear))
                        .accessibilityLabel(accessibilityText(step))
                }
            }
            .padding(.horizontal, 16)
        }
        .padding(.horizontal, -16)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Delivery note progress")
    }

    private func foreground(_ status: GoodsOutStepStatus) -> Color {
        switch status {
        case .done, .waiting: Color(hex: 0xFFFFFF)
        case .current: AikuColor.onPrimary
        case .upcoming: AikuColor.text3
        }
    }

    private func background(_ status: GoodsOutStepStatus) -> Color {
        switch status {
        case .done: AikuColor.success
        case .current: AikuColor.primary
        case .waiting: AikuColor.warn
        case .upcoming: AikuColor.surface
        }
    }

    private func accessibilityText(_ step: GoodsOutStep) -> String {
        switch step.status {
        case .done: "\(step.title), done"
        case .current, .waiting: "\(step.title), current step"
        case .upcoming: step.title
        }
    }
}
