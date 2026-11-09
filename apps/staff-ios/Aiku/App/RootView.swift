import SwiftUI

struct RootView: View {
    @State private var isSignedIn = false

    var body: some View {
        if isSignedIn {
            MainView()
                .environment(\.signOut, SignOutAction { isSignedIn = false })
        } else {
            LoginView(
                onSignIn: { isSignedIn = true },
                onSignInWithPasskey: { isSignedIn = true }
            )
        }
    }
}

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

#Preview {
    RootView()
}
