import SwiftUI

enum GoodsOutQuickAction: String, CaseIterable, Identifiable, Sendable {
    case packingList
    case task
    case askCRM

    var id: String { rawValue }

    var title: String {
        switch self {
        case .packingList: "Packing list"
        case .task: "Task"
        case .askCRM: "Ask CRM"
        }
    }

    var icon: AppIcon {
        switch self {
        case .packingList: .printer
        case .task: .check
        case .askCRM: .chat
        }
    }
}

struct GoodsOutQuickActions: View {
    let onSelect: (GoodsOutQuickAction) -> Void

    var body: some View {
        HStack(spacing: 8) {
            ForEach(GoodsOutQuickAction.allCases) { action in
                Button {
                    onSelect(action)
                } label: {
                    VStack(spacing: 4) {
                        action.icon.image
                            .font(.system(size: 18))
                        Text(action.title)
                            .font(.aiku(12, .semibold))
                            .lineLimit(1)
                            .minimumScaleFactor(0.8)
                    }
                    .foregroundStyle(AikuColor.text2)
                    .frame(maxWidth: .infinity, minHeight: 60)
                    .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 10))
                    .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.borderStrong))
                    .contentShape(RoundedRectangle(cornerRadius: 10))
                }
                .buttonStyle(.plain)
                .accessibilityIdentifier("goodsOut.quick.\(action.rawValue)")
            }
        }
    }
}
