import Foundation

struct AppConfiguration: Sendable {
    let apiBaseURL: URL
    let mobileAppKey: String?
    let sentryDSN: String?

    static let current = AppConfiguration(bundle: .main)

    init(apiBaseURL: URL, mobileAppKey: String? = nil, sentryDSN: String? = nil) {
        self.apiBaseURL = apiBaseURL
        self.mobileAppKey = mobileAppKey?.isEmpty == false ? mobileAppKey : nil
        let dsn = sentryDSN?.trimmingCharacters(in: .whitespacesAndNewlines)
        self.sentryDSN = dsn?.isEmpty == false ? dsn : nil
    }

    init(bundle: Bundle) {
        guard
            let value = bundle.object(forInfoDictionaryKey: "AikuAPIBaseURL") as? String,
            let url = URL(string: value)
        else {
            preconditionFailure("AikuAPIBaseURL is missing from Info.plist")
        }
        self.init(
            apiBaseURL: url,
            mobileAppKey: bundle.object(forInfoDictionaryKey: "AikuMobileAppKey") as? String,
            sentryDSN: bundle.object(forInfoDictionaryKey: "AikuSentryDSN") as? String
        )
    }
}
