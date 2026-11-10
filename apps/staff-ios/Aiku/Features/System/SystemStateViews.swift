import SwiftUI

enum SystemRichText {
    static func text(_ markdown: String) -> Text {
        Text((try? AttributedString(markdown: markdown)) ?? AttributedString(markdown))
    }
}

struct SystemOfflineView: View {
    @Environment(AppRouter.self) private var router
    @State private var store = SystemSyncStore.shared

    var body: some View {
        SystemStateScreen {
            SystemStateBody(
                icon: SystemStateIcon(icon: .offline, foreground: AikuColor.text3, background: AikuColor.surface2),
                title: "You’re offline",
                message: Text("No internet connection. Scans and counts are saved on this phone and will sync automatically when you’re back online.")
            ) {
                queueCard
            }
        } actions: {
            AikuButton("Try again", icon: .retry) { router.back() }
                .accessibilityIdentifier("system.offline.retry")
            AikuButton("Keep working offline", kind: .tertiary) { router.select(.home) }
        }
    }

    private var queueCard: some View {
        Button {
            router.open(SystemRoute.syncQueue)
        } label: {
            VStack(alignment: .leading, spacing: 12) {
                HStack {
                    Label {
                        Text("Waiting to sync")
                    } icon: {
                        AppIcon.sync.image.font(.system(size: 16))
                    }
                    .foregroundStyle(AikuColor.text2)
                    Spacer(minLength: 8)
                    Text(store.pendingCount == 1 ? "1 action" : "\(store.pendingCount) actions")
                        .fontWeight(.bold)
                        .foregroundStyle(AikuColor.text)
                }
                .font(.aiku(14))
                if !summary.isEmpty {
                    Text(summary)
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text3)
                }
                Text("View queue")
                    .font(.aiku(13, .bold))
                    .foregroundStyle(AikuColor.link)
            }
            .multilineTextAlignment(.leading)
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
            .cardSurface()
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .padding(.top, 4)
        .accessibilityIdentifier("system.offline.queue")
    }

    private var summary: String {
        (store.waitingActions + store.reviewActions).map(\.summary).joined(separator: " · ")
    }
}

struct SystemSessionExpiredView: View {
    @Environment(\.signOut) private var signOut

    var body: some View {
        SystemStateScreen {
            SystemStateBody(
                icon: SystemStateIcon(icon: .lock, foreground: AikuColor.link, background: AikuColor.soft),
                title: "Session expired",
                message: Text("For security you were signed out after a long time without activity. Sign in again to continue. Nothing you saved is lost.")
            )
        } actions: {
            AikuButton("Sign in again") { signOut() }
                .accessibilityIdentifier("system.session.signIn")
        }
    }
}

struct SystemNoAccessView: View {
    @Environment(AppRouter.self) private var router
    var module = "Goods in"
    var warehouse = "[Warehouse 2]"
    var permission = "incoming.view"

    var body: some View {
        SystemStateScreen {
            SystemStateHeader(title: "Warehouse 2", kicker: "Access")
        } body: {
            SystemStateBody(
                icon: SystemStateIcon(icon: .shield, foreground: AikuColor.warn, background: AikuColor.warnSoft),
                title: "You don’t have access",
                message: SystemRichText.text("Your account can’t open **\(module)** in **\(warehouse)**. Ask your warehouse manager to add the permission in Aiku.")
            ) {
                Text("Missing permission: \(permission)")
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text4)
            }
        } actions: {
            AikuButton("Back to Home") { router.select(.home) }
                .accessibilityIdentifier("system.noAccess.home")
            AikuButton("Change warehouse", kind: .tertiary) { router.open(ShellRoute.profile) }
        }
    }
}

struct SystemNotFoundView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    var code = "5060123456789"

    var body: some View {
        SystemStateScreen {
            SystemStateHeader(title: "Scan", kicker: "Result")
        } body: {
            SystemStateBody(
                icon: SystemStateIcon(icon: .search, foreground: AikuColor.danger, background: AikuColor.dangerSoft),
                title: "Nothing found for this barcode",
                message: SystemRichText.text("**\(code)** isn’t linked to a SKO, location, delivery note or stock delivery in this warehouse.")
            ) {
                Text("Check the label isn’t damaged, or that you’re in the right warehouse.")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text3)
            }
        } actions: {
            AikuButton("Scan again", icon: .scan) { router.select(.scan) }
                .accessibilityIdentifier("system.notFound.scan")
            AikuButton("Type the code", icon: .keyboard, kind: .tertiary) { router.open(ShellRoute.search) }
            LinkButton("Report unknown barcode") {
                toasts.show("Unknown barcode \(code) reported")
            }
            .frame(minHeight: 44)
        }
    }
}

