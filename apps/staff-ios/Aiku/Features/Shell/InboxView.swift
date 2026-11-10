import SwiftUI

struct InboxView: View {
    @Environment(AppRouter.self) private var router
    @Environment(Session.self) private var session
    @State private var inbox = ShellInboxStore.shared

    var body: some View {
        AppScreen(title: "Inbox", kicker: kicker, back: .none, spacing: 10) {
            HStack {
                Spacer()
                Button("Mark all as read") {
                    inbox.markAllRead()
                    syncUnread()
                }
                .font(.aiku(14, .semibold))
                .foregroundStyle(inbox.unreadCount > 0 ? AikuColor.link : AikuColor.text4)
                .underline(inbox.unreadCount > 0, color: AikuColor.primary)
                .frame(minHeight: 44)
                .disabled(inbox.unreadCount == 0)
                .accessibilityIdentifier("shell.inbox.markAllRead")
            }
            ForEach(ShellInboxSection.allCases, id: \.self) { section in
                let items = inbox.items(in: section)
                if !items.isEmpty {
                    SectionHeading(section.rawValue)
                        .padding(.top, section == .today ? 0 : 8)
                    CardList(items) { item in
                        row(item)
                    }
                }
            }
        }
        .onAppear(perform: syncUnread)
    }

    private var kicker: String {
        inbox.unreadCount == 0 ? "All read" : "\(inbox.unreadCount) unread"
    }

    private func row(_ item: ShellInboxItem) -> some View {
        Button {
            inbox.markRead(item.id)
            syncUnread()
            router.shellFollow(item.link)
        } label: {
            HStack(alignment: .top, spacing: 12) {
                item.icon.image
                    .font(.system(size: 16))
                    .foregroundStyle(item.tone.foreground)
                    .frame(width: 36, height: 36)
                    .background(item.tone.background, in: Circle())
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 2) {
                    message(item)
                        .font(.aiku(14))
                        .foregroundStyle(AikuColor.text)
                        .multilineTextAlignment(.leading)
                    Text(item.time)
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text3)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                if !item.isRead {
                    Circle()
                        .fill(AikuColor.primary)
                        .frame(width: 10, height: 10)
                        .padding(.top, 4)
                        .accessibilityHidden(true)
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 14)
            .background(item.isRead ? AikuColor.surface : AikuColor.soft)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel(item.isRead ? item.accessibilityText : "Unread, \(item.accessibilityText)")
        .accessibilityValue(item.time)
    }

    private func message(_ item: ShellInboxItem) -> Text {
        var text = Text("")
        if let lead = item.lead {
            text = text + Text("\(lead) ")
        }
        text = text + Text(item.emphasis).bold()
        if let tail = item.tail {
            text = text + Text(" \(tail)")
        }
        return text
    }

    private func syncUnread() {
        session.unreadCount = inbox.unreadCount
    }
}
