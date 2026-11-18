package io.aiku.droid.data.api

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.jsonObject
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class PushTokenResponsesTest {

    @Test
    fun `registering sends the token, the android platform and the app version`() {
        val body = Json.parseToJsonElement(PushTokenResponses.registerBody(PushTokenRegistration(fcmToken = "fcm-1", appVersion = "0.1.0"))).jsonObject

        assertEquals(Json.parseToJsonElement("""{"token":"fcm-1","platform":"android","app_version":"0.1.0"}"""), body)
    }

    @Test
    fun `a missing app version is left out`() {
        val body = Json.parseToJsonElement(PushTokenResponses.registerBody(PushTokenRegistration(fcmToken = "fcm-1", appVersion = " "))) as JsonObject

        assertFalse("app_version" in body)
    }

    @Test
    fun `register and unregister read the registered flag`() {
        assertEquals(GoodsOutResult.Loaded(true), PushTokenResponses.parse(200, """{"data":{"registered":true}}"""))
        assertEquals(GoodsOutResult.Loaded(false), PushTokenResponses.parse(200, """{"data":{"registered":false}}"""))
        assertEquals(GoodsOutResult.ServerError(200), PushTokenResponses.parse(200, """{"data":{"registered":null}}"""))
        assertEquals(GoodsOutResult.ServerError(200), PushTokenResponses.parse(200, "not json"))
    }

    @Test
    fun `push token failures are mapped`() {
        assertEquals(GoodsOutResult.SessionExpired, PushTokenResponses.parse(401, """{"message":"Unauthenticated."}"""))
        assertTrue(PushTokenResponses.parse(422, """{"message":"The token field is required.","errors":{"token":["The token field is required."]}}""") is GoodsOutResult.Invalid)
        assertEquals(GoodsOutResult.ServerError(503), PushTokenResponses.parse(503, ""))
    }
}
