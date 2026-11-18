package io.aiku.droid.data.api

import kotlinx.serialization.Serializable
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody

private const val HTTP_OK = 200
private const val ANDROID_PLATFORM = "android"

data class PushTokenRegistration(val fcmToken: String, val appVersion: String? = null)

interface PushTokenApi {
    suspend fun register(token: String, registration: PushTokenRegistration): GoodsOutResult<Boolean>

    suspend fun unregister(token: String): GoodsOutResult<Boolean>
}

class OkHttpPushTokenApi(private val http: OkHttpMobileApi) : PushTokenApi {

    private val pushTokenUrl = "${http.mobileApiUrl}/push-token"
    private val jsonMediaType = "application/json; charset=utf-8".toMediaType()

    override suspend fun register(token: String, registration: PushTokenRegistration): GoodsOutResult<Boolean> {
        val request = Request.Builder()
            .url(pushTokenUrl)
            .put(PushTokenResponses.registerBody(registration).toRequestBody(jsonMediaType))
            .header("Authorization", "Bearer $token")
            .build()
        return http.execute(request)
            ?.let { (statusCode, body) -> PushTokenResponses.parse(statusCode, body) }
            ?: GoodsOutResult.Unreachable
    }

    override suspend fun unregister(token: String): GoodsOutResult<Boolean> {
        val request = Request.Builder()
            .url(pushTokenUrl)
            .delete()
            .header("Authorization", "Bearer $token")
            .build()
        return http.execute(request)
            ?.let { (statusCode, body) -> PushTokenResponses.parse(statusCode, body) }
            ?: GoodsOutResult.Unreachable
    }
}

@Serializable
private data class PushTokenResponse(val data: PushTokenState? = null)

@Serializable
private data class PushTokenState(val registered: Boolean? = null)

object PushTokenResponses {
    private val json = ClockingResponses.json

    fun registerBody(registration: PushTokenRegistration): String = buildJsonObject {
        put("token", registration.fcmToken)
        put("platform", ANDROID_PLATFORM)
        registration.appVersion?.takeIf { it.isNotBlank() }?.let { put("app_version", it) }
    }.toString()

    fun parse(statusCode: Int, body: String): GoodsOutResult<Boolean> = when (statusCode) {
        HTTP_OK -> runCatching { json.decodeFromString<PushTokenResponse>(body) }.getOrNull()?.data?.registered
            ?.let { GoodsOutResult.Loaded(it) }
            ?: GoodsOutResult.ServerError(statusCode)
        else -> GoodsOutResponses.failure(statusCode, body)
    }
}
