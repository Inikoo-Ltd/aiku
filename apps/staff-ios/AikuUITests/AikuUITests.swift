import XCTest

final class AikuUITests: XCTestCase {
    override func setUp() {
        continueAfterFailure = false
    }

    @MainActor
    func testLaunchShowsSignIn() {
        let app = XCUIApplication()
        app.launchArguments = ["-AikuUITestSignedOut"]
        app.launch()

        XCTAssertTrue(app.textFields["login.username"].waitForExistence(timeout: 5))
        XCTAssertTrue(app.secureTextFields["login.password"].exists)
        XCTAssertTrue(app.buttons["login.submit"].exists)
        XCTAssertTrue(app.buttons["login.passkey"].exists)
    }

    @MainActor
    func testEmptySignInShowsValidation() {
        let app = XCUIApplication()
        app.launchArguments = ["-AikuUITestSignedOut"]
        app.launch()

        app.buttons["login.submit"].tap()

        XCTAssertTrue(app.staticTexts["Enter your username."].waitForExistence(timeout: 5))
        XCTAssertTrue(app.staticTexts["Enter your password."].exists)
    }

    @MainActor
    func testForgotPasswordSendsResetLink() {
        let app = XCUIApplication()
        app.launchArguments = ["-AikuUITestSignedOut"]
        app.launch()

        app.buttons["login.forgot"].tap()
        let identifier = app.textFields["forgot.identifier"]
        XCTAssertTrue(identifier.waitForExistence(timeout: 5))
        identifier.tap()
        identifier.typeText("alex.morgan")
        app.buttons["forgot.submit"].tap()

        XCTAssertTrue(app.buttons["forgot.back"].waitForExistence(timeout: 5))
        app.buttons["forgot.back"].tap()
        XCTAssertTrue(app.textFields["login.username"].waitForExistence(timeout: 5))
    }

    @MainActor
    func testTabsAndModulesNavigate() {
        let app = XCUIApplication()
        app.launchArguments = ["-AikuUITestSignedIn"]
        app.launch()

        XCTAssertTrue(app.buttons["shell.home.shortcut.inventory"].waitForExistence(timeout: 5))
        allowLocalNetworkIfAsked()

        app.buttons["shell.home.shortcut.inventory"].tap()
        XCTAssertTrue(app.staticTexts["SKO-00733"].waitForExistence(timeout: 5))
        app.staticTexts["SKO-00733"].tap()
        XCTAssertTrue(app.staticTexts["Bath bomb – rose 180g"].waitForExistence(timeout: 5))

        app.buttons["tab.Menu"].tap()
        XCTAssertTrue(app.buttons["shell.menu.goodsOut.deliveryNotes"].waitForExistence(timeout: 5))
        app.buttons["shell.menu.goodsOut.deliveryNotes"].tap()
        XCTAssertTrue(app.staticTexts["AR24816"].waitForExistence(timeout: 5))

        app.buttons["header.back"].tap()
        XCTAssertTrue(app.buttons["shell.menu.logout"].waitForExistence(timeout: 5))
        app.buttons["shell.menu.logout"].tap()
        XCTAssertTrue(app.textFields["login.username"].waitForExistence(timeout: 5))
    }

    @MainActor
    func testPasskeyExplainsItIsNotAvailableYet() {
        let app = XCUIApplication()
        app.launchArguments = ["-AikuUITestSignedOut"]
        app.launch()

        app.buttons["login.passkey"].tap()

        XCTAssertTrue(app.otherElements["login.error"].waitForExistence(timeout: 5) || app.staticTexts["Couldn’t sign in"].waitForExistence(timeout: 5))
    }

    private func allowLocalNetworkIfAsked() {
        let springboard = XCUIApplication(bundleIdentifier: "com.apple.springboard")
        let allow = springboard.buttons["Allow"]
        if allow.waitForExistence(timeout: 3) {
            allow.tap()
        }
    }
}
