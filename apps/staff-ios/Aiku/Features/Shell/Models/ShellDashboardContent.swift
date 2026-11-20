import Foundation

enum ShellDashboardContent {
    static let tiles: [ShellShortcut] = [
        ShellShortcut(id: "inventory", title: "Inventory", icon: .sko, requirement: .inventory, link: .inventory(.skos)),
        ShellShortcut(id: "locations", title: "Locations", icon: .locations, requirement: .locations, link: .locations(.locations)),
        ShellShortcut(id: "goodsIn", title: "Goods in", icon: .order, requirement: .goodsInModule, link: .goodsIn(.stockDeliveries)),
        ShellShortcut(id: "goodsOut", title: "Goods out", icon: .deliveryNote, requirement: .goodsOut, link: .goodsOut(.deliveryNotes)),
        ShellShortcut(id: "clocking", title: "Clocking", icon: .clock, requirement: .allStaff, link: .clocking(.clocking))
    ]

    static func tiles(for modules: ModuleAccess) -> [ShellShortcut] {
        tiles.filter { $0.requirement.isMet(by: modules) }.map { $0.opening(for: modules) }
    }
}

enum ShellProfileContent {
    static func syncValue(waiting: Int, needsYou: Int) -> String {
        if needsYou > 0 { return OfflineTexts.count(needsYou, "needs you", "need you") }
        return waiting > 0 ? "\(waiting) waiting" : "All synced"
    }

    static func signOutWaitingMessage(_ waiting: Int) -> String {
        waiting == 1
            ? "1 action from you is waiting; it will be sent when you sign in again on this phone."
            : "\(waiting) actions from you are waiting; they will be sent when you sign in again on this phone."
    }
    static let notifications = "On"
    static let fallbackVersion = "1.0.0"

    static func scanModeTitle(_ mode: ScanInputMode) -> String {
        switch mode {
        case .automatic: "Automatic"
        case .scannerDevice: "Bluetooth scanner"
        case .camera: "Camera"
        }
    }

    static func scanModeDetail(_ mode: ScanInputMode) -> String? {
        mode == .automatic ? "Scanner device if connected, otherwise camera" : nil
    }
}
