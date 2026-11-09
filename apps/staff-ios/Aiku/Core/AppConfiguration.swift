import Foundation

struct AppConfiguration: Sendable {
    let apiBaseURL: URL

    static let current = AppConfiguration(bundle: .main)

    init(apiBaseURL: URL) {
        self.apiBaseURL = apiBaseURL
    }

    init(bundle: Bundle) {
        guard
            let value = bundle.object(forInfoDictionaryKey: "AikuAPIBaseURL") as? String,
            let url = URL(string: value)
        else {
            preconditionFailure("AikuAPIBaseURL is missing from Info.plist")
        }
        self.init(apiBaseURL: url)
    }
}
