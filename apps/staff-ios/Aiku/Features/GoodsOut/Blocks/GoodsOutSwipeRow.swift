import SwiftUI

struct GoodsOutSwipeAction: Identifiable {
    let id: String
    let title: String
    let icon: AppIcon
    let background: Color
    var foreground: Color = Color(hex: 0xFFFFFF)
    let action: () -> Void
}

enum GoodsOutSwipeSide: Equatable, Sendable {
    case leading
    case trailing
}

struct GoodsOutSwipeOpen: Equatable, Sendable {
    let rowID: String
    let side: GoodsOutSwipeSide
}

struct GoodsOutSwipeRow<Content: View>: View {
    let rowID: String
    @Binding var open: GoodsOutSwipeOpen?
    var leadingActions: [GoodsOutSwipeAction] = []
    var trailingActions: [GoodsOutSwipeAction] = []
    var trailingBackground: Color = AikuColor.warn
    var fullLeading: (() -> Void)?
    var fullTrailing: (() -> Void)?
    @ViewBuilder var content: Content

    @State private var dragOffset: CGFloat?
    @State private var isHorizontalDrag: Bool?

    private let leadingButtonWidth: CGFloat = 120
    private let trailingButtonWidth: CGFloat = 100
    private let openThreshold: CGFloat = 50
    private let fullThreshold: CGFloat = 240

    private var restingOffset: CGFloat {
        guard let open, open.rowID == rowID else { return 0 }
        switch open.side {
        case .leading: return leadingButtonWidth * CGFloat(max(leadingActions.count, 1))
        case .trailing: return -trailingButtonWidth * CGFloat(max(trailingActions.count, 1))
        }
    }

    private var offset: CGFloat { dragOffset ?? restingOffset }

    var body: some View {
        ZStack {
            if offset > 0 {
                leadingBackground
            } else if offset < 0 {
                trailingBackgroundView
            }
            content
                .offset(x: offset)
                .animation(dragOffset == nil ? .easeOut(duration: 0.2) : nil, value: offset)
                .simultaneousGesture(dragGesture)
                .onTapGesture {
                    if open?.rowID == rowID {
                        open = nil
                    }
                }
        }
        .clipped()
        .accessibilityElement(children: .contain)
        .accessibilityActions {
            ForEach(leadingActions + trailingActions) { item in
                Button(item.title) {
                    open = nil
                    item.action()
                }
            }
        }
    }

    private var leadingBackground: some View {
        HStack(spacing: 0) {
            ForEach(leadingActions) { item in
                actionButton(item, width: leadingButtonWidth)
            }
            Spacer(minLength: 0)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(leadingActions.first?.background ?? AikuColor.success)
    }

    private var trailingBackgroundView: some View {
        HStack(spacing: 0) {
            Spacer(minLength: 0)
            ForEach(trailingActions) { item in
                actionButton(item, width: trailingButtonWidth)
            }
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .background(trailingBackground)
    }

    private func actionButton(_ item: GoodsOutSwipeAction, width: CGFloat) -> some View {
        Button {
            open = nil
            item.action()
        } label: {
            VStack(spacing: 4) {
                item.icon.image
                    .font(.system(size: 20, weight: .semibold))
                Text(item.title)
                    .font(.aiku(13, .bold))
                    .lineLimit(2)
                    .minimumScaleFactor(0.8)
                    .multilineTextAlignment(.center)
            }
            .foregroundStyle(item.foreground)
            .frame(width: width)
            .frame(maxHeight: .infinity)
            .background(item.background)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("goodsOut.swipe.\(item.id)")
    }

    private var dragGesture: some Gesture {
        DragGesture(minimumDistance: 12)
            .onChanged { value in
                if isHorizontalDrag == nil {
                    isHorizontalDrag = abs(value.translation.width) > abs(value.translation.height)
                    if isHorizontalDrag == true, let open, open.rowID != rowID {
                        self.open = nil
                    }
                }
                guard isHorizontalDrag == true else { return }
                let proposed = restingOffset + value.translation.width
                let allowLeading = !leadingActions.isEmpty
                let allowTrailing = !trailingActions.isEmpty
                let lower: CGFloat = allowTrailing ? -300 : 0
                let upper: CGFloat = allowLeading ? 300 : 0
                dragOffset = min(upper, max(lower, proposed))
            }
            .onEnded { _ in
                defer {
                    isHorizontalDrag = nil
                }
                guard isHorizontalDrag == true, let final = dragOffset else { return }
                dragOffset = nil
                if final > fullThreshold, let fullLeading {
                    open = nil
                    fullLeading()
                } else if final > openThreshold {
                    open = GoodsOutSwipeOpen(rowID: rowID, side: .leading)
                } else if final < -(fullThreshold + 30), let fullTrailing {
                    open = nil
                    fullTrailing()
                } else if final < -openThreshold {
                    open = GoodsOutSwipeOpen(rowID: rowID, side: .trailing)
                } else {
                    open = nil
                }
            }
    }
}
