import SwiftUI

struct OfflineScreen: View {
    let queuedActions: [String]
    let onRetry: () -> Void
    let onKeepWorking: () -> Void
    let onViewQueue: () -> Void

    var body: some View {
        StateScreen(
            systemImage: "wifi.slash",
            title: "You’re offline",
            message: "No internet connection. Scans and counts are saved on this phone and will sync automatically when you’re back online.",
            actions: [
                StateAction(title: "Try again", systemImage: "arrow.clockwise", perform: onRetry),
                StateAction(title: "Keep working offline", perform: onKeepWorking),
            ]
        ) {
            if !queuedActions.isEmpty {
                syncQueueCard
            }
        }
    }

    private var syncQueueCard: some View {
        Button(action: onViewQueue) {
            VStack(alignment: .leading, spacing: 12) {
                HStack {
                    Label("Waiting to sync", systemImage: "arrow.triangle.2.circlepath")
                        .foregroundStyle(AikuColor.textBody)
                    Spacer()
                    Text(queuedActions.count == 1 ? "1 action" : "\(queuedActions.count) actions")
                        .bold()
                        .foregroundStyle(AikuColor.textPrimary)
                }
                .font(.subheadline)
                Text(queuedActions.joined(separator: " · "))
                    .font(.caption)
                    .foregroundStyle(AikuColor.textSecondary)
                    .fixedSize(horizontal: false, vertical: true)
                Text("View queue")
                    .font(.footnote.bold())
                    .foregroundStyle(AikuColor.indigo)
            }
            .multilineTextAlignment(.leading)
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
            .card()
        }
        .padding(.top, 8)
    }
}

struct SessionExpiredScreen: View {
    let onSignIn: () -> Void

    var body: some View {
        StateScreen(
            systemImage: "lock",
            tone: .info,
            title: "Session expired",
            message: "For security you were signed out after a long time without activity. Sign in again to continue. Nothing you saved is lost.",
            actions: [StateAction(title: "Sign in again", perform: onSignIn)]
        )
    }
}

struct NoAccessScreen: View {
    let warehouse: String
    let module: String
    let permission: String
    let onBack: () -> Void
    let onChangeWarehouse: () -> Void

    var body: some View {
        StateScreen(
            headerTitle: warehouse,
            headerEyebrow: "Access",
            systemImage: "checkmark.shield",
            tone: .warning,
            title: "You don’t have access",
            message: "Your account can’t open **\(module)** in **\(warehouse)**. Ask your warehouse manager to add the permission in Aiku.",
            footnote: "Missing permission: \(permission)",
            actions: [
                StateAction(title: "Back to dashboard", perform: onBack),
                StateAction(title: "Change warehouse", perform: onChangeWarehouse),
            ]
        )
    }
}

struct BarcodeNotFoundScreen: View {
    let code: String
    let onScanAgain: () -> Void
    let onTypeCode: () -> Void
    let onReport: () -> Void

    var body: some View {
        StateScreen(
            headerTitle: "Scan",
            headerEyebrow: "Result",
            systemImage: "magnifyingglass",
            tone: .danger,
            title: "Nothing found for this barcode",
            message: "**\(code)** isn’t linked to a SKU, location, delivery note or stock delivery in this warehouse.",
            actions: [
                StateAction(title: "Scan again", systemImage: AppIcon.scan, perform: onScanAgain),
                StateAction(title: "Type the code", systemImage: "keyboard", perform: onTypeCode),
            ],
            link: StateAction(title: "Report unknown barcode", perform: onReport)
        ) {
            Text("Check the label isn’t damaged, or that you’re in the right warehouse.")
                .font(.footnote)
                .foregroundStyle(AikuColor.textSecondary)
        }
    }
}

struct ServerErrorScreen: View {
    let statusCode: Int
    let reference: String
    let time: String
    let onRetry: () -> Void
    let onReport: () -> Void

    var body: some View {
        StateScreen(
            systemImage: "server.rack",
            tone: .danger,
            title: "Something went wrong",
            message: "Aiku didn’t respond. Your last action was not saved. Try again in a moment.",
            footnote: "Error \(statusCode) · Ref \(reference) · \(time)",
            actions: [
                StateAction(title: "Try again", systemImage: "arrow.clockwise", perform: onRetry),
                StateAction(title: "Report problem", perform: onReport),
            ]
        )
    }
}

struct CameraBlockedScreen: View {
    let context: String
    let onUseScanner: () -> Void
    let onTypeCode: () -> Void

    @Environment(\.openURL) private var openURL

    var body: some View {
        StateScreen(
            headerTitle: "Scan",
            headerEyebrow: context,
            systemImage: "camera",
            title: "Camera access is off",
            message: "Aiku needs the camera to scan barcodes. Turn it on in your phone settings, or use a scanner device instead.",
            actions: [
                StateAction(title: "Open settings") {
                    if let url = URL(string: UIApplication.openSettingsURLString) {
                        openURL(url)
                    }
                },
                StateAction(title: "Use scanner device", systemImage: "barcode", perform: onUseScanner),
                StateAction(title: "Type the code", systemImage: "keyboard", perform: onTypeCode),
            ]
        )
    }
}
