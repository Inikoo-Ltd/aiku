import SwiftUI

@main
struct AikuApp: App {
    private let apiClient = APIClient.shared
    @State private var permissionStore = PermissionStore(apiClient: .shared)
    @State private var session = Session()
    @State private var authStore = AikuApp.makeAuthStore()

    var body: some Scene {
        WindowGroup {
            RootView()
                .environment(\.apiClient, apiClient)
                .environment(permissionStore)
                .environment(session)
                .environment(authStore)
        }
    }

    private static func makeAuthStore() -> AuthStore {
        let deviceName = DeviceName.make(
            model: UIDevice.current.model,
            systemName: UIDevice.current.systemName,
            systemVersion: UIDevice.current.systemVersion
        )
        #if DEBUG
        if ProcessInfo.processInfo.arguments.contains("-AikuUITestSignedIn") {
            let profile = ProfileResponse(id: 0, username: StaffProfile.sample.username, contactName: StaffProfile.sample.name, email: nil, employee: [])
            let service = AuthService(
                login: { _ in MobileLoginResponse(token: "ui-test", expiresAt: nil, user: profile) },
                fetchProfile: { profile },
                logout: {}
            )
            return AuthStore(service: service, tokens: InMemoryTokenStore(token: "ui-test"), deviceName: deviceName)
        }
        if ProcessInfo.processInfo.arguments.contains("-AikuUITestSignedOut") {
            return AuthStore(service: .live(.shared), tokens: InMemoryTokenStore(), deviceName: deviceName)
        }
        #endif
        return AuthStore(service: .live(.shared), tokens: KeychainTokenStore.shared, deviceName: deviceName)
    }
}
