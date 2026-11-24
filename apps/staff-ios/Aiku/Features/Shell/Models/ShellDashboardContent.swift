import Foundation
import UserNotifications

enum ShellDashboardContent {
    static let tiles: [ShellShortcut] = [
        ShellShortcut(id: "inventory", title: "Inventory", icon: .faPalletAlt, requirement: .inventory, link: .inventory(.dashboard)),
        ShellShortcut(id: "locations", title: "Locations", icon: .faInventory, requirement: .locations, link: .locations(.warehouse)),
        ShellShortcut(id: "goodsIn", title: "Goods in", icon: .faArrowToBottom, requirement: .goodsInModule, link: .goodsIn(.backlog)),
        ShellShortcut(id: "goodsOut", title: "Goods out", icon: .faArrowFromLeft, requirement: .goodsOut, link: .goodsOut(.backlog))
    ]

    static func tiles(for modules: ModuleAccess) -> [ShellShortcut] {
        tiles.filter { $0.requirement.isMet(by: modules) }.map { $0.opening(for: modules) }
    }
}

@MainActor
enum ShellProfileContent {
    static let noValue = "—"

    static func syncValue(waiting: Int, needsYou: Int) -> String {
        if needsYou > 0 { return L10n.format("%lld need you", needsYou) }
        return waiting > 0 ? L10n.format("%lld waiting", waiting) : L10n.text("All synced")
    }

    static func signOutWaitingMessage(_ waiting: Int) -> String {
        L10n.format("%lld actions from you are waiting; they will be sent when you sign in again on this phone.", waiting)
    }

    static func clockingValue(isClockedIn: Bool?) -> String {
        switch isClockedIn {
        case true?: L10n.text("Clocked in")
        case false?: L10n.text("Clocked out")
        case nil: noValue
        }
    }

    static func weekValue(_ weekTotal: String?) -> String {
        weekTotal.map { L10n.format("This week %@", $0) } ?? noValue
    }

    static func notificationsValue(isAllowed: Bool) -> String {
        L10n.text(isAllowed ? "On" : "Off")
    }

    nonisolated static func notificationsAllowed(_ status: UNAuthorizationStatus) -> Bool {
        switch status {
        case .authorized, .provisional, .ephemeral: true
        default: false
        }
    }

    nonisolated static func hasNotificationSettings(_ status: UNAuthorizationStatus) -> Bool {
        status != .notDetermined
    }

    static func versionValue(name: String?, build: String?) -> String {
        switch (name, build) {
        case let (name?, build?): "\(name) (\(build))"
        case let (name?, nil): name
        default: noValue
        }
    }

    static func scanModeTitle(_ mode: ScanInputMode) -> String {
        switch mode {
        case .automatic: L10n.text("Automatic")
        case .scannerDevice: L10n.text("Bluetooth scanner")
        case .camera: L10n.text("Camera")
        }
    }

    static func scanModeDetail(_ mode: ScanInputMode) -> String? {
        mode == .automatic ? L10n.text("Scanner device if connected, otherwise camera") : nil
    }
}
