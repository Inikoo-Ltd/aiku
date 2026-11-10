import Foundation

enum ShellRoute: Hashable, Sendable {
    case profile
    case appearance
    case language
    case search
    case warehouseDashboard
    case scanResult(code: String)
}
