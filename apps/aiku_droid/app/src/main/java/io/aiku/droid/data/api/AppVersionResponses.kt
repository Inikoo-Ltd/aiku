package io.aiku.droid.data.api

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

private const val HTTP_OK = 200
private const val FIRST_VERSION_CODE = 1

@Serializable
private data class AppVersionResponse(val data: AppVersionPlatforms? = null)

@Serializable
private data class AppVersionPlatforms(val android: PlatformVersionWire? = null)

@Serializable
private data class PlatformVersionWire(
    @Serializable(with = LenientIntOrNull::class) @SerialName("minimum_version_code") val minimumVersionCode: Int? = null,
    @Serializable(with = LenientIntOrNull::class) @SerialName("latest_version_code") val latestVersionCode: Int? = null,
    @SerialName("latest_version_name") val latestVersionName: String? = null,
    @SerialName("update_url") val updateUrl: String? = null,
)

object AppVersionResponses {
    private val json = ClockingResponses.json

    fun parseAndroid(statusCode: Int, body: String): GoodsOutResult<PlatformVersion> {
        if (statusCode != HTTP_OK) {
            return GoodsOutResponses.failure(statusCode, body)
        }
        val android = runCatching { json.decodeFromString<AppVersionResponse>(body) }.getOrNull()?.data?.android
            ?: return GoodsOutResult.ServerError(statusCode)
        val minimum = android.minimumVersionCode ?: FIRST_VERSION_CODE
        val latest = maxOf(android.latestVersionCode ?: minimum, minimum)
        return GoodsOutResult.Loaded(
            PlatformVersion(
                minimumVersionCode = minimum,
                latestVersionCode = latest,
                latestVersionName = android.latestVersionName?.trim().orEmpty(),
                updateUrl = android.updateUrl?.trim()?.takeIf { it.isNotEmpty() },
            ),
        )
    }
}
