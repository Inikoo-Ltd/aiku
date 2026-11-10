import Foundation
import Observation

enum ShellInboxSection: String, Hashable, Sendable, CaseIterable {
    case today = "Today"
    case earlier = "Earlier"
}

struct ShellInboxItem: Identifiable, Hashable, Sendable {
    let id: String
    var lead: String?
    let emphasis: String
    var tail: String?
    let time: String
    let icon: AppIcon
    let tone: Tone
    let section: ShellInboxSection
    var isRead: Bool
    let link: ShellLink

    var accessibilityText: String {
        [lead, emphasis, tail].compactMap { $0 }.joined(separator: " ")
    }
}

@MainActor
@Observable
final class ShellInboxStore {
    static let shared = ShellInboxStore()

    private(set) var items: [ShellInboxItem]

    init(items: [ShellInboxItem] = ShellInboxStore.sampleItems) {
        self.items = items
    }

    var unreadCount: Int {
        items.filter { !$0.isRead }.count
    }

    func items(in section: ShellInboxSection) -> [ShellInboxItem] {
        items.filter { $0.section == section }
    }

    func markRead(_ id: String) {
        guard let index = items.firstIndex(where: { $0.id == id }) else { return }
        items[index].isRead = true
    }

    func markAllRead() {
        for index in items.indices {
            items[index].isRead = true
        }
    }

    nonisolated static let sampleItems: [ShellInboxItem] = [
        ShellInboxItem(id: "dn-cancelled", emphasis: "AR24812", tail: "was cancelled while you were picking · stop picking", time: "Just now", icon: .close, tone: .danger, section: .today, isRead: false, link: .goodsOut(.deliveryNote(reference: "AR24812"))),
        ShellInboxItem(id: "dn-assigned", emphasis: "AR24820", tail: "was assigned to you", time: "5 min ago", icon: .deliveryNote, tone: .info, section: .today, isRead: false, link: .goodsOut(.deliveryNote(reference: "AR24820"))),
        ShellInboxItem(id: "sd-arrived", emphasis: "SDaw-0093", tail: "arrived at Goods-in bay 2", time: "1 h ago", icon: .goodsIn, tone: .success, section: .today, isRead: false, link: .goodsIn(.stockDelivery(reference: "SDaw-0093"))),
        ShellInboxItem(id: "audit", lead: "Stock audit requested for", emphasis: "A-12-03", time: "2 h ago", icon: .audit, tone: .warn, section: .today, isRead: false, link: .locations(.location(code: "A-12-03"))),
        ShellInboxItem(id: "out-of-stock", emphasis: "SKO-01402", tail: "is out of stock", time: "Yesterday", icon: .warning, tone: .danger, section: .earlier, isRead: true, link: .inventory(.sko(reference: "SKO-01402"))),
        ShellInboxItem(id: "dispatched", emphasis: "AR24790", tail: "dispatched", time: "Yesterday", icon: .checkDouble, tone: .neutral, section: .earlier, isRead: true, link: .goodsOut(.deliveryNote(reference: "AR24790")))
    ]
}
