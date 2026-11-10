import SwiftUI

struct MyWorkView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @State private var filter: ShellWorkFilter = .all

    var body: some View {
        let items = ShellWork.visibleItems(ShellWork.items, hasWarehouseAccess: session.hasWarehouseAccess)
        AppScreen(
            title: "My work",
            kicker: "\(session.profile.name) · assigned to you",
            back: .none,
            actions: session.hasWarehouseAccess ? [HeaderAction(.search, label: "Search") { router.open(ShellRoute.search) }] : [],
            spacing: 10
        ) {
            FilterChipBar(options: ShellWork.filters(for: items), selection: $filter, title: \.title) { option in
                ShellWork.count(items, for: option)
            }
            if ShellWork.filtered(items, by: filter).isEmpty {
                EmptyState(icon: .work, title: "All caught up", message: "Nothing is assigned to you right now.")
            }
            ForEach(ShellWork.filtered(items, by: filter)) { item in
                row(item)
            }
            Text("Everything assigned to you, from every module you can open. Oldest and blocked first.")
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text4)
                .padding(.horizontal, 4)
                .padding(.top, 6)
        }
    }

    private func row(_ item: ShellWorkItem) -> some View {
        Button {
            open(item)
        } label: {
            HStack(spacing: 12) {
                Capsule()
                    .fill(barColour(item.tone))
                    .frame(width: 6)
                    .frame(maxHeight: .infinity)
                VStack(alignment: .leading, spacing: 3) {
                    FlowLayout(spacing: 8, lineSpacing: 4) {
                        Text(item.reference)
                            .font(.aiku(15, .bold))
                            .foregroundStyle(AikuColor.text)
                        StatusChip(item.state, tone: item.tone)
                    }
                    Text(item.detail)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                        .multilineTextAlignment(.leading)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                ShellOutlineTag(text: item.module.rawValue)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 12)
            .frame(minHeight: 72)
            .fixedSize(horizontal: false, vertical: true)
            .cardSurface()
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("shell.work.\(item.reference)")
    }

    private func barColour(_ tone: Tone) -> Color {
        switch tone {
        case .warn: AikuColor.warn
        case .neutral: AikuColor.borderStrong
        default: AikuColor.primary
        }
    }

    private func open(_ item: ShellWorkItem) {
        if let link = item.link {
            router.shellFollow(link)
        } else {
            toasts.show("Aiku Tasks come to the app later", tone: .info)
        }
    }
}
