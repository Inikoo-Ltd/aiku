package io.aiku.droid

import android.content.Context
import android.os.Build
import android.os.SystemClock
import io.aiku.droid.data.api.AikuHttpClient
import io.aiku.droid.data.api.AppVersionApi
import io.aiku.droid.data.api.AuthApi
import io.aiku.droid.data.api.ClockingApi
import io.aiku.droid.data.api.FulfilmentInboundApi
import io.aiku.droid.data.api.FulfilmentOutboundApi
import io.aiku.droid.data.api.GoodsInApi
import io.aiku.droid.data.api.GoodsOutApi
import io.aiku.droid.data.api.HrApi
import io.aiku.droid.data.api.InboxApi
import io.aiku.droid.data.api.InventoryApi
import io.aiku.droid.data.api.MyWorkApi
import io.aiku.droid.data.api.OkHttpAppVersionApi
import io.aiku.droid.data.api.OkHttpFulfilmentInboundApi
import io.aiku.droid.data.api.OkHttpFulfilmentOutboundApi
import io.aiku.droid.data.api.OkHttpInboxApi
import io.aiku.droid.data.api.OkHttpMobileApi
import io.aiku.droid.data.api.OkHttpMyWorkApi
import io.aiku.droid.data.api.OkHttpPushTokenApi
import io.aiku.droid.data.api.OkHttpSearchApi
import io.aiku.droid.data.api.OkHttpWarehouseDashboardApi
import io.aiku.droid.data.api.PickingSessionApi
import io.aiku.droid.data.api.SearchApi
import io.aiku.droid.data.api.StaffChatApi
import io.aiku.droid.data.api.WarehouseApi
import io.aiku.droid.data.api.WarehouseDashboardApi
import io.aiku.droid.data.images.ImageSource
import io.aiku.droid.data.images.RemoteImages
import io.aiku.droid.data.session.DeviceRecentSearches
import io.aiku.droid.data.session.DeviceSessionRepository
import io.aiku.droid.data.session.DeviceWarehouseCache
import io.aiku.droid.data.session.KeystoreCipher
import io.aiku.droid.data.session.RecentSearches
import io.aiku.droid.data.session.SessionRepository
import io.aiku.droid.data.session.WarehouseCache
import io.aiku.droid.data.settings.AccountLanguageSync
import io.aiku.droid.data.settings.AppLanguageSettings
import io.aiku.droid.data.settings.DeviceAppLocales
import io.aiku.droid.data.settings.DeviceSettings
import io.aiku.droid.data.settings.PreferencesPendingLanguage
import io.aiku.droid.push.FirebasePushTokenSource
import io.aiku.droid.push.NotificationPrompt
import io.aiku.droid.push.PushInbox
import io.aiku.droid.push.PushRegistration
import io.aiku.droid.push.WorkNotifications
import io.aiku.droid.ui.goodsin.StockDeliveryHandoff
import io.aiku.droid.ui.goodsout.DeliveryNoteHandoff
import io.aiku.droid.ui.goodsout.extra.PickingSessionHandoff
import io.aiku.droid.ui.scan.DeviceScanSignals
import io.aiku.droid.ui.scan.ScanController
import io.aiku.droid.update.AppVersionGate
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob

class AppContainer(context: Context) {

    val language = AppLanguageSettings(DeviceAppLocales.store(context))

    private val httpClient = AikuHttpClient.create(
        apiBaseUrl = BuildConfig.AIKU_API_BASE_URL,
        appKey = BuildConfig.AIKU_APP_KEY,
        developmentHostAddress = BuildConfig.DEVELOPMENT_HOST_ADDRESS,
        developmentCaCertificate = BuildConfig.DEVELOPMENT_CA_CERTIFICATE,
        acceptLanguage = { language.acceptLanguage },
    )

    private val mobileApi = OkHttpMobileApi(httpClient, BuildConfig.AIKU_API_BASE_URL)

    val authApi: AuthApi = mobileApi

    val warehouseApi: WarehouseApi = mobileApi

    val clockingApi: ClockingApi = mobileApi

    val hrApi: HrApi = mobileApi

    val goodsOutApi: GoodsOutApi = mobileApi

    val pickingSessionApi: PickingSessionApi = mobileApi

    val staffChatApi: StaffChatApi = mobileApi

    val inventoryApi: InventoryApi = mobileApi

    val goodsInApi: GoodsInApi = mobileApi

    val fulfilmentInboundApi: FulfilmentInboundApi = OkHttpFulfilmentInboundApi(mobileApi)

