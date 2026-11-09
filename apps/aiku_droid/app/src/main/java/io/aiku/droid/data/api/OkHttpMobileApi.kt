package io.aiku.droid.data.api

import android.util.Log
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.IOException

class OkHttpMobileApi(
    private val client: OkHttpClient,
    baseUrl: String,
    private val ioDispatcher: CoroutineDispatcher = Dispatchers.IO,
) : AuthApi, WarehouseApi {

    private val mobileApiUrl = baseUrl.trimEnd('/') + "/app/api/mobile"
    private val jsonMediaType = "application/json; charset=utf-8".toMediaType()

    override suspend fun login(credentials: LoginCredentials): LoginResult {
        val body = AuthResponses.json.encodeToString(
            LoginRequest(
                username = credentials.username,
                password = credentials.password,
                deviceName = credentials.deviceName,
                oneTimePassword = credentials.oneTimePassword,
            ),
        )

        return execute(post("$mobileApiUrl/login", body, bearerToken = null))
            ?.let { (statusCode, responseBody) -> AuthResponses.parseLogin(statusCode, responseBody) }
            ?: LoginResult.Unreachable
    }

    override suspend fun logout(token: String) {
        execute(post("$mobileApiUrl/logout", body = "{}", bearerToken = token))
    }

    override suspend fun sendPasswordResetLink(email: String): PasswordResetResult {
        val body = AuthResponses.json.encodeToString(PasswordResetRequest(email))

        return execute(post("$mobileApiUrl/password/reset-link", body, bearerToken = null))
            ?.let { (statusCode, responseBody) -> AuthResponses.parsePasswordReset(statusCode, responseBody) }
            ?: PasswordResetResult.Unreachable
    }

    override suspend fun warehouses(token: String): WarehousesResult =
        execute(Request.Builder().url("$mobileApiUrl/warehouses").get().header("Authorization", "Bearer $token").build())
            ?.let { (statusCode, responseBody) -> WarehouseResponses.parse(statusCode, responseBody) }
            ?: WarehousesResult.Unreachable

    private fun post(url: String, body: String, bearerToken: String?): Request = Request.Builder()
        .url(url)
        .post(body.toRequestBody(jsonMediaType))
        .apply { bearerToken?.let { header("Authorization", "Bearer $it") } }
        .build()

    private suspend fun execute(request: Request): Pair<Int, String>? = withContext(ioDispatcher) {
        try {
            client.newCall(request).execute().use { response -> response.code to response.body.string() }
        } catch (exception: IOException) {
            Log.w(LOG_TAG, "${request.method} ${request.url} failed", exception)
            null
        }
    }

    private companion object {
        const val LOG_TAG = "AikuApi"
    }
}
