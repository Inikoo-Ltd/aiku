import Foundation
import Testing
@testable import Aiku

struct CrashReportingTests {
    private let apiBaseURL = URL(string: "https://example.test/app/api")!

    @Test func crashReportingStaysOffWithoutAKey() {
        #expect(AppConfiguration(apiBaseURL: apiBaseURL).sentryDSN == nil)
        #expect(AppConfiguration(apiBaseURL: apiBaseURL, sentryDSN: "").sentryDSN == nil)
        #expect(AppConfiguration(apiBaseURL: apiBaseURL, sentryDSN: "   ").sentryDSN == nil)
    }

    @Test func theKeyIsReadWithoutSurroundingSpaces() {
        let configuration = AppConfiguration(apiBaseURL: apiBaseURL, sentryDSN: " https://key@o1.ingest.sentry.io/2 ")

        #expect(configuration.sentryDSN == "https://key@o1.ingest.sentry.io/2")
    }

    @Test func reportsAreTaggedWithTheBuildTypeAndNeverStartUnderTests() {
        #expect(CrashReporting.environment == "debug")
        #expect(CrashReporting.isRunningTests)
    }
}
