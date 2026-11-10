import SwiftUI

struct ConfirmDialog: Identifiable {
    enum Role {
        case primary
        case destructive
    }

    let id = UUID()
    let title: String
    let message: String
    let confirmTitle: String
    var cancelTitle = "Cancel"
    var role: Role = .primary
    let onConfirm: () -> Void
}

struct ConfirmDialogCard: View {
    let dialog: ConfirmDialog
    let onCancel: () -> Void
    let onConfirm: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            Text(dialog.title)
                .font(.system(size: 18, weight: .bold))
                .foregroundStyle(AikuColor.textPrimary)
                .accessibilityAddTraits(.isHeader)
            Text(dialog.message)
                .font(.subheadline)
                .foregroundStyle(AikuColor.textSecondary)
                .fixedSize(horizontal: false, vertical: true)
            HStack(spacing: 8) {
                ActionButton(title: dialog.cancelTitle, kind: .outline, action: onCancel)
                ActionButton(title: dialog.confirmTitle, kind: dialog.role == .destructive ? .destructiveOutline : .primary, action: onConfirm)
            }
            .padding(.top, 4)
        }
        .padding(20)
        .frame(maxWidth: 400)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 14))
        .overlay(RoundedRectangle(cornerRadius: 14).stroke(AikuColor.border))
        .shadow(color: .black.opacity(0.18), radius: 20, y: 10)
        .accessibilityElement(children: .contain)
        .accessibilityAddTraits(.isModal)
    }
}

extension View {
    func confirmDialog(_ dialog: Binding<ConfirmDialog?>) -> some View {
        overlay {
            ZStack {
                if let current = dialog.wrappedValue {
                    AikuColor.scrim
                        .ignoresSafeArea()
                        .onTapGesture { dialog.wrappedValue = nil }
                        .accessibilityHidden(true)
                        .transition(.opacity)
                    ConfirmDialogCard(dialog: current) {
                        dialog.wrappedValue = nil
                    } onConfirm: {
                        dialog.wrappedValue = nil
                        current.onConfirm()
                    }
                    .padding(.horizontal, 24)
                    .transition(.scale(scale: 0.95).combined(with: .opacity))
                }
            }
            .animation(.easeOut(duration: 0.2), value: dialog.wrappedValue?.id)
        }
    }
}
