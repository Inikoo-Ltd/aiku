import SwiftUI

struct RootView: View {
    @State private var isSignedIn = false

    var body: some View {
        if isSignedIn {
            NavigationStack {
                HomeView()
            }
        } else {
            LoginView {
                isSignedIn = true
            }
        }
    }
}

#Preview {
    RootView()
}
