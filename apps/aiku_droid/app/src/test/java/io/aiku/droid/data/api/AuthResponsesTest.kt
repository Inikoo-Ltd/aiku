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
        assertEquals(PasswordResetResult.AppKeyRejected, AuthResponses.parsePasswordReset(404, ""))
    }

    @Test
    fun `a server error page becomes a server error with its status`() {
        assertEquals(LoginResult.ServerError(500), AuthResponses.parseLogin(500, "<html>Server Error</html>"))
        assertEquals(LoginResult.ServerError(200), AuthResponses.parseLogin(200, "<html>not json</html>"))
    }

    @Test
    fun `a password reset answers sent or passes on the email error`() {
        assertEquals(PasswordResetResult.LinkSent, AuthResponses.parsePasswordReset(200, ""))

        val rejected = AuthResponses.parsePasswordReset(
            422,
            """{"message":"x","errors":{"email":["The email field must be a valid email address."]}}""",
        ) as PasswordResetResult.Rejected

        assertEquals("The email field must be a valid email address.", rejected.rejection.fieldErrors["email"])
    }

    @Test
    fun `the login request sends the one time password only when there is one`() {
        val withoutCode = AuthResponses.json.encodeToString(LoginRequest("picker", "secret", "Pixel 9"))
        val withCode = AuthResponses.json.encodeToString(LoginRequest("picker", "secret", "Pixel 9", oneTimePassword = "123456"))

        assertFalse(withoutCode.contains("one_time_password"))
        assertTrue(withCode.contains("\"one_time_password\":\"123456\""))
        assertTrue(withCode.contains("\"device_name\":\"Pixel 9\""))
    }
}
