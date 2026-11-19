import SwiftUI

struct InboxView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    @State private var warehouseStore = WarehouseStore.shared
    private let inbox = ShellInboxStore.shared

    var body: some View {
        let now = Date()
        AppScreen(title: "Inbox", kicker: inbox.hasLoaded ? InboxFormat.kicker(unread: inbox.unreadCount) : nil, back: .none, spacing: 10) {
            if let problem = inbox.loadError {
                InlineAlert(title: "Couldn’t load your inbox", message: problem) {
                    AikuButton("Try again", kind: .secondary, size: .small, fullWidth: false) {
                        Task { await inbox.load() }
                    }
                    .padding(.top, 6)
                }
            }
            if !inbox.notifications.isEmpty {
                markAllReadLink
                ForEach(InboxSection.allCases, id: \.self) { section in
                    let notifications = InboxFormat.notifications(inbox.notifications, in: section, now: now)
                    if !notifications.isEmpty {
                        SectionHeading(section.rawValue)
                            .padding(.top, section == .today ? 0 : 8)
                        CardList(notifications) { notification in
                            row(notification, now: now)
                        }
                    }
                }
                if let problem = inbox.loadMoreError {
                    InlineAlert(title: "Couldn’t load more notifications", message: problem) {
                        AikuButton("Try again", kind: .secondary, size: .small, fullWidth: false) {
                            Task { await inbox.retryMore() }
                        }
                        .padding(.top, 6)
                    }
                } else if inbox.canLoadMore || inbox.isLoadingMore {
                    InboxLoadMoreRow(page: inbox.page) { await inbox.loadMore() }
                }
            } else if inbox.isEmpty {
                EmptyState(icon: .bell, title: "No notifications", message: "When work is assigned to you, taken over, cancelled or changed, you’ll see it here.")
            } else if inbox.loadError == nil {
                Card {
                    SkeletonRow()
                    RowDivider()
                    SkeletonRow()
                    RowDivider()
                    SkeletonRow()
                    RowDivider()
                    SkeletonRow()
                }
            }
        }
        .screenRefreshable { await inbox.load() }
        .task { await inbox.load() }
    }

    private var markAllReadLink: some View {
        HStack {
            Spacer()
            Button("Mark all as read") {
                Task {
                    let succeeded = await inbox.markAllRead()
                    if !succeeded {
                        toasts.show("Couldn’t mark all as read. Try again.", tone: .danger)
                    }
                }
            }
            .font(.aiku(14, .semibold))
            .foregroundStyle(inbox.unreadCount > 0 ? AikuColor.link : AikuColor.text4)
            .underline(inbox.unreadCount > 0, color: AikuColor.primary)
            .frame(minHeight: 44)
            .disabled(inbox.unreadCount == 0)
            .accessibilityIdentifier("shell.inbox.markAllRead")
        }
    }

    private func row(_ notification: MobileInboxNotification, now: Date) -> some View {
        let look = InboxFormat.look(kind: notification.kind, event: notification.event)
        let detail = InboxFormat.detail(notification)
        let elapsed = MyWorkText.since(notification.createdAt, now: now)
        let toggleTitle = notification.isUnread ? "Mark as read" : "Mark as unread"
        return Button {
            open(notification)
        } label: {
            HStack(alignment: .top, spacing: 12) {
                look.icon.image
                    .font(.system(size: 16))
                    .foregroundStyle(look.tone.foreground)
                    .frame(width: 36, height: 36)
                    .background(iconFill(look.tone, isUnread: notification.isUnread), in: Circle())
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 2) {
                    headline(InboxFormat.headline(notification), reference: notification.target?.reference)
                        .font(.aiku(14))
                        .foregroundStyle(AikuColor.text)
                        .multilineTextAlignment(.leading)
                    if let detail {
                        Text(detail)
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                            .lineLimit(notification.target == nil ? nil : 2)
                            .multilineTextAlignment(.leading)
                    }
                    if let elapsed {
                        Text(elapsed)
                            .font(.aiku(12))
                            .foregroundStyle(AikuColor.text3)
                    }
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                if notification.isUnread {
                    Circle()
                        .fill(AikuColor.primary)
                        .frame(width: 10, height: 10)
                        .padding(.top, 4)
                        .accessibilityHidden(true)
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 14)
            .frame(minHeight: 44)
            .background(notification.isUnread ? AikuColor.soft : AikuColor.surface)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .contextMenu {
            Button(toggleTitle) {
                toggleRead(notification)
            }
        }
        .accessibilityLabel(accessibilityText(notification, detail: detail))
        .accessibilityValue(elapsed ?? "")
        .accessibilityAction(named: toggleTitle) {
            toggleRead(notification)
        }
        .accessibilityIdentifier("shell.inbox.\(notification.id)")
    }

    private func iconFill(_ tone: Tone, isUnread: Bool) -> Color {
        InboxFormat.iconBlendsIntoRow(tone: tone, isUnread: isUnread) ? AikuColor.softBd : tone.background
    }

    private func headline(_ text: String, reference: String?) -> Text {
        guard let reference, !reference.isEmpty, let range = text.range(of: reference) else { return Text(text) }
        return Text(text[..<range.lowerBound]) + Text(reference).bold() + Text(text[range.upperBound...])
    }

    private func accessibilityText(_ notification: MobileInboxNotification, detail: String?) -> String {
        let text = [InboxFormat.headline(notification), detail].compactMap { $0 }.joined(separator: ". ")
        return notification.isUnread ? "Unread, \(text)" : text
    }

    private func open(_ notification: MobileInboxNotification) {
        let opening = InboxFormat.opening(for: notification, currentWarehouseID: warehouseStore.selectedID, warehouses: warehouseStore.warehouses)
        switch opening {
        case .textOnly:
            break
        case .unavailable:
            toasts.show("This record is in a warehouse you can’t open here.", tone: .warn)
        case .record(let target, let warehouse):
            if let warehouse {
                warehouseStore.select(warehouse.id)
                session.apply(warehouse: warehouse.name, code: warehouse.code, organisation: warehouse.organisation.name)
                toasts.show(InboxFormat.switchedText(warehouse), tone: .info)
            }
            openRecord(target)
        }
        Task { await inbox.markRead(notification.id) }
    }

    private func openRecord(_ target: MobileInboxTarget) {
        switch target.kind {
        case .deliveryNote: router.open(GoodsOutRoute.deliveryNoteID(id: target.id))
        case .pickingSession: router.open(DispatchRoute.pickingSessionID(id: target.id))
        case .customerReturn: router.open(GoodsInRoute.returnID(id: target.id, reference: target.reference))
        case .palletReturn: router.open(FulfilmentRoute.palletReturn(id: target.id, reference: target.reference))
        }
    }

    private func toggleRead(_ notification: MobileInboxNotification) {
        Task {
            let isUnread = notification.isUnread
            let succeeded: Bool
            if isUnread {
                succeeded = await inbox.markRead(notification.id)
            } else {
                succeeded = await inbox.markUnread(notification.id)
            }
            if !succeeded {
                toasts.show(isUnread ? "Couldn’t mark it as read. Try again." : "Couldn’t mark it as unread. Try again.", tone: .danger)
            }
        }
    }
}

private struct InboxLoadMoreRow: View {
    let page: Int
    let loadMore: () async -> Void
    @State private var isNearScreen = false

    var body: some View {
        let triggerLine = UIScreen.main.bounds.height + 240
        HStack(spacing: 8) {
            ProgressView()
            Text("Loading more…")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
        }
        .frame(maxWidth: .infinity, minHeight: 44)
        .onGeometryChange(for: Bool.self) { proxy in
            proxy.frame(in: .global).minY < triggerLine
        } action: { isNear in
            isNearScreen = isNear
        }
        .task(id: LoadTrigger(isNearScreen: isNearScreen, page: page)) {
            guard isNearScreen else { return }
            await loadMore()
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("Loading more notifications")
    }

    private struct LoadTrigger: Hashable {
        let isNearScreen: Bool
        let page: Int
    }
}
