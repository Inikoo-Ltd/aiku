package io.aiku.droid.data.api

import okhttp3.Request

data class PlatformVersion(
    val minimumVersionCode: Int,
    val latestVersionCode: Int,
    val latestVersionName: String,
    val updateUrl: String?,
)

interface AppVersionApi {
    suspend fun androidVersion(): GoodsOutResult<PlatformVersion>
}

class OkHttpAppVersionApi(private val http: OkHttpMobileApi) : AppVersionApi {

    override suspend fun androidVersion(): GoodsOutResult<PlatformVersion> =
        http.execute(Request.Builder().url("${http.mobileApiUrl}/app-version").get().build())
            ?.let { (statusCode, body) -> AppVersionResponses.parseAndroid(statusCode, body) }
            ?: GoodsOutResult.Unreachable
}
