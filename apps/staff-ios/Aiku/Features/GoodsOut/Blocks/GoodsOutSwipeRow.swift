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
                .modifier(GoodsOutSwipeGesture(onChanged: dragChanged, onEnded: dragEnded, fallback: dragGesture))
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

    private var dragGesture: AnyGesture<Void> {
        AnyGesture(
            DragGesture(minimumDistance: 12)
                .onChanged { value in
                    if isHorizontalDrag == nil {
                        isHorizontalDrag = abs(value.translation.width) > abs(value.translation.height)
                    }
                    guard isHorizontalDrag == true else { return }
                    dragChanged(value.translation.width)
                }
                .onEnded { _ in dragEnded() }
                .map { _ in () }
        )
    }

    private func dragChanged(_ translation: CGFloat) {
        isHorizontalDrag = true
        if dragOffset == nil, let open, open.rowID != rowID {
            self.open = nil
        }
        let lower: CGFloat = trailingActions.isEmpty ? 0 : -300
        let upper: CGFloat = leadingActions.isEmpty ? 0 : 300
        dragOffset = min(upper, max(lower, restingOffset + translation))
    }

    private func dragEnded() {
        defer { isHorizontalDrag = nil }
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

private struct GoodsOutSwipeGesture: ViewModifier {
    let onChanged: (CGFloat) -> Void
    let onEnded: () -> Void
    let fallback: AnyGesture<Void>

    func body(content: Content) -> some View {
        if #available(iOS 18.0, *) {
            content.gesture(HorizontalPanGesture(onChanged: onChanged, onEnded: onEnded))
        } else {
            content.simultaneousGesture(fallback)
        }
    }
}

@available(iOS 18.0, *)
private struct HorizontalPanGesture: UIGestureRecognizerRepresentable {
    let onChanged: (CGFloat) -> Void
    let onEnded: () -> Void

    func makeCoordinator(converter: CoordinateSpaceConverter) -> Coordinator {
        Coordinator()
    }

    func makeUIGestureRecognizer(context: Context) -> UIPanGestureRecognizer {
        let recognizer = UIPanGestureRecognizer()
        recognizer.delegate = context.coordinator
        return recognizer
    }

    func handleUIGestureRecognizerAction(_ recognizer: UIPanGestureRecognizer, context: Context) {
        switch recognizer.state {
        case .began, .changed:
            onChanged(recognizer.translation(in: recognizer.view).x)
        case .ended, .cancelled, .failed:
            onEnded()
        default:
            break
        }
    }

    final class Coordinator: NSObject, UIGestureRecognizerDelegate {
        func gestureRecognizerShouldBegin(_ gestureRecognizer: UIGestureRecognizer) -> Bool {
            guard let pan = gestureRecognizer as? UIPanGestureRecognizer else { return false }
            let velocity = pan.velocity(in: pan.view)
            return abs(velocity.x) > abs(velocity.y) * 1.2
        }

        func gestureRecognizer(_ gestureRecognizer: UIGestureRecognizer, shouldBeRequiredToFailBy other: UIGestureRecognizer) -> Bool {
            other.view is UIScrollView
        }
    }
}
