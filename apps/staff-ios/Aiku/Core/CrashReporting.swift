import Foundation
import Sentry

enum CrashReporting {
    static var environment: String {
        #if DEBUG
        "debug"
        #else
        "release"
        #endif
    }

    static var isRunningTests: Bool {
        ProcessInfo.processInfo.environment["XCTestConfigurationFilePath"] != nil
    }

    static func start(configuration: AppConfiguration = .current) {
        guard let dsn = configuration.sentryDSN, !isRunningTests else { return }
        SentrySDK.start { options in
            options.dsn = dsn
            options.environment = environment
            options.sendDefaultPii = false
            options.attachScreenshot = false
            options.attachViewHierarchy = false
        }
    }

    static func identify(userID: Int?) {
        guard SentrySDK.isEnabled else { return }
        SentrySDK.setUser(userID.map { User(userId: String($0)) })
    }
}
