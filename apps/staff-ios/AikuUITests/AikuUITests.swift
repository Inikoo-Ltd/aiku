import XCTest

final class AikuUITests: XCTestCase {
    @MainActor
    func testLaunchShowsHome() {
        let app = XCUIApplication()
        app.launch()

        XCTAssertTrue(app.navigationBars["Aiku"].waitForExistence(timeout: 5))
    }
}
