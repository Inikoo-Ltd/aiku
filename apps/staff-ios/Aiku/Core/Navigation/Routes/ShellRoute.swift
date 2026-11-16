import Foundation

enum ShellRoute: Hashable, Sendable {
    case profile
    case appearance
    case language
    case changePassword
    case staffChat(kind: String, contextID: Int, title: String)
    case search
    case warehouseDashboard
    case scanResult(code: String)
}
