import SwiftUI

struct SignOutAction {
    let action: @MainActor () -> Void

    @MainActor
    func callAsFunction() {
        action()
    }
}

extension EnvironmentValues {
    @Entry var signOut = SignOutAction {}
}
