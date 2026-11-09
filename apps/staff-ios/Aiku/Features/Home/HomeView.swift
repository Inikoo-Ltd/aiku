import SwiftUI

struct HomeView: View {
    @Environment(\.apiClient) private var apiClient
    @State private var connectionStatus: ConnectionStatus = .idle

    var body: some View {
        List {
            Section("Server") {
                LabeledContent("Base URL", value: AppConfiguration.current.apiBaseURL.absoluteString)
                LabeledContent("Status", value: connectionStatus.label)
            }
            Section {
                Button("Check connection") {
                    Task { await checkConnection() }
                }
                .disabled(connectionStatus == .checking)
            }
        }
        .navigationTitle("Aiku")
        .accessibilityIdentifier("home.root")
    }

    private func checkConnection() async {
        connectionStatus = .checking
        do {
            try await apiClient.ping()
            connectionStatus = .online
        } catch {
            connectionStatus = .failed(error.localizedDescription)
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
    NavigationStack {
        HomeView()
    }
}
