import SwiftUI

struct SystemActionFailedView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @State private var isShowingTransfer = false

    var body: some View {
        AppScreen(title: "SKO-00733", kicker: "SKO · Bath bomb – rose 180g") {
            Card {
                KeyValueRow("A-03-02", "30 in location")
                RowDivider()
                KeyValueRow("B-01-04", "12 in location")
            }
            AikuButton("Location transfer", icon: .transfer, kind: .secondary) {
                isShowingTransfer = true
            }
            .accessibilityIdentifier("system.actionFailed.reopen")
        }
        .onAppear {
            isShowingTransfer = true
        }
        .aikuSheet(isPresented: $isShowingTransfer) {
            SystemTransferFailedSheet(
                onMove: { quantity in
                    isShowingTransfer = false
                    toasts.show("\(quantity) units moved to B-01-04", actionTitle: "Undo") {
                        toasts.show("Move undone", tone: .neutral)
                    }
                    router.back()
                },
                onCancel: { isShowingTransfer = false }
            )
        }
    }
}

struct SystemTransferFailedSheet: View {
    let available = 30
    let onMove: (Int) -> Void
    let onCancel: () -> Void
    @State private var quantity = "36"

    private var requested: Int { Int(quantity) ?? 0 }
    private var isOverLimit: Bool { requested > available }
    private var moveQuantity: Int { min(max(requested, 0), available) }

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(spacing: 10) {
                AppIcon.transfer.image
                    .font(.system(size: 20))
                    .foregroundStyle(AikuColor.link)
                    .accessibilityHidden(true)
                Text("Location transfer")
                    .font(.aiku(18, .bold))
                    .foregroundStyle(AikuColor.text)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .accessibilityAddTraits(.isHeader)
                Text("SKO-00733")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            InlineAlert(tone: .danger, title: "Couldn’t move stock", message: "A-03-02 has only \(available) units. Someone picked from it a moment ago.")
            VStack(alignment: .leading, spacing: 6) {
                FieldLabel("From")
                readOnlyField("A-03-02")
                Text("\(available) in location now")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
            VStack(alignment: .leading, spacing: 6) {
                FieldLabel("To")
                readOnlyField("B-01-04")
            }
            AikuTextField(
                "Quantity",
                text: $quantity,
                error: isOverLimit ? "Max \(available) — lower the quantity or choose “Move all”." : nil,
                keyboard: .numberPad
            )
            AikuButton(isOverLimit ? "Move \(available) units" : "Move \(moveQuantity) units") {
                onMove(moveQuantity)
            }
            .disabled(moveQuantity == 0)
            .accessibilityIdentifier("system.actionFailed.move")
            AikuButton("Cancel", kind: .tertiary, size: .small, action: onCancel)
        }
        .padding(.horizontal, 20)
        .padding(.top, 28)
        .padding(.bottom, 16)
    }

    private func readOnlyField(_ value: String) -> some View {
        FieldBox(height: 48, radius: 6) {
            Text(value)
                .font(.aiku(16))
                .foregroundStyle(AikuColor.text)
                .padding(.horizontal, 12)
                .frame(maxWidth: .infinity, alignment: .leading)
        }
    }
}
