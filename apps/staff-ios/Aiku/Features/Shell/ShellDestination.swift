import SwiftUI

struct ShellDestination: View {
    let route: ShellRoute

    var body: some View {
        switch route {
        case .profile: ShellProfileView()
        case .appearance: ShellAppearanceView()
        case .language: ShellLanguageView()
        case .search: ShellSearchView()
        case .warehouseDashboard: ShellWarehouseDashboardView()
        case .scanResult(let code): ShellScanResultView(code: code)
        }
    }
}
