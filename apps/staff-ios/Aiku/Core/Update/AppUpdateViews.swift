import SwiftUI

struct UpdateRequiredView: View {
    let latestVersionName: String
    let updateURL: URL?
    let installedVersionName: String
    @Environment(\.openURL) private var openURL
    @State private var toasts = ToastCenter()
    @State private var isLinkUnavailable = false

    var body: some View {
        SystemStateScreen {
            SystemStateBody(
                icon: SystemStateIcon(icon: .sync, foreground: Tone.warn.foreground, background: Tone.warn.background),
                title: "Update required",
                message: Text("This version of the app (\(installedVersionName)) can no longer be used. Install version \(latestVersionName) to keep working.")
            ) {
                if updateURL == nil {
                    Text("Ask your supervisor to install the new version on this phone.")
                        .font(.aiku(15))
                        .foregroundStyle(AikuColor.text2)
                        .multilineTextAlignment(.center)
                }
                if isLinkUnavailable {
                    Text("No app on this phone can open the update link.")
                        .font(.aiku(13))
                        .foregroundStyle(Tone.danger.ink)
                        .multilineTextAlignment(.center)
                        .accessibilityIdentifier("update.required.noApp")
                }
            }
        } actions: {
            if let updateURL {
                AikuButton("Update", icon: .sync) {
                    openURL(updateURL) { accepted in
                        isLinkUnavailable = !accepted
                    }
                }
                .accessibilityIdentifier("update.required.update")
            }
        }
        .environment(toasts)
        .accessibilityIdentifier("update.required")
    }
}

struct AppUpdateBanner: View {
    let latestVersionName: String
    let updateURL: URL?
    let onDismiss: () -> Void
    @Environment(\.openURL) private var openURL
    @Environment(ToastCenter.self) private var toasts

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            AppIcon.sync.image
                .font(.system(size: 18))
                .foregroundStyle(AikuColor.link)
                .padding(.top, 1)
                .accessibilityHidden(true)
            VStack(alignment: .leading, spacing: 2) {
                Text("Version \(latestVersionName) is available")
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.link)
                Text(updateURL == nil ? "Ask your supervisor to install it when convenient." : "Update when you have a moment.")
                    .font(.aiku(13))
                    .foregroundStyle(AikuColor.text2)
                if let updateURL {
                    Button {
                        openURL(updateURL) { accepted in
                            if !accepted {
                                toasts.show("No app on this phone can open the update link.", tone: .danger)
                            }
                        }
                    } label: {
                        Text("Update")
                            .font(.aiku(13, .bold))
                            .foregroundStyle(AikuColor.link)
                            .frame(minHeight: 44)
                    }
                    .buttonStyle(.plain)
                    .accessibilityIdentifier("update.banner.update")
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            Button(action: onDismiss) {
                AppIcon.close.image
                    .font(.system(size: 15, weight: .semibold))
                    .foregroundStyle(AikuColor.text3)
                    .frame(width: 44, height: 44)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityLabel("Dismiss")
            .accessibilityIdentifier("update.banner.dismiss")
        }
        .padding(.leading, 14)
        .padding(.vertical, 8)
        .background(AikuColor.soft, in: RoundedRectangle(cornerRadius: 10))
        .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.softBd))
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("update.banner")
    }
}
