package io.aiku.droid

import android.content.Context
import android.os.Build
import io.aiku.droid.data.api.AikuHttpClient
import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.ClockingApi
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.HrApi
import io.aiku.droid.data.api.OkHttpMobileApi
import io.aiku.droid.data.api.PickingSessionApi
import io.aiku.droid.data.api.StaffChatApi
import io.aiku.droid.data.api.WarehouseApi
import io.aiku.droid.data.images.ImageSource
import io.aiku.droid.data.images.RemoteImages
import io.aiku.droid.data.session.DeviceSessionRepository
import io.aiku.droid.data.session.DeviceWarehouseCache
import io.aiku.droid.data.session.KeystoreCipher
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.WarehouseCache
import io.aiku.droid.data.settings.DeviceSettings
import io.aiku.droid.ui.goodsout.DeliveryNoteHandoff
import io.aiku.droid.ui.goodsout.extra.PickingSessionHandoff
import io.aiku.droid.ui.scan.ScanController

class AppContainer(context: Context) {

    private val httpClient = AikuHttpClient.create(
        apiBaseUrl = BuildConfig.AIKU_API_BASE_URL,
        appKey = BuildConfig.AIKU_APP_KEY,
        developmentHostAddress = BuildConfig.DEVELOPMENT_HOST_ADDRESS,
        developmentCaCertificate = BuildConfig.DEVELOPMENT_CA_CERTIFICATE,
    )

    private val mobileApi = OkHttpMobileApi(httpClient, BuildConfig.AIKU_API_BASE_URL)

    val authApi: AuthApi = mobileApi

    val warehouseApi: WarehouseApi = mobileApi

    val clockingApi: ClockingApi = mobileApi

    val hrApi: HrApi = mobileApi

    val goodsOutApi: GoodsOutApi = mobileApi

    val pickingSessionApi: PickingSessionApi = mobileApi

    val staffChatApi: StaffChatApi = mobileApi

    val images: ImageSource = RemoteImages(httpClient, BuildConfig.AIKU_API_BASE_URL)

    val scans = ScanController()

    val deliveryNoteHandoff = DeliveryNoteHandoff()

    val pickingSessionHandoff = PickingSessionHandoff()

    private val sessionCipher = KeystoreCipher(SESSION_KEY_ALIAS)

    val sessions: SessionRepository = DeviceSessionRepository(
        preferences = context.getSharedPreferences(SESSION_PREFERENCES, Context.MODE_PRIVATE),
        cipher = sessionCipher,
    )

    val warehouseCache: WarehouseCache = DeviceWarehouseCache(
        preferences = context.getSharedPreferences(PERMISSIONS_PREFERENCES, Context.MODE_PRIVATE),
        cipher = sessionCipher,
    )

    val settings = DeviceSettings(context.getSharedPreferences(SETTINGS_PREFERENCES, Context.MODE_PRIVATE))

    val deviceName: String = listOf(Build.MANUFACTURER, Build.MODEL)
        .filter { it.isNotBlank() }
        .joinToString(" ")
        .take(MAX_DEVICE_NAME_LENGTH)

    init {
        if (sessions.session.value == null) {
            warehouseCache.clear()
        }
    }

    private companion object {
        const val SESSION_PREFERENCES = "aiku_session"
        const val SETTINGS_PREFERENCES = "aiku_settings"
        const val PERMISSIONS_PREFERENCES = "aiku_permissions"
        const val SESSION_KEY_ALIAS = "aiku_session_key"
        const val MAX_DEVICE_NAME_LENGTH = 100
    }
}
