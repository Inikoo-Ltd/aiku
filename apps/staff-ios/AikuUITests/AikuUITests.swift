import XCTest

final class AikuUITests: XCTestCase {
    @MainActor
    func testLaunchShowsLogin() {
        let app = XCUIApplication()
        app.launch()

        XCTAssertTrue(app.textFields["login.username"].waitForExistence(timeout: 5))
        XCTAssertTrue(app.secureTextFields["login.password"].exists)
        XCTAssertFalse(app.buttons["login.submit"].isEnabled)
    }
}