struct SystemServerErrorView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    var reference = "7F3A-2C19"

    var body: some View {
        SystemStateScreen {
            SystemStateBody(
                icon: SystemStateIcon(icon: .server, foreground: AikuColor.danger, background: AikuColor.dangerSoft),
                title: "Something went wrong",
                message: Text("Aiku didn’t respond. Your last action was not saved. Try again in a moment.")
            ) {
                Text("Error 500 · Ref \(reference) · 09:41")
                    .font(.aiku(12))
                    .monospacedDigit()
                    .foregroundStyle(AikuColor.text4)
            }
        } actions: {
            AikuButton("Try again", icon: .retry) { router.back() }
                .accessibilityIdentifier("system.server.retry")
            AikuButton("Report problem", kind: .tertiary) {
                toasts.show("Problem reported · Ref \(reference)")
            }
        }
    }
}

struct SystemCameraOffView: View {
    @Environment(AppRouter.self) private var router
    @Environment(\.openURL) private var openURL

    var body: some View {
        SystemStateScreen {
            SystemStateHeader(title: "Scan", kicker: "Goods in · SDaw-0093")
        } body: {
            SystemStateBody(
                icon: SystemStateIcon(icon: .camera, foreground: AikuColor.text3, background: AikuColor.surface2),
                title: "Camera access is off",
                message: Text("Aiku needs the camera to scan barcodes. Turn it on in your phone settings, or use a scanner device instead.")
            )
        } actions: {
            AikuButton("Open settings") {
                if let url = URL(string: UIApplication.openSettingsURLString) {
                    openURL(url)
                }
            }
            .accessibilityIdentifier("system.camera.settings")
            AikuButton("Use scanner device", icon: .scannerDevice, kind: .secondary) { router.select(.scan) }
            AikuButton("Type the code", icon: .keyboard, kind: .tertiary) { router.open(ShellRoute.search) }
        }
    }
}

struct SystemEmptySearchView: View {
    @Environment(AppRouter.self) private var router
    @State private var query = "rosemary oil 50ml"

    private let suggestions = ["rosemary", "SKO-0", "A-03"]

    var body: some View {
        SystemStateScreen {
            HeaderContainer {
                HStack(spacing: 8) {
                    Button {
                        router.back()
                    } label: {
                        AppIcon.back.image
                            .font(.system(size: 20, weight: .semibold))
                            .foregroundStyle(AikuColor.onNav)
                            .frame(width: 44, height: 44)
                            .contentShape(Rectangle())
                    }
                    .buttonStyle(.plain)
                    .accessibilityLabel("Back")
                    SearchField(text: $query, placeholder: "Code or name")
                }
            }
        } body: {
            SystemStateBody(
                icon: SystemStateIcon(icon: .search, foreground: AikuColor.text4, background: AikuColor.surface2, size: 80, glyph: 36),
                title: query.isEmpty ? "Search Aiku" : "No results for “\(query)”",
                message: Text("Try a SKO reference (SKO-00412), a location code (A-03-02) or a shorter word."),
                spacing: 14,
                titleSize: 20,
                messageSize: 14
            ) {
                FlowLayout(spacing: 8, lineSpacing: 8) {
                    ForEach(suggestions, id: \.self) { suggestion in
                        Button {
                            query = suggestion
                        } label: {
                            Text(suggestion)
                                .font(.aiku(13))
                                .foregroundStyle(AikuColor.text)
                                .padding(.horizontal, 12)
                                .frame(minHeight: 36)
                                .background(AikuColor.surface, in: Capsule())
                                .overlay(Capsule().strokeBorder(AikuColor.border))
                                .frame(minHeight: 44)
                                .contentShape(Rectangle())
                        }
                        .buttonStyle(.plain)
                        .accessibilityLabel("Search for \(suggestion)")
                    }
                }
            }
        } actions: {
            AikuButton("Scan instead", icon: .scan, kind: .scan) { router.select(.scan) }
                .accessibilityIdentifier("system.emptySearch.scan")
        }
    }
}

struct SystemEmptyTasksView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session

    var body: some View {
        SystemStateScreen {
            SystemStateHeader(title: "Goods out", kicker: session.warehouseLabel)
        } body: {
            SystemStateBody(
                icon: SystemStateIcon(icon: .checkDouble, foreground: AikuColor.success, background: AikuColor.successSoft),
                title: "All caught up",
                message: Text("No delivery notes are waiting for you. New ones appear here as soon as they are assigned."),
                spacing: 14
            )
        } actions: {
            AikuButton("Back to delivery notes") { router.replace(with: GoodsOutRoute.deliveryNotes) }
                .accessibilityIdentifier("system.emptyTasks.deliveryNotes")
            AikuButton("Back to Home", kind: .tertiary) { router.select(.home) }
        }
    }
}
