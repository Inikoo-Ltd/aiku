import XCTest

final class AikuUITests: XCTestCase {
    @MainActor
    func testLaunchShowsLogin() {
        let app = XCUIApplication()
        app.launch()

        XCTAssertTrue(app.textFields["login.username"].waitForExistence(timeout: 5))
        XCTAssertTrue(app.secureTextFields["login.password"].exists)
        XCTAssertFalse(app.buttons["login.submit"].isEnabled)
        XCTAssertTrue(app.buttons["login.passkey"].isEnabled)
    }

    @MainActor
    func testSignInOpensDashboardAndTabsNavigate() {
        let app = XCUIApplication()
        app.launch()

        app.buttons["login.passkey"].tap()
        XCTAssertTrue(app.staticTexts["Assigned to me"].waitForExistence(timeout: 5))

        app.buttons["tab.Inventory"].tap()
        XCTAssertTrue(app.staticTexts["SKU-00412"].waitForExistence(timeout: 5))
        app.staticTexts["SKU-00733"].tap()
        XCTAssertTrue(app.staticTexts["Bath bomb – rose 180g"].waitForExistence(timeout: 5))

        app.buttons["Back"].tap()
        app.buttons["tab.Goods out"].tap()
        XCTAssertTrue(app.staticTexts["DN-24811"].waitForExistence(timeout: 5))
    }
}
