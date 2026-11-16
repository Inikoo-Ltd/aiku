import SwiftUI

struct ShellDestination: View {
    let route: ShellRoute

    var body: some View {
        switch route {
        case .profile: ShellProfileView()
        case .appearance: ShellAppearanceView()
        case .language: ShellLanguageView()
        case .changePassword: ShellChangePasswordView()
        case .staffChat(let kind, let contextID, let title):
            if let chatKind = StaffChatContextKind(rawValue: kind) {
                StaffChatView(kind: chatKind, contextID: contextID, title: title)
            } else {
                AppScreen(title: "Ask CRM", kicker: title) {
                    EmptyState(icon: .chat, title: "This conversation can’t be opened", message: "Chats are kept for delivery notes and picking sessions.")
                }
            }
        case .search: ShellSearchView()
        case .warehouseDashboard: ShellWarehouseDashboardView()
        case .scanResult(let code): ShellScanResultView(code: code)
        }
    }
}
