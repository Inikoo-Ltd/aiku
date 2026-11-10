import SwiftUI

struct ProfileView: View {
    @Environment(\.apiClient) private var apiClient
    @Environment(\.signOut) private var signOut
    @Environment(AppRouter.self) private var router
    @Environment(ToastCenter.self) private var toasts
    @AppStorage(ScanSettingsKey.inputMode) private var inputMode = ScanInputMode.automatic
    @AppStorage(ScanSettingsKey.sound) private var playsSound = true
    @AppStorage(ScanSettingsKey.vibrate) private var vibrates = true
    @State private var connectionStatus: ConnectionStatus = .idle

    var body: some View {
        VStack(spacing: 0) {
            ScreenHeader(title: SampleData.currentUser.name, subtitle: "\(SampleData.warehouse.name) · \(SampleData.warehouse.code)", eyebrow: "Profile", trailing: .none)
            ScrollView {
                VStack(alignment: .leading, spacing: 20) {
                    scanInputSection
                    serverSection
                    #if DEBUG
                    ActionButton(title: "UI components", systemImage: "square.on.square", kind: .outline) {
                        router.open(.componentGallery)
                    }
                    #endif
                    ActionButton(title: "Log out", kind: .destructiveOutline) { signOut() }
                }
                .padding(16)
            }
        }
        .aikuScreen()
    }

    private var scanInputSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            SectionHeader(title: "Scan input")
            VStack(spacing: 0) {
                ForEach(ScanInputMode.allCases) { mode in
                    Button {
                        inputMode = mode
                    } label: {
                        HStack(spacing: 12) {
                            Image(systemName: inputMode == mode ? "largecircle.fill.circle" : "circle")
                                .font(.system(size: 20))
                                .foregroundStyle(inputMode == mode ? AikuColor.indigo : AikuColor.textFaint)
                            VStack(alignment: .leading, spacing: 2) {
                                Text(mode.title).font(.body.weight(.semibold)).foregroundStyle(AikuColor.textPrimary)
                                Text(mode.detail).font(.caption).foregroundStyle(AikuColor.textSecondary)
                            }
                            Spacer()
                        }
                        .multilineTextAlignment(.leading)
                        .padding(14)
                        .background(inputMode == mode ? AikuColor.indigoSoft : .clear)
                    }
                    .accessibilityAddTraits(inputMode == mode ? .isSelected : [])
                    RowDivider()
                }
                Toggle("Sound on scan", isOn: $playsSound)
                    .padding(14)
                RowDivider()
                Toggle("Vibrate on scan", isOn: $vibrates)
                    .padding(14)
            }
            .tint(AikuColor.indigo)
            .card()
        }
    }

    private var serverSection: some View {
        VStack(alignment: .leading, spacing: 8) {
            SectionHeader(title: "Server")
            VStack(alignment: .leading, spacing: 10) {
                LabeledContent("Base URL", value: AppConfiguration.current.apiBaseURL.absoluteString)
                    .font(.subheadline)
                LabeledContent("Status", value: connectionStatus.label)
                    .font(.subheadline)
                ActionButton(title: "Check connection", kind: .secondary) {
                    Task { await checkConnection() }
                }
                .disabled(connectionStatus == .checking)
            }
            .padding(14)
            .card()
        }
    }

    private func checkConnection() async {
        connectionStatus = .checking
        do {
            try await apiClient.ping()
            connectionStatus = .online
            toasts.show(.success, "Connected to the server")
        } catch {
            connectionStatus = .failed(error.localizedDescription)
            toasts.show(.error, "Couldn’t reach the server. Check your connection.", action: ToastAction(title: "Retry") {
                Task { await checkConnection() }
            })
        }
    }
}

enum ConnectionStatus: Equatable {
    case idle
    case checking
    case online
    case failed(String)

    var label: String {
        switch self {
        case .idle: "Not checked"
        case .checking: "Checking..."
        case .online: "Online"
        case .failed(let message): message
        }
    }
}

#Preview {
    ProfileView()
        .environment(AppRouter())
        .environment(ToastCenter())
}
