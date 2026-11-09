import SwiftUI

@main
struct AikuApp: App {
    private let apiClient = APIClient.shared

    var body: some Scene {
        WindowGroup {
            RootView()
                .environment(\.apiClient, apiClient)
        }
    }
}
