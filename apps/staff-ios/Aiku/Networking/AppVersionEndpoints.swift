import Foundation

struct MobilePlatformVersion: Equatable, Sendable {
    static let firstVersionCode = 1

    let minimumVersionCode: Int
    let latestVersionCode: Int
    let latestVersionName: String
    let updateURL: URL?

    init(minimumVersionCode: Int?, latestVersionCode: Int?, latestVersionName: String?, updateURL: String?) {
        let minimum = max(minimumVersionCode ?? Self.firstVersionCode, Self.firstVersionCode)
        self.minimumVersionCode = minimum
        self.latestVersionCode = max(latestVersionCode ?? minimum, minimum)
        self.latestVersionName = latestVersionName?.trimmingCharacters(in: .whitespacesAndNewlines) ?? ""
        self.updateURL = Self.webURL(updateURL)
    }

    private static func webURL(_ text: String?) -> URL? {
        guard let text = text?.trimmingCharacters(in: .whitespacesAndNewlines), !text.isEmpty,
              let url = URL(string: text), let scheme = url.scheme?.lowercased(),
              ["http", "https", "itms-apps", "itms-services"].contains(scheme) else { return nil }
        return url
    }
}

private struct MobileAppVersionPlatforms: Decodable, Sendable {
    let ios: MobilePlatformVersionWire?
}

private struct MobilePlatformVersionWire: Decodable, Sendable {
    let minimumVersionCode: Int?
    let latestVersionCode: Int?
    let latestVersionName: String?
    let updateUrl: String?

    private enum CodingKeys: String, CodingKey {
        case minimumVersionCode = "minimum_version_code"
        case latestVersionCode = "latest_version_code"
        case latestVersionName = "latest_version_name"
        case updateUrl = "update_url"
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        minimumVersionCode = Self.lenientInt(container, .minimumVersionCode)
        latestVersionCode = Self.lenientInt(container, .latestVersionCode)
        latestVersionName = try? container.decodeIfPresent(String.self, forKey: .latestVersionName)
        updateUrl = try? container.decodeIfPresent(String.self, forKey: .updateUrl)
    }

    private static func lenientInt(_ container: KeyedDecodingContainer<CodingKeys>, _ key: CodingKeys) -> Int? {
        if let number = try? container.decodeIfPresent(Int.self, forKey: key) {
            return number
        }
        if let text = try? container.decodeIfPresent(String.self, forKey: key) {
            return Int(text.trimmingCharacters(in: .whitespaces))
        }
        return nil
    }
}

extension Endpoint {
    static let appVersion = Endpoint(path: "mobile/app-version", timeout: 15)
}

extension APIClient {
    func fetchIOSVersion() async throws -> MobilePlatformVersion {
        let data = try await send(.appVersion)
        guard let ios = try JSONDecoder().decode(DataEnvelope<MobileAppVersionPlatforms>.self, from: data).value.ios else {
            throw APIError.unexpectedBody
        }
        return MobilePlatformVersion(
            minimumVersionCode: ios.minimumVersionCode,
            latestVersionCode: ios.latestVersionCode,
            latestVersionName: ios.latestVersionName,
            updateURL: ios.updateUrl
        )
    }
}
