import Foundation

enum ShellDashboardContent {
    static let tiles: [ShellShortcut] = [
        ShellShortcut(id: "inventory", title: "Inventory", icon: .sko, requirement: .inventory, link: .inventory(.skos)),
        ShellShortcut(id: "locations", title: "Locations", icon: .locations, requirement: .locations, link: .locations(.locations)),
        ShellShortcut(id: "goodsIn", title: "Goods in", icon: .order, requirement: .goodsIn, link: .goodsIn(.stockDeliveries)),
        ShellShortcut(id: "goodsOut", title: "Goods out", icon: .deliveryNote, requirement: .goodsOut, link: .goodsOut(.deliveryNotes)),
        ShellShortcut(id: "clocking", title: "Clocking", icon: .clock, requirement: .allStaff, link: .clocking(.clocking))
    ]

    static func tiles(for modules: ModuleAccess) -> [ShellShortcut] {
        tiles.filter { $0.requirement.isMet(by: modules) }
    }
}

enum ShellProfileContent {
    static let syncWaiting = "3 waiting"
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
