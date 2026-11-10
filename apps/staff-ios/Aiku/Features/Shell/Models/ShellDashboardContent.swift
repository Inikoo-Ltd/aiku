import Foundation

struct ShellStat: Identifiable, Hashable, Sendable {
    var id: String { label }
    let value: String
    let label: String
    let tone: Tone
}

struct ShellDashboardPanel: Identifiable, Hashable, Sendable {
    var id: String { title }
    let title: String
    let subtitle: String
    let stats: [ShellStat]
    let link: ShellLink
}

struct ShellStockMovement: Identifiable, Hashable, Sendable {
    var id: String { "\(sko).\(change)" }
    let sko: String
    let change: Chip
    let detail: String
}

enum ShellDashboardContent {
    static let tiles: [ShellShortcut] = [
        ShellShortcut(id: "inventory", title: "Inventory", icon: .sko, link: .inventory(.skos)),
        ShellShortcut(id: "locations", title: "Locations", icon: .locations, link: .locations(.locations)),
        ShellShortcut(id: "goodsIn", title: "Goods in", icon: .order, link: .goodsIn(.stockDeliveries)),
        ShellShortcut(id: "goodsOut", title: "Goods out", icon: .deliveryNote, link: .goodsOut(.deliveryNotes)),
        ShellShortcut(id: "clocking", title: "Clocking", icon: .clock, link: .clocking(.clocking))
    ]

    static let goodsOut = ShellDashboardPanel(title: "Goods out", subtitle: "Dispatching backlog", stats: [
        ShellStat(value: "47", label: "To do", tone: .neutral),
        ShellStat(value: "17", label: "Picking", tone: .info),
        ShellStat(value: "2", label: "Waiting", tone: .warn),
        ShellStat(value: "26", label: "Packed", tone: .info)
    ], link: .goodsOut(.backlog))

    static let dispatchedToday = 142
    static let dispatchTarget = 228

    static let goodsIn = ShellDashboardPanel(title: "Goods in", subtitle: "Incoming hub", stats: [
        ShellStat(value: "3", label: "Arriving", tone: .neutral),
        ShellStat(value: "2", label: "Received", tone: .info),
        ShellStat(value: "1", label: "Checked", tone: .info),
        ShellStat(value: "1", label: "Booking in", tone: .info)
    ], link: .goodsIn(.backlog))

    static let inventory = ShellDashboardPanel(title: "Inventory", subtitle: "4,812 SKOs in this warehouse", stats: [
        ShellStat(value: "17", label: "Low", tone: .warn),
        ShellStat(value: "5", label: "Critical", tone: .danger),
        ShellStat(value: "3", label: "Out of stock", tone: .danger)
    ], link: .inventory(.dashboard))

    static let movements: [ShellStockMovement] = [
        ShellStockMovement(sko: "SKO-00412", change: Chip("Purchase +120", .success), detail: "into A-03-02 · Sam P. · 10 min ago"),
        ShellStockMovement(sko: "SKO-00733", change: Chip("Picked −6", .neutral), detail: "for delivery note AR24790 · you · 24 min ago")
    ]

    static var dispatchedFraction: Double {
        Double(dispatchedToday) / Double(dispatchTarget)
    }
}

enum ShellProfileContent {
    static let timesheetSummary = "This week 6h 13m"
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
