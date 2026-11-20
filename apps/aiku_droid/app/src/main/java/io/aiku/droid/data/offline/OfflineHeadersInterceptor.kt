package io.aiku.droid.data.offline

import okhttp3.Interceptor
import okhttp3.Response

class OfflineHeadersInterceptor(
    private val deviceId: () -> String,
    private val appVersion: String,
    private val clock: ServerClock,
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request().newBuilder()
            .header(DEVICE_HEADER, deviceId())
            .header(PLATFORM_HEADER, PLATFORM)
            .header(APP_VERSION_HEADER, appVersion)
            .build()
        val response = chain.proceed(request)
        response.headers.getDate(DATE_HEADER)?.toInstant()?.let(clock::record)
        return response
    }

    companion object {
        const val DEVICE_HEADER = "X-Aiku-Device"
        const val PLATFORM_HEADER = "X-Aiku-Platform"
        const val APP_VERSION_HEADER = "X-Aiku-App-Version"
        const val PLATFORM = "android"
        const val DATE_HEADER = "Date"

        fun appVersionOf(versionName: String, versionCode: Int): String = "$versionName ($versionCode)"
    }
}