    val fulfilmentOutboundApi: FulfilmentOutboundApi = OkHttpFulfilmentOutboundApi(mobileApi)

    val myWorkApi: MyWorkApi = OkHttpMyWorkApi(mobileApi)

    val inboxApi: InboxApi = OkHttpInboxApi(mobileApi)
    val searchApi: SearchApi = OkHttpSearchApi(mobileApi, httpClient)

    val warehouseDashboardApi: WarehouseDashboardApi = OkHttpWarehouseDashboardApi(mobileApi)

    val images: ImageSource = RemoteImages(httpClient, BuildConfig.AIKU_API_BASE_URL)

    val deliveryNoteHandoff = DeliveryNoteHandoff()

    val pickingSessionHandoff = PickingSessionHandoff()

    val stockDeliveryHandoff = StockDeliveryHandoff()

    private val sessionCipher = KeystoreCipher(SESSION_KEY_ALIAS)

    val sessions: SessionRepository = DeviceSessionRepository(
        preferences = context.getSharedPreferences(SESSION_PREFERENCES, Context.MODE_PRIVATE),
        cipher = sessionCipher,
    )

    val warehouseCache: WarehouseCache = DeviceWarehouseCache(
        preferences = context.getSharedPreferences(PERMISSIONS_PREFERENCES, Context.MODE_PRIVATE),
        cipher = sessionCipher,
    )

    val recentSearches: RecentSearches = DeviceRecentSearches(
        preferences = context.getSharedPreferences(RECENT_SEARCHES_PREFERENCES, Context.MODE_PRIVATE),
        cipher = sessionCipher,
    )

    val settings = DeviceSettings(context.getSharedPreferences(SETTINGS_PREFERENCES, Context.MODE_PRIVATE))

    val scans = ScanController(preferences = settings.scanPreferences, signals = DeviceScanSignals(context.applicationContext))

    val deviceName: String = listOf(Build.MANUFACTURER, Build.MODEL)
        .filter { it.isNotBlank() }
        .joinToString(" ")
        .take(MAX_DEVICE_NAME_LENGTH)

    private val applicationScope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)

    private val pushTokens = FirebasePushTokenSource(context.applicationContext)

    val workNotifications = WorkNotifications(context.applicationContext)

    val pushRegistration = PushRegistration(
        api = OkHttpPushTokenApi(mobileApi),
        tokens = pushTokens,
        sessions = sessions,
        shown = workNotifications,
        appVersion = BuildConfig.VERSION_NAME,
        scope = applicationScope,
    )

    val pushInbox = PushInbox(inboxApi, sessions, applicationScope)

    val notificationPrompt = NotificationPrompt(context.getSharedPreferences(PUSH_PREFERENCES, Context.MODE_PRIVATE), pushTokens)

    private val appVersionApi: AppVersionApi = OkHttpAppVersionApi(mobileApi)

    val appVersions = AppVersionGate(
        api = appVersionApi,
        installedVersionCode = BuildConfig.VERSION_CODE,
        preferences = context.getSharedPreferences(UPDATE_PREFERENCES, Context.MODE_PRIVATE),
        scope = applicationScope,
        clock = SystemClock::elapsedRealtime,
    )

    val accountLanguage = AccountLanguageSync(
        settings = language,
        api = authApi,
        sessions = sessions,
        pending = PreferencesPendingLanguage(context.getSharedPreferences(ACCOUNT_LANGUAGE_PREFERENCES, Context.MODE_PRIVATE)),
        scope = applicationScope,
    )

    init {
        if (sessions.session.value == null) {
            warehouseCache.clear()
            recentSearches.clear()
        }
        accountLanguage.start()
    }

    fun startPush() {
        if (!pushTokens.isAvailable) {
            return
        }
        workNotifications.createChannel()
        pushRegistration.start()
    }

    private companion object {
        const val SESSION_PREFERENCES = "aiku_session"
        const val SETTINGS_PREFERENCES = "aiku_settings"
        const val PERMISSIONS_PREFERENCES = "aiku_permissions"
        const val PUSH_PREFERENCES = "aiku_push"
        const val RECENT_SEARCHES_PREFERENCES = "aiku_recent_searches"
        const val UPDATE_PREFERENCES = "aiku_updates"
        const val ACCOUNT_LANGUAGE_PREFERENCES = "aiku_account_language"
        const val SESSION_KEY_ALIAS = "aiku_session_key"
        const val MAX_DEVICE_NAME_LENGTH = 100
    }
}
