import Foundation
import Testing
@testable import Aiku

@MainActor
struct AppUpdateTests {
    private struct StubTransport: HTTPTransport {
        let status: Int
        let body: String

        func data(for request: URLRequest) async throws -> (Data, URLResponse) {
            (Data(body.utf8), HTTPURLResponse(url: request.url!, statusCode: status, httpVersion: nil, headerFields: nil)!)
        }
    }

    private final class Clock: @unchecked Sendable {
        var date = Date(timeIntervalSince1970: 1_000_000)
    }

    private final class Counter: @unchecked Sendable {
        var calls = 0
    }

    private func client(status: Int = 200, body: String) -> APIClient {
        APIClient(
            configuration: AppConfiguration(apiBaseURL: URL(string: "https://app.aiku.test/app/api")!, mobileAppKey: "key"),
            transport: StubTransport(status: status, body: body)
        )
    }

    private func isolatedDefaults() -> UserDefaults {
        let name = "aiku.tests.update.\(UUID().uuidString)"
        let defaults = UserDefaults(suiteName: name)!
        defaults.removePersistentDomain(forName: name)
        return defaults
    }

    private func version(minimum: Int, latest: Int, name: String = "0.3.0", url: String? = "https://example.com/aiku") -> MobilePlatformVersion {
        MobilePlatformVersion(minimumVersionCode: minimum, latestVersionCode: latest, latestVersionName: name, updateURL: url)
    }

    private func store(installed: Int, answer: MobilePlatformVersion?, defaults: UserDefaults, clock: Clock = Clock(), counter: Counter = Counter()) -> AppVersionStore {
        AppVersionStore(
            installed: InstalledAppVersion(code: installed, name: "0.1.0"),
            defaults: defaults,
            now: { clock.date },
            fetch: {
                counter.calls += 1
                guard let answer else { throw APIError.offline }
                return answer
            }
        )
    }

    @Test func theIOSVersionIsReadFromTheAppVersionEndpoint() async throws {
        let body = #"{"data":{"android":{"minimum_version_code":9,"latest_version_code":9,"latest_version_name":"9.0.0","update_url":null},"ios":{"minimum_version_code":2,"latest_version_code":"3","latest_version_name":" 0.3.0 ","update_url":"https://example.com/aiku"}}}"#

        let version = try await client(body: body).fetchIOSVersion()

        #expect(version == MobilePlatformVersion(minimumVersionCode: 2, latestVersionCode: 3, latestVersionName: "0.3.0", updateURL: "https://example.com/aiku"))
        #expect(version.updateURL?.absoluteString == "https://example.com/aiku")
    }

    @Test func aLatestVersionBelowTheMinimumIsRaisedToTheMinimum() {
        let version = MobilePlatformVersion(minimumVersionCode: 4, latestVersionCode: 2, latestVersionName: "0.4.0", updateURL: "not a link")

        #expect(version.latestVersionCode == 4)
        #expect(version.updateURL == nil)
    }

    @Test func belowTheMinimumTheUpdateIsRequired() {
        let update = AppUpdate(installedVersionCode: 1, version: version(minimum: 2, latest: 3))

        #expect(update == .required(latestVersionName: "0.3.0", updateURL: URL(string: "https://example.com/aiku")))
    }

    @Test func belowTheLatestButAtTheMinimumTheUpdateIsOptional() {
        let update = AppUpdate(installedVersionCode: 2, version: version(minimum: 2, latest: 3))

        #expect(update == .available(latestVersionCode: 3, latestVersionName: "0.3.0", updateURL: URL(string: "https://example.com/aiku")))
    }

    @Test func theLatestVersionNeedsNoUpdate() {
        #expect(AppUpdate(installedVersionCode: 3, version: version(minimum: 2, latest: 3)) == .none)
    }

    @Test func aRequiredUpdateBlocksTheAppAndShowsNoBanner() async {
        let store = store(installed: 1, answer: version(minimum: 2, latest: 3), defaults: isolatedDefaults())

        await store.check()

        #expect(store.update.isRequired)
        #expect(store.banner == nil)
    }

    @Test func anOptionalUpdateShowsTheBannerUntilItIsDismissed() async {
        let defaults = isolatedDefaults()
        let clock = Clock()
        let first = store(installed: 2, answer: version(minimum: 1, latest: 3), defaults: defaults, clock: clock)

        await first.check()
        #expect(first.banner != nil)
        first.dismissBanner()
        #expect(first.banner == nil)

        let reopened = store(installed: 2, answer: version(minimum: 1, latest: 3), defaults: defaults, clock: clock)
        await reopened.check()
        #expect(reopened.banner == nil)

        let newer = store(installed: 2, answer: version(minimum: 1, latest: 4, name: "0.4.0"), defaults: defaults, clock: clock)
        await newer.check()
        #expect(newer.banner == .available(latestVersionCode: 4, latestVersionName: "0.4.0", updateURL: URL(string: "https://example.com/aiku")))
    }

    @Test func offlineTheAppIsNotBlocked() async {
        let store = store(installed: 1, answer: nil, defaults: isolatedDefaults())

        await store.check()

        #expect(store.update == .none)
        #expect(store.banner == nil)
    }

    @Test func anAnswerIsKeptForTenMinutesBeforeAskingAgain() async {
        let clock = Clock()
        let counter = Counter()
        let store = store(installed: 2, answer: version(minimum: 1, latest: 2), defaults: isolatedDefaults(), clock: clock, counter: counter)

        await store.check()
        clock.date.addTimeInterval(9 * 60)
        await store.check()
        #expect(counter.calls == 1)

        clock.date.addTimeInterval(2 * 60)
        await store.check()
        #expect(counter.calls == 2)
    }

    @Test func aFailedCheckIsRetriedOnTheNextOpen() async {
        let counter = Counter()
        let store = store(installed: 2, answer: nil, defaults: isolatedDefaults(), counter: counter)

        await store.check()
        await store.check()

        #expect(counter.calls == 2)
    }
}
