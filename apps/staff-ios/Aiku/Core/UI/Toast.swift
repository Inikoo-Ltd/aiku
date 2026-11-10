import Observation
import SwiftUI

enum ToastKind {
    case success
    case error
    case offline
    case info

    var symbol: String {
        switch self {
        case .success: "checkmark"
        case .error: "xmark"
        case .offline: "wifi.slash"
        case .info: "info"
        }
    }

    var tint: Color {
        switch self {
        case .success: AikuColor.success
        case .error: AikuColor.danger
        case .offline: AikuColor.textMuted
        case .info: AikuColor.indigo
        }
    }

    var hidesAutomatically: Bool {
        self != .error
    }
}

struct ToastAction {
    let title: String
    let perform: () -> Void
}

struct Toast: Identifiable, Equatable {
    let id = UUID()
    let kind: ToastKind
    let message: String
    var action: ToastAction?

    static func == (lhs: Toast, rhs: Toast) -> Bool {
        lhs.id == rhs.id
    }
}

@MainActor
@Observable
final class ToastCenter {
    private(set) var current: Toast?
    private let hideDelay: Duration
    private var hideTask: Task<Void, Never>?

    init(hideDelay: Duration = .seconds(4)) {
        self.hideDelay = hideDelay
    }

    func show(_ kind: ToastKind, _ message: String, action: ToastAction? = nil) {
        show(Toast(kind: kind, message: message, action: action))
    }

    func show(_ toast: Toast) {
        hideTask?.cancel()
        current = toast
        AccessibilityNotification.Announcement(toast.message).post()
        guard toast.kind.hidesAutomatically else { return }
        hideTask = Task { [hideDelay] in
            try? await Task.sleep(for: hideDelay)
            guard !Task.isCancelled else { return }
            dismiss(toast.id)
        }
    }

    func dismiss(_ id: Toast.ID) {
        guard current?.id == id else { return }
        hideTask?.cancel()
        current = nil
    }
}

struct ToastView: View {
    let toast: Toast
    let onDismiss: () -> Void

    var body: some View {
        HStack(spacing: 12) {
            Image(systemName: toast.kind.symbol)
                .font(.system(size: 13, weight: .bold))
                .foregroundStyle(.white)
                .frame(width: 26, height: 26)
                .background(toast.kind.tint, in: Circle())
                .accessibilityHidden(true)
            Text(toast.message)
                .font(.subheadline)
                .foregroundStyle(AikuColor.toastText)
                .frame(maxWidth: .infinity, alignment: .leading)
            if let action = toast.action {
                Button {
                    onDismiss()
                    action.perform()
                } label: {
                    Text(action.title)
                        .font(.subheadline.bold())
                        .foregroundStyle(AikuColor.yellow)
                        .frame(minWidth: 44, minHeight: 44)
                }
            } else if !toast.kind.hidesAutomatically {
                Button(action: onDismiss) {
                    Image(systemName: "xmark")
                        .font(.system(size: 14, weight: .semibold))
                        .foregroundStyle(AikuColor.toastText)
                        .frame(width: 44, height: 44)
                }
                .accessibilityLabel("Dismiss")
            }
        }
        .padding(.leading, 14)
        .padding(.trailing, toast.action == nil && toast.kind.hidesAutomatically ? 14 : 8)
        .padding(.vertical, toast.action == nil && toast.kind.hidesAutomatically ? 12 : 2)
        .background(AikuColor.toastBackground, in: RoundedRectangle(cornerRadius: 10))
        .shadow(color: .black.opacity(0.3), radius: 12, y: 6)
        .gesture(DragGesture(minimumDistance: 12).onEnded { drag in
            if drag.translation.height > 20 { onDismiss() }
        })
        .accessibilityElement(children: .contain)
    }
}

extension View {
    func toastHost(_ center: ToastCenter, bottomInset: CGFloat = 96) -> some View {
        overlay(alignment: .bottom) {
            if let toast = center.current {
                ToastView(toast: toast) { center.dismiss(toast.id) }
                    .padding(.horizontal, 16)
                    .padding(.bottom, bottomInset)
                    .id(toast.id)
                    .transition(.move(edge: .bottom).combined(with: .opacity))
            }
        }
        .animation(.spring(duration: 0.3), value: center.current)
    }
}
