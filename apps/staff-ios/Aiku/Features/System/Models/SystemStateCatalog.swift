import Foundation

enum SystemGalleryTarget: Hashable, Sendable {
    case system(SystemRoute)
    case clocking(ClockingRoute)
}

struct SystemGalleryEntry: Identifiable, Hashable, Sendable {
    let code: String
    let title: String
    let detail: String
    let icon: AppIcon
    let tone: Tone
    let target: SystemGalleryTarget

    var id: String { code }
    var subtitle: String { "\(code) · \(detail)" }
}

struct SystemGallerySection: Identifiable, Hashable, Sendable {
    let title: String
    let entries: [SystemGalleryEntry]

    var id: String { title }
}

enum SystemStateCatalog {
    static let sections: [SystemGallerySection] = [
        SystemGallerySection(title: "Offline & sync", entries: [
            SystemGalleryEntry(code: "14", title: "Offline & sync queue", detail: "Waiting to upload", icon: .sync, tone: .warn, target: .system(.syncQueue)),
            SystemGalleryEntry(code: "15", title: "Resolve conflicts", detail: "After syncing the queue", icon: .warning, tone: .warn, target: .system(.conflicts)),
            SystemGalleryEntry(code: "17", title: "Online-only action", detail: "Finalise needs a connection", icon: .offline, tone: .warn, target: .system(.onlineOnlyAction)),
        ]),
        SystemGallerySection(title: "Errors", entries: [
            SystemGalleryEntry(code: "S01", title: "You’re offline", detail: "No internet connection", icon: .offline, tone: .neutral, target: .system(.offline)),
            SystemGalleryEntry(code: "S02", title: "Session expired", detail: "Sign in again", icon: .lock, tone: .info, target: .system(.sessionExpired)),
            SystemGalleryEntry(code: "S03", title: "You don’t have access", detail: "Missing permission", icon: .shield, tone: .warn, target: .system(.noAccess)),
            SystemGalleryEntry(code: "S04", title: "Nothing found for this barcode", detail: "Unknown barcode", icon: .search, tone: .danger, target: .system(.notFound)),
            SystemGalleryEntry(code: "S05", title: "Something went wrong", detail: "Server error", icon: .server, tone: .danger, target: .system(.serverError)),
            SystemGalleryEntry(code: "S06", title: "Camera access is off", detail: "Camera permission", icon: .camera, tone: .neutral, target: .system(.cameraOff)),
            SystemGalleryEntry(code: "S10", title: "Couldn’t move stock", detail: "Action failed", icon: .warning, tone: .danger, target: .system(.actionFailed)),
        ]),
        SystemGallerySection(title: "Empty & loading", entries: [
            SystemGalleryEntry(code: "S07", title: "No results", detail: "Empty search", icon: .search, tone: .neutral, target: .system(.emptySearch)),
            SystemGalleryEntry(code: "S08", title: "All caught up", detail: "No tasks", icon: .checkDouble, tone: .success, target: .system(.emptyTasks)),
            SystemGalleryEntry(code: "S09", title: "Loading dashboard", detail: "Skeleton", icon: .dashboard, tone: .info, target: .system(.loadingDashboard)),
        ]),
        SystemGallerySection(title: "Clocking", entries: [
            SystemGalleryEntry(code: "101", title: "Failed Scan QR", detail: "QR not valid", icon: .qrCode, tone: .danger, target: .clocking(.qrInvalid)),
            SystemGalleryEntry(code: "103", title: "No PIN yet", detail: "No PIN or code yet", icon: .hash, tone: .neutral, target: .clocking(.noPin)),
            SystemGalleryEntry(code: "104", title: "Location blocked", detail: "Location permission", icon: .pin, tone: .warn, target: .clocking(.locationBlocked)),
            SystemGalleryEntry(code: "105", title: "No connection", detail: "Clocking needs a connection", icon: .wifi, tone: .warn, target: .clocking(.noConnection)),
        ]),
    ]
}
