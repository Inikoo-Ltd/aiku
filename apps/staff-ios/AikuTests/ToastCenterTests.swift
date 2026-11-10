import Testing
@testable import Aiku

@MainActor
struct ToastCenterTests {
    @Test func showsTheLatestToast() {
        let center = ToastCenter()

        center.show(.success, "First")
        center.show(.info, "Second")

        #expect(center.current?.message == "Second")
    }

    @Test func hidesSuccessAfterTheDelay() async throws {
        let center = ToastCenter(hideDelay: .milliseconds(50))

        center.show(.success, "Saved")
        try await Task.sleep(for: .milliseconds(300))

        #expect(center.current == nil)
    }

    @Test func keepsErrorsUntilDismissed() async throws {
        let center = ToastCenter(hideDelay: .milliseconds(50))

        center.show(.error, "Couldn’t save")
        try await Task.sleep(for: .milliseconds(300))

        #expect(center.current?.message == "Couldn’t save")
        center.dismiss(try #require(center.current).id)
        #expect(center.current == nil)
    }

    @Test func ignoresDismissOfAReplacedToast() {
        let center = ToastCenter()

        center.show(.success, "First")
        let firstID = try! #require(center.current).id
        center.show(.info, "Second")
        center.dismiss(firstID)

        #expect(center.current?.message == "Second")
    }
}
