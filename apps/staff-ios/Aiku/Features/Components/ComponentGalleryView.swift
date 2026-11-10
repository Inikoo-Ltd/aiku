import SwiftUI

struct ComponentGalleryView: View {
    @Environment(ToastCenter.self) private var toasts
    @Environment(AppRouter.self) private var router

    @State private var isTransferSheetPresented = false
    @State private var transferLocation = "B-01-04"
    @State private var transferQuantity = 10
    @State private var dialog: ConfirmDialog?
    @State private var isSaving = false

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: "UI components", eyebrow: "Debug", trailing: .none)
            ScrollView {
                VStack(alignment: .leading, spacing: 24) {
                    sheetSection
                    dialogSection
                    toastSection
                    alertSection
                    loadingSection
                    statesSection
                }
                .padding(16)
                .padding(.bottom, 80)
            }
        }
        .aikuScreen()
        .confirmDialog($dialog)
        .sheet(isPresented: $isTransferSheetPresented) {
            BottomSheet(
                title: "Location transfer",
                systemImage: "arrow.left.arrow.right",
                context: "SKU-00733",
                primaryTitle: "Move \(transferQuantity) units",
                primaryAction: {
                    isTransferSheetPresented = false
                    toasts.show(.success, "\(transferQuantity) units moved to \(transferLocation)", action: ToastAction(title: "Undo") {})
                }
            ) {
                SheetTextField(label: "To location", text: $transferLocation)
                VStack(alignment: .leading, spacing: 6) {
                    Text("Quantity")
                        .font(.subheadline.weight(.medium))
                        .foregroundStyle(AikuColor.textBody)
                    QuantityStepper(value: $transferQuantity, range: 1...30, label: "quantity")
                }
            }
        }
    }

    private var sheetSection: some View {
        gallerySection("Bottom sheet") {
            ActionButton(title: "Open location transfer", kind: .secondary) {
                isTransferSheetPresented = true
            }
        }
    }

    private var dialogSection: some View {
        gallerySection("Confirm dialog") {
            ActionButton(title: "Remove picker", kind: .destructiveOutline) {
                dialog = ConfirmDialog(
                    title: "Remove picker?",
                    message: "DN-24817 goes back to Unassigned. Items already picked stay picked.",
                    confirmTitle: "Remove",
                    role: .destructive
                ) {
                    toasts.show(.info, "DN-24817 is unassigned")
                }
            }
            ActionButton(title: "Set as packed", kind: .outline) {
                dialog = ConfirmDialog(
                    title: "Set as packed with 1 missing?",
                    message: "SKU-01402 is out of stock and will be marked as not dispatched.",
                    confirmTitle: "Set as packed",
                    cancelTitle: "Go back"
                ) {
                    toasts.show(.success, "DN-24817 set as packed")
                }
            }
        }
    }

    private var toastSection: some View {
        gallerySection("Toasts") {
            ActionButton(title: "Success with undo", kind: .outline) {
                toasts.show(.success, "40 units moved to A-07-01", action: ToastAction(title: "Undo") {})
            }
            ActionButton(title: "Error with retry", kind: .outline) {
                toasts.show(.error, "Couldn’t save. Check your connection.", action: ToastAction(title: "Retry") {})
            }
            ActionButton(title: "Offline", kind: .outline) {
                toasts.show(.offline, "Saved offline · will sync later")
            }
            ActionButton(title: "Info with action", kind: .outline) {
                toasts.show(.info, "DN-24820 was assigned to you", action: ToastAction(title: "Open") {})
            }
        }
    }

    private var alertSection: some View {
        gallerySection("Inline alerts") {
            InlineAlert(kind: .danger, title: "Incorrect username or password", message: "Check your details and try again.")
            InlineAlert(kind: .warning, title: "This delivery note changed", message: "Sam P. updated it while you were offline.", actionTitle: "Reload now") {}
            InlineAlert(kind: .info, title: "2 items short on SD-1093", message: "They will be recorded as not received.")
            InlineAlert(kind: .success, title: "Synced", message: "3 offline actions were saved.")
            OfflineBar(message: "Offline · 3 actions waiting to sync", actionTitle: "Retry") {}
        }
    }

    private var loadingSection: some View {
        gallerySection("Loading") {
            SkeletonListCard()
            SkeletonStatCard()
            ActionButton(title: isSaving ? "Saving…" : "Save", isLoading: isSaving) {
                isSaving = true
                Task {
                    try? await Task.sleep(for: .seconds(2))
                    isSaving = false
                }
            }
        }
    }

    private var statesSection: some View {
        gallerySection("States & errors") {
            VStack(spacing: 0) {
                ForEach(StateDemo.allCases) { demo in
                    Button {
                        router.open(.stateDemo(demo))
                    } label: {
                        HStack {
                            Text(demo.title)
                                .font(.subheadline.weight(.medium))
                                .foregroundStyle(AikuColor.textPrimary)
                            Spacer()
                            ChevronIcon()
                        }
                        .padding(.horizontal, 14)
                        .frame(minHeight: 48)
                    }
                    .accessibilityIdentifier("state.\(demo.rawValue)")
                    if demo != StateDemo.allCases.last {
                        RowDivider()
                    }
                }
            }
            .card()
        }
    }

    private func gallerySection<Content: View>(_ title: String, @ViewBuilder content: () -> Content) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            SectionHeader(title: title)
            content()
        }
    }
}

#Preview {
    ComponentGalleryView()
        .environment(AppRouter())
        .environment(ToastCenter())
}
