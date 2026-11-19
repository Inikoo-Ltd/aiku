package io.aiku.droid.data.cache

import io.aiku.droid.data.api.ClockingResponses
import io.aiku.droid.data.api.ClockingResult
import io.aiku.droid.data.api.ClockingStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.InboxPage
import io.aiku.droid.data.api.InboxResponses
import io.aiku.droid.data.api.MyWorkPage
import io.aiku.droid.data.api.MyWorkResponses
import io.aiku.droid.data.api.TimesheetPeriod
import io.aiku.droid.data.api.WarehouseDashboard
import io.aiku.droid.data.api.WarehouseDashboardResponses
import io.aiku.droid.data.api.WorkBadges

private const val HTTP_OK = 200

class CachedScreen<out T>(val key: String, val parse: (String) -> T?)

object CachedScreens {
    const val USER_SCOPED = 0L
    private const val INBOX_PREFIX = "inbox:"

    val myWork: CachedScreen<MyWorkPage> = CachedScreen("my_work") { MyWorkResponses.parseMyWork(HTTP_OK, it).loaded() }

    val workBadges: CachedScreen<WorkBadges> = CachedScreen("work_badges") { MyWorkResponses.parseBadges(HTTP_OK, it).loaded() }

    val warehouseDashboard: CachedScreen<WarehouseDashboard> =
        CachedScreen("warehouse_dashboard") { WarehouseDashboardResponses.parseDashboard(HTTP_OK, it).loaded() }

    val clockingStatus: CachedScreen<ClockingStatus> = CachedScreen("clocking") { ClockingResponses.parseClocking(HTTP_OK, it).loaded() }

    val clockingThisWeek: CachedScreen<TimesheetPeriod> =
        CachedScreen("clocking_this_week") { ClockingResponses.parseTimesheets(HTTP_OK, it).loaded() }

    val warehouseWorkKeys: Set<String> = setOf(myWork.key, workBadges.key, warehouseDashboard.key)

    val clockingKeys: Set<String> = setOf(clockingStatus.key, clockingThisWeek.key)

    fun inbox(filter: String): CachedScreen<InboxPage> = CachedScreen(INBOX_PREFIX + filter) { InboxResponses.parseList(HTTP_OK, it).loaded() }

    fun isInbox(screen: String): Boolean = screen.startsWith(INBOX_PREFIX)

    private fun <T> GoodsOutResult<T>.loaded(): T? = (this as? GoodsOutResult.Loaded)?.value

    private fun <T> ClockingResult<T>.loaded(): T? = (this as? ClockingResult.Loaded)?.value
}
