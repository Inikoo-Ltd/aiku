import Foundation
import Observation

enum AppUpdate: Equatable, Sendable {
    case none
    case required(latestVersionName: String, updateURL: URL?)
    case available(latestVersionCode: Int, latestVersionName: String, updateURL: URL?)

    init(installedVersionCode: Int, version: MobilePlatformVersion) {
        if installedVersionCode < version.minimumVersionCode {
            self = .required(latestVersionName: version.latestVersionName, updateURL: version.updateURL)
        } else if installedVersionCode < version.latestVersionCode {
            self = .available(latestVersionCode: version.latestVersionCode, latestVersionName: version.latestVersionName, updateURL: version.updateURL)
        } else {
            self = .none
        }
    }

    var isRequired: Bool {
        if case .required = self { return true }
        return false
    }
}

struct InstalledAppVersion: Equatable, Sendable {
    let code: Int
    let name: String

    static func current(bundle: Bundle = .main) -> InstalledAppVersion {
        let code = (bundle.object(forInfoDictionaryKey: "CFBundleVersion") as? String).flatMap { Int($0) } ?? MobilePlatformVersion.firstVersionCode
        let name = bundle.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? ""
        return InstalledAppVersion(code: code, name: name)
    }
}

@MainActor
@Observable
final class AppVersionStore {
    static let shared = AppVersionStore(fetch: { try await APIClient.shared.fetchIOSVersion() })
    static let checkInterval: TimeInterval = 10 * 60
    static let dismissedVersionKey = "aiku.appUpdate.dismissedVersionCode"

    private(set) var update: AppUpdate = .none
    private(set) var banner: AppUpdate?

    let installed: InstalledAppVersion
    @ObservationIgnored private let fetch: @Sendable () async throws -> MobilePlatformVersion
    @ObservationIgnored private let defaults: UserDefaults
    @ObservationIgnored private let now: @Sendable () -> Date
    @ObservationIgnored private var isChecking = false
    @ObservationIgnored private var answeredAt: Date?

    init(
        installed: InstalledAppVersion = .current(),
        defaults: UserDefaults = .standard,
        now: @escaping @Sendable () -> Date = { Date() },
        fetch: @escaping @Sendable () async throws -> MobilePlatformVersion
    ) {
        self.installed = installed
        self.defaults = defaults
        self.now = now
        self.fetch = fetch
    }

    func check() async {
        if isChecking { return }
        if let answeredAt, now().timeIntervalSince(answeredAt) < Self.checkInterval { return }
        isChecking = true
        defer { isChecking = false }
        guard let version = try? await fetch() else { return }
        answeredAt = now()
        publish(AppUpdate(installedVersionCode: installed.code, version: version))
    }

    func dismissBanner() {
        guard case .available(let latestVersionCode, _, _) = banner else { return }
        defaults.set(latestVersionCode, forKey: Self.dismissedVersionKey)
        banner = nil
    }

    private func publish(_ update: AppUpdate) {
        self.update = update
        if case .available(let latestVersionCode, _, _) = update,
           latestVersionCode != defaults.integer(forKey: Self.dismissedVersionKey) {
            banner = update
        } else {
            banner = nil
        }
    }
}
