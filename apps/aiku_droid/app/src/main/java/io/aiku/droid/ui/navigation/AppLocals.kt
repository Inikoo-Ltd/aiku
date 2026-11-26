package io.aiku.droid.ui.navigation

import androidx.compose.runtime.staticCompositionLocalOf
import io.aiku.droid.data.api.OrganisationSummary
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.data.api.WarehouseAccess
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.theme.Appearance

interface AppNavigator {
    fun open(route: Any)
    fun switchSection(route: Any)
    fun openTab(tab: MainTab)
    fun back()
    fun showWarehouseSwitcher()
    fun showReportProblem()
    fun signOut()
}

enum class MainTab { Home, MyWork, Scan, Inbox, Menu }

interface AppearanceController {
    val appearance: Appearance
    val isThemePerOrganisation: Boolean
    fun update(appearance: Appearance)
    fun updateThemePerOrganisation(isOn: Boolean)
}

interface StartScreenController {
    val isOpeningOnLastScreen: Boolean
    val resumeTarget: ResumeTarget?
    fun updateOpenOnLastScreen(isOn: Boolean)
}

object PreviewNavigator : AppNavigator {
    override fun open(route: Any) = Unit
    override fun switchSection(route: Any) = Unit
    override fun openTab(tab: MainTab) = Unit
    override fun back() = Unit
    override fun showWarehouseSwitcher() = Unit
    override fun showReportProblem() = Unit
    override fun signOut() = Unit
}

val PreviewWarehouse = WarehouseAccess(
    id = 1,
    slug = "wh1",
    code = "WH1",
    name = "Main Warehouse",
    organisation = OrganisationSummary(id = 1, slug = "aw", name = "AW"),
    can = WarehouseAbilities(
        viewLocations = true,
        editLocations = true,
        viewInventory = true,
        moveStock = true,
        viewGoodsIn = true,
        bookIn = true,
        superviseGoodsIn = true,
        editProcurement = true,
        placeStockDeliveries = true,
        viewReturns = true,
        handleReturns = true,
        viewGoodsOut = true,
        pickAndPack = true,
        superviseGoodsOut = true,
        viewFulfilment = true,
        editFulfilment = true,
    ),
)

val NoWarehouseAccess = WarehouseAccess(
    id = 0,
    slug = "",
    code = "",
    name = "",
    organisation = OrganisationSummary(id = 0, slug = "", name = ""),
    can = WarehouseAbilities(),
)

val PreviewStaff = StaffSession(token = "", userId = 1, username = "alex.morgan", displayName = "Alex Morgan")

val LocalAppNavigator = staticCompositionLocalOf<AppNavigator> { PreviewNavigator }
val LocalWarehouse = staticCompositionLocalOf { PreviewWarehouse }
val LocalWarehouses = staticCompositionLocalOf { listOf(PreviewWarehouse) }
val LocalStaff = staticCompositionLocalOf { PreviewStaff }
val LocalAppearanceController = staticCompositionLocalOf<AppearanceController> {
    object : AppearanceController {
        override val appearance = Appearance()
        override val isThemePerOrganisation = false
        override fun update(appearance: Appearance) = Unit
        override fun updateThemePerOrganisation(isOn: Boolean) = Unit
    }
}
val LocalStartScreen = staticCompositionLocalOf<StartScreenController> {
    object : StartScreenController {
        override val isOpeningOnLastScreen = false
        override val resumeTarget: ResumeTarget? = null
        override fun updateOpenOnLastScreen(isOn: Boolean) = Unit
    }
}
