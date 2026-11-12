package io.aiku.droid.data.api

import io.aiku.droid.data.session.StaffSession
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class AuthResponsesTest {

    @Test
    fun `a successful login becomes a session named after the contact`() {
        val body = """{"token":"7|abc","expires_at":null,"user":{"id":42,"username":"picker","contact_name":"Ana Picker","email":"ana@example.com","employee":[],"guest":[]}}"""

        val result = AuthResponses.parseLogin(200, body)

        assertEquals(
            LoginResult.SignedIn(StaffSession(token = "7|abc", userId = 42, username = "picker", displayName = "Ana Picker")),
            result,
        )
    }

    @Test
    fun `a user without a contact name is shown by username`() {
        val body = """{"token":"7|abc","user":{"id":42,"username":"picker","contact_name":""}}"""

        val result = AuthResponses.parseLogin(200, body) as LoginResult.SignedIn

        assertEquals("picker", result.session.displayName)
    }

    @Test
    fun `the otp_required answer asks for the one time password`() {
        val body = """{"message":"Enter OTP from your Authenticator App","code":"otp_required","errors":{"one_time_password":["Enter OTP from your Authenticator App"]}}"""

        assertEquals(LoginResult.OneTimePasswordRequired, AuthResponses.parseLogin(422, body))
    }

    @Test
    fun `refused credentials keep the field the server named`() {
        val body = """{"message":"These credentials do not match our records.","errors":{"username":["These credentials do not match our records."]}}"""

        val result = AuthResponses.parseLogin(422, body) as LoginResult.Rejected

        assertEquals(mapOf("username" to "These credentials do not match our records."), result.rejection.fieldErrors)
        assertEquals("These credentials do not match our records.", result.rejection.firstMessage)
    }

    @Test
    fun `a wrong one time password is a rejection on the code field, not another prompt`() {
        val body = """{"message":"Invalid OTP is given. Please check your Authenticator App","errors":{"one_time_password":["Invalid OTP is given. Please check your Authenticator App"]}}"""

        val result = AuthResponses.parseLogin(422, body) as LoginResult.Rejected

        assertEquals("Invalid OTP is given. Please check your Authenticator App", result.rejection.fieldErrors["one_time_password"])
    }

    @Test
    fun `the route throttle message is used when there are no field errors`() {
        val result = AuthResponses.parseLogin(429, """{"message":"Too Many Attempts."}""") as LoginResult.Rejected

        assertEquals(emptyMap<String, String>(), result.rejection.fieldErrors)
        assertEquals("Too Many Attempts.", result.rejection.firstMessage)
    }

    @Test
    fun `not found means the app key was refused`() {
        assertEquals(LoginResult.AppKeyRejected, AuthResponses.parseLogin(404, "<html>Not Found</html>"))
        assertEquals(PasswordResult.AppKeyRejected, AuthResponses.parsePassword(404, ""))
    }

    @Test
    fun `a server error page becomes a server error with its status`() {
        assertEquals(LoginResult.ServerError(500), AuthResponses.parseLogin(500, "<html>Server Error</html>"))
        assertEquals(LoginResult.ServerError(200), AuthResponses.parseLogin(200, "<html>not json</html>"))
    }

    @Test
    fun `the login request sends the one time password only when there is one`() {
        val withoutCode = AuthResponses.json.encodeToString(LoginRequest("picker", "secret", "Pixel 9"))
        val withCode = AuthResponses.json.encodeToString(LoginRequest("picker", "secret", "Pixel 9", oneTimePassword = "123456"))

        assertFalse(withoutCode.contains("one_time_password"))
        assertTrue(withCode.contains("\"one_time_password\":\"123456\""))
        assertTrue(withCode.contains("\"device_name\":\"Pixel 9\""))
    }

    @Test
    fun `the login request sends the two factor setup token only when there is one`() {
        val withoutToken = AuthResponses.json.encodeToString(LoginRequest("picker", "secret", "Pixel 9", oneTimePassword = "123456"))
        val withToken = AuthResponses.json.encodeToString(
            LoginRequest("picker", "secret", "Pixel 9", oneTimePassword = "123456", twoFactorSetupToken = "eyJpdiI6"),
        )

        assertFalse(withoutToken.contains("two_factor_setup_token"))
        assertTrue(withToken.contains("\"two_factor_setup_token\":\"eyJpdiI6\""))
    }

    @Test
    fun `two factor setup required carries the setup block`() {
        val body = """
            {"message":"Set up two-factor authentication to continue.","code":"two_factor_setup_required",
            "errors":{"one_time_password":["Set up two-factor authentication to continue."]},
            "two_factor_setup":{"setup_token":"eyJpdiI6","secret":"JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP",
            "otpauth_url":"otpauth://totp/Aiku:jane?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=Aiku",
            "issuer":"Aiku","account":"jane","expires_in":600}}
        """.trimIndent()

        assertEquals(
            LoginResult.TwoFactorSetupRequired(
                TwoFactorSetup(
                    setupToken = "eyJpdiI6",
                    secret = "JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP",
                    otpauthUrl = "otpauth://totp/Aiku:jane?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=Aiku",
                    issuer = "Aiku",
                    account = "jane",
                    expiresInSeconds = 600,
                ),
            ),
            AuthResponses.parseLogin(422, body),
        )
    }

    @Test
    fun `two factor setup required without a usable block shows the server message`() {
        val body = """{"message":"Set up two-factor authentication to continue.","code":"two_factor_setup_required","errors":{},"two_factor_setup":{"secret":""}}"""

        val result = AuthResponses.parseLogin(422, body) as LoginResult.Rejected

        assertEquals("Set up two-factor authentication to continue.", result.rejection.firstMessage)
    }

    @Test
    fun `too many wrong codes is a rejection on the code field`() {
        val body = """{"message":"Too many wrong codes. Please try again in 5 minutes.","errors":{"one_time_password":["Too many wrong codes. Please try again in 5 minutes."]}}"""

        val result = AuthResponses.parseLogin(422, body) as LoginResult.Rejected

        assertEquals("Too many wrong codes. Please try again in 5 minutes.", result.rejection.fieldErrors["one_time_password"])
    }

    @Test
    fun `asking for a reset code is done on 200 and passes on the throttle message on 429`() {
        val sent = """{"data":{"sent":true},"message":"If an account matches, we have emailed a 6-digit code to its email address."}"""

        assertEquals(PasswordResult.Done, AuthResponses.parsePassword(200, sent))

        val throttled = AuthResponses.parsePassword(429, """{"message":"Too Many Attempts."}""") as PasswordResult.Rejected

        assertEquals(emptyMap<String, String>(), throttled.rejection.fieldErrors)
        assertEquals("Too Many Attempts.", throttled.rejection.firstMessage)
    }

    @Test
    fun `a missing login is a rejection on the login field`() {
        val body = """{"message":"The login field is required.","errors":{"login":["The login field is required."]}}"""

        val result = AuthResponses.parsePassword(422, body) as PasswordResult.Rejected

        assertEquals("The login field is required.", result.rejection.fieldErrors["login"])
    }

    @Test
    fun `resetting the password is done on 200 and names the code or the password on 422`() {
        assertEquals(PasswordResult.Done, AuthResponses.parsePassword(200, """{"data":{"reset":true}}"""))

        val wrongCode = AuthResponses.parsePassword(
            422,
            """{"message":"The code is wrong or has expired.","errors":{"code":["The code is wrong or has expired."]}}""",
        ) as PasswordResult.Rejected
        val weakPassword = AuthResponses.parsePassword(
            422,
            """{"message":"The password field must be at least 8 characters.","errors":{"password":["The password field must be at least 8 characters.","The password field confirmation does not match."]}}""",
        ) as PasswordResult.Rejected

        assertEquals(mapOf("code" to "The code is wrong or has expired."), wrongCode.rejection.fieldErrors)
        assertEquals(mapOf("password" to "The password field must be at least 8 characters."), weakPassword.rejection.fieldErrors)
    }

    @Test
    fun `changing the password is done on 200, names the current password on 422 and expires on 401`() {
        assertEquals(PasswordResult.Done, AuthResponses.parsePassword(200, """{"data":{"changed":true}}"""))

        val wrongCurrent = AuthResponses.parsePassword(
            422,
            """{"message":"The current password is incorrect.","errors":{"current_password":["The current password is incorrect."]}}""",
        ) as PasswordResult.Rejected

        assertEquals(mapOf("current_password" to "The current password is incorrect."), wrongCurrent.rejection.fieldErrors)
        assertEquals(PasswordResult.SessionExpired, AuthResponses.parsePassword(401, """{"message":"Unauthenticated."}"""))
    }

    @Test
    fun `a password answer that is not json is a server error`() {
        assertEquals(PasswordResult.ServerError(200), AuthResponses.parsePassword(200, "<html>ok</html>"))
        assertEquals(PasswordResult.ServerError(500), AuthResponses.parsePassword(500, "<html>Server Error</html>"))
    }

    @Test
    fun `the password requests use the contract field names`() {
        val reset = AuthResponses.json.encodeToString(PasswordResetRequest("jane", "482913", "new-secret", "new-secret"))
        val change = AuthResponses.json.encodeToString(PasswordChangeRequest("old", "new-secret", "new-secret"))
        val code = AuthResponses.json.encodeToString(PasswordResetCodeRequest("jane"))

        assertEquals("""{"login":"jane","code":"482913","password":"new-secret","password_confirmation":"new-secret"}""", reset)
        assertEquals("""{"current_password":"old","password":"new-secret","password_confirmation":"new-secret"}""", change)
        assertEquals("""{"login":"jane"}""", code)
    }

    @Test
    fun `a profile carries the position and the organisation of the current employment`() {
        val body = """{"data":{"id":292,"username":"louis","name":"Louis","position":"Developer","job_title":"Developer","job_positions":[],"organisation":{"id":2,"code":"AWA","name":"AW Advantage"}}}"""

        val result = AuthResponses.parseProfile(200, body)

        assertEquals(
            ProfileResult.Loaded(
                StaffProfile(
                    id = 292,
                    username = "louis",
                    name = "Louis",
                    position = "Developer",
                    organisation = ProfileOrganisation(id = 2, code = "AWA", name = "AW Advantage"),
                ),
            ),
            result,
        )
    }

    @Test
    fun `a profile without an employment has no position or organisation`() {
        val body = """{"data":{"id":7,"username":"jane","name":null,"position":null,"job_title":null,"job_positions":[],"organisation":null}}"""

        assertEquals(ProfileResult.Loaded(StaffProfile(id = 7, username = "jane")), AuthResponses.parseProfile(200, body))
    }

    @Test
    fun `a refused or broken profile response leaves the profile unavailable`() {
        assertEquals(ProfileResult.Unavailable, AuthResponses.parseProfile(401, """{"message":"Unauthenticated."}"""))
        assertEquals(ProfileResult.Unavailable, AuthResponses.parseProfile(500, "oops"))
    }
}
