import SwiftUI

struct ShellScanResultContent: View {
    @Environment(AppRouter.self) private var router
    let target: ShellScanTarget

    var body: some View {
        VStack(alignment: .leading, spacing: 16) {
            HStack(spacing: 10) {
                AppIcon.check.image
                    .font(.system(size: 18, weight: .semibold))
                    .accessibilityHidden(true)
                (Text("Scanned ") + Text(target.code).bold() + Text(" · it’s \(target.kind.article)"))
                    .font(.aiku(14))
                    .frame(maxWidth: .infinity, alignment: .leading)
            }
            .foregroundStyle(AikuColor.success)
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .background(AikuColor.successSoft, in: RoundedRectangle(cornerRadius: 8))
            .accessibilityElement(children: .combine)
            Card(padding: 16, spacing: 12) {
                HStack(spacing: 12) {
                    IconTile(target.kind.icon, tone: .info, size: 48, radius: 8)
                    VStack(alignment: .leading, spacing: 2) {
                        Text(target.code)
                            .font(.aiku(20, .bold))
                            .foregroundStyle(AikuColor.text)
                            .lineLimit(1)
                            .minimumScaleFactor(0.7)
                        Text(target.detail)
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                    }
                }
                AikuButton(target.kind.openTitle, size: .regular) {
                    router.shellFollow(target.link)
                }
                .accessibilityIdentifier("shell.scan.open")
                if !target.actions.isEmpty {
                    HStack(spacing: 8) {
                        ForEach(target.actions) { action in
                            AikuButton(action.title, icon: action.icon, kind: .secondary, size: .regular) {
                                router.shellFollow(action.link)
                            }
                            .lineLimit(1)
                            .minimumScaleFactor(0.8)
                        }
                    }
                }
            }
            .accessibilityElement(children: .contain)
            .accessibilityLabel("\(target.kind.fallbackDetail) found")
        }
    }
}

struct ShellScanCapabilities: View {
    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("What a scan can open")
                .font(.aiku(14, .bold))
                .foregroundStyle(AikuColor.text2)
                .padding(.horizontal, 4)
                .accessibilityAddTraits(.isHeader)
            Card {
                ForEach(Array(ShellScanCatalog.capabilities.enumerated()), id: \.offset) { index, capability in
                    if index > 0 {
                        RowDivider()
                    }
                    HStack(spacing: 12) {
                        capability.icon.image
                            .font(.system(size: 17))
                            .foregroundStyle(AikuColor.text2)
                            .accessibilityHidden(true)
                        Text(capability.text)
                            .font(.aiku(14))
                            .foregroundStyle(AikuColor.text)
                            .frame(maxWidth: .infinity, alignment: .leading)
                    }
                    .padding(.horizontal, 16)
                    .padding(.vertical, 12)
                }
            }
        }
    }
}

struct ShellScanNotFoundCard: View {
    let code: String
    let onTypeCode: () -> Void
    let onReport: () -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(alignment: .top, spacing: 10) {
                AppIcon.question.image
                    .font(.system(size: 20))
                    .foregroundStyle(AikuColor.dangerInk)
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 2) {
                    Text("Barcode not found")
                        .font(.aiku(15, .bold))
                    Text("\(code) is not linked to a SKO or location")
                        .font(.aiku(13))
                }
                .foregroundStyle(AikuColor.dangerInk)
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            HStack(spacing: 8) {
                AikuButton("Type code", kind: .tertiary, size: .small, action: onTypeCode)
                    .accessibilityIdentifier("shell.scan.typeCode")
                AikuButton("Report", kind: .tertiary, size: .small, action: onReport)
                    .accessibilityIdentifier("shell.scan.report")
            }
        }
        .padding(14)
        .background(AikuColor.surface, in: RoundedRectangle(cornerRadius: 8))
        .overlay(RoundedRectangle(cornerRadius: 8).strokeBorder(AikuColor.dangerBd))
        .accessibilityElement(children: .contain)
    }
}

struct ShellScanResultView: View {
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    let code: String

    var body: some View {
        AppScreen(title: "Result", kicker: "Scan", spacing: 16) {
            if let target = ShellScanCatalog.target(for: code) {
                ShellScanResultContent(target: target)
            } else {
                ShellScanNotFoundCard(code: code) {
                    router.open(ShellRoute.search)
                } onReport: {
                    toasts.show("Reported \(code)")
                }
            }
            ShellScanCapabilities()
        }
    }
}
