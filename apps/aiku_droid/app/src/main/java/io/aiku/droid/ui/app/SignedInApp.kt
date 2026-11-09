package io.aiku.droid.ui.app

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.selection.selectable
import androidx.compose.material3.RadioButton
import androidx.compose.material3.RadioButtonDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.toRoute
import io.aiku.droid.AppContainer
import io.aiku.droid.R
import io.aiku.droid.data.api.WarehouseAccess
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.ui.asText
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.FullScreenLoading
import io.aiku.droid.ui.components.FullScreenMessage
import io.aiku.droid.ui.dashboard.DashboardScreen
import io.aiku.droid.ui.goodsin.GoodsInBacklogScreen
import io.aiku.droid.ui.goodsin.StockDeliveriesScreen
import io.aiku.droid.ui.goodsin.StockDeliveryDetailScreen
import io.aiku.droid.ui.goodsout.DeliveryNoteDetailScreen
import io.aiku.droid.ui.goodsout.DeliveryNotesScreen
import io.aiku.droid.ui.goodsout.GoodsOutBacklogScreen
import io.aiku.droid.ui.goodsout.PackingScreen
import io.aiku.droid.ui.goodsout.ShippersScreen
import io.aiku.droid.ui.inventory.InventoryDashboardScreen
import io.aiku.droid.ui.inventory.SkuDetailScreen
import io.aiku.droid.ui.inventory.SkuFamiliesScreen
import io.aiku.droid.ui.inventory.SkuFamilyDetailScreen
import io.aiku.droid.ui.inventory.SkusScreen
import io.aiku.droid.ui.locations.LocationDetailScreen
import io.aiku.droid.ui.locations.LocationsScreen
import io.aiku.droid.ui.menu.WarehouseMenuScreen
import io.aiku.droid.ui.navigation.AppNavigator
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.AppearanceController
import io.aiku.droid.ui.navigation.AppearanceRoute
import io.aiku.droid.ui.navigation.AreaDetailRoute
import io.aiku.droid.ui.navigation.AreasRoute
import io.aiku.droid.ui.navigation.DashboardRoute
import io.aiku.droid.ui.navigation.DeliveryNoteDetailRoute
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.GoodsInBacklogRoute
import io.aiku.droid.ui.navigation.GoodsOutBacklogRoute
import io.aiku.droid.ui.navigation.InventoryDashboardRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalAppearanceController
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocalWarehouses
import io.aiku.droid.ui.navigation.LocationDetailRoute
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.navigation.NotificationsRoute
import io.aiku.droid.ui.navigation.PackingRoute
import io.aiku.droid.ui.navigation.ProfileRoute
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.navigation.ScanHardwareRoute
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.navigation.ScanSettingsRoute
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.navigation.ShippersRoute
import io.aiku.droid.ui.navigation.SkuDetailRoute
import io.aiku.droid.ui.navigation.SkuFamiliesRoute
import io.aiku.droid.ui.navigation.SkuFamilyDetailRoute
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import io.aiku.droid.ui.navigation.StockDeliveryDetailRoute
import io.aiku.droid.ui.navigation.WarehouseMenuRoute
import io.aiku.droid.ui.navigation.WarehouseOverviewRoute
import io.aiku.droid.ui.notifications.NotificationsScreen
import io.aiku.droid.ui.profile.AppearanceScreen
import io.aiku.droid.ui.profile.ProfileScreen
import io.aiku.droid.ui.scan.ScanCameraScreen
import io.aiku.droid.ui.scan.ScanHardwareScreen
import io.aiku.droid.ui.scan.ScanScreen
import io.aiku.droid.ui.scan.ScanSettingsScreen
import io.aiku.droid.ui.search.SearchScreen
import io.aiku.droid.ui.theme.Appearance
import io.aiku.droid.ui.theme.LocalAikuColors
import io.aiku.droid.ui.warehouse.AreaDetailScreen
import io.aiku.droid.ui.warehouse.AreasScreen
import io.aiku.droid.ui.warehouse.WarehouseOverviewScreen

@Composable
fun SignedInApp(container: AppContainer, session: StaffSession) {
    val warehousesViewModel: WarehousesViewModel = viewModel(
        factory = viewModelFactory {
            initializer { WarehousesViewModel(container.warehouseApi, container.authApi, container.sessions, container.settings) }
        },
    )
    val state by warehousesViewModel.state.collectAsStateWithLifecycle()

    when (val current = state) {
        WarehousesUiState.Loading -> FullScreenLoading()
        is WarehousesUiState.Failed -> FullScreenMessage(
            title = stringResource(R.string.warehouses_failed_title),
            body = current.message.asText(),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                AikuButton(text = stringResource(R.string.warehouses_retry), onClick = warehousesViewModel::load, modifier = Modifier.fillMaxWidth())
                AikuButton(text = stringResource(R.string.sign_out), onClick = warehousesViewModel::signOut, style = ButtonStyle.Outline, modifier = Modifier.fillMaxWidth())
            }
        }
        WarehousesUiState.NoWarehouses -> FullScreenMessage(
            title = stringResource(R.string.warehouses_none_title),
            body = stringResource(R.string.warehouses_none_body),
        ) {
            AikuButton(text = stringResource(R.string.sign_out), onClick = warehousesViewModel::signOut, style = ButtonStyle.Outline, modifier = Modifier.fillMaxWidth())
        }
        is WarehousesUiState.Ready -> WarehouseApp(
            container = container,
            session = session,
            warehouses = current.warehouses,
            selected = current.selected,
            onSelectWarehouse = warehousesViewModel::select,
            onSignOut = warehousesViewModel::signOut,
        )
    }
}

@Composable
private fun WarehouseApp(
    container: AppContainer,
    session: StaffSession,
    warehouses: List<WarehouseAccess>,
    selected: WarehouseAccess,
    onSelectWarehouse: (Long) -> Unit,
    onSignOut: () -> Unit,
) {
    val navController = rememberNavController()
    var isSwitcherOpen by remember { mutableStateOf(false) }
    val navigator = remember(navController) {
        NavControllerNavigator(navController, onShowSwitcher = { isSwitcherOpen = true }, onSignOut = onSignOut)
    }
    val appearance by container.settings.appearance.collectAsStateWithLifecycle()
    val appearanceController = remember(appearance) {
        object : AppearanceController {
            override val appearance: Appearance = appearance
            override fun update(appearance: Appearance) = container.settings.updateAppearance(appearance)
        }
    }

    CompositionLocalProvider(
        LocalAppNavigator provides navigator,
        LocalWarehouse provides selected,
        LocalWarehouses provides warehouses,
        LocalStaff provides session,
        LocalAppearanceController provides appearanceController,
    ) {
        AppNavHost(navController)
        if (isSwitcherOpen) {
            WarehouseSwitcherSheet(
                warehouses = warehouses,
                selected = selected,
                onSwitch = { warehouseId ->
                    isSwitcherOpen = false
                    onSelectWarehouse(warehouseId)
                    navigator.openTab(AppTab.Dashboard)
                },
                onDismiss = { isSwitcherOpen = false },
            )
        }
    }
}

@Composable
private fun AppNavHost(navController: NavHostController) {
    NavHost(navController = navController, startDestination = DashboardRoute) {
        composable<DashboardRoute> { DashboardScreen() }
        composable<WarehouseMenuRoute> { WarehouseMenuScreen() }

        composable<InventoryDashboardRoute> { InventoryDashboardScreen() }
        composable<SkuFamiliesRoute> { SkuFamiliesScreen() }
        composable<SkuFamilyDetailRoute> { SkuFamilyDetailScreen(it.toRoute<SkuFamilyDetailRoute>().code) }
        composable<SkusRoute> { SkusScreen() }
        composable<SkuDetailRoute> { SkuDetailScreen(it.toRoute<SkuDetailRoute>().code) }

        composable<WarehouseOverviewRoute> { WarehouseOverviewScreen() }
        composable<AreasRoute> { AreasScreen() }
        composable<AreaDetailRoute> { AreaDetailScreen(it.toRoute<AreaDetailRoute>().code) }
        composable<LocationsRoute> { LocationsScreen() }
        composable<LocationDetailRoute> { LocationDetailScreen(it.toRoute<LocationDetailRoute>().code) }

        composable<GoodsInBacklogRoute> { GoodsInBacklogScreen() }
        composable<StockDeliveriesRoute> { StockDeliveriesScreen() }
        composable<StockDeliveryDetailRoute> { StockDeliveryDetailScreen(it.toRoute<StockDeliveryDetailRoute>().reference) }

        composable<GoodsOutBacklogRoute> { GoodsOutBacklogScreen() }
        composable<DeliveryNotesRoute> { DeliveryNotesScreen() }
        composable<DeliveryNoteDetailRoute> { DeliveryNoteDetailScreen(it.toRoute<DeliveryNoteDetailRoute>().reference) }
        composable<PackingRoute> { PackingScreen(it.toRoute<PackingRoute>().reference) }
        composable<ShippersRoute> { ShippersScreen() }

        composable<ScanRoute> { ScanScreen() }
        composable<ScanCameraRoute> { ScanCameraScreen() }
        composable<ScanHardwareRoute> { ScanHardwareScreen() }
        composable<ScanSettingsRoute> { ScanSettingsScreen() }

        composable<SearchRoute> { SearchScreen() }
        composable<NotificationsRoute> { NotificationsScreen() }
        composable<ProfileRoute> { ProfileScreen() }
        composable<AppearanceRoute> { AppearanceScreen() }
    }
}

private class NavControllerNavigator(
    private val navController: NavHostController,
    private val onShowSwitcher: () -> Unit,
    private val onSignOut: () -> Unit,
) : AppNavigator {

    override fun open(route: Any) {
        navController.navigate(route) { launchSingleTop = true }
    }

    override fun switchSection(route: Any) {
        val currentDestinationId = navController.currentBackStackEntry?.destination?.id
        navController.navigate(route) {
            if (currentDestinationId != null) {
                popUpTo(currentDestinationId) { inclusive = true }
            }
            launchSingleTop = true
        }
    }

    override fun openTab(tab: AppTab) {
        val route: Any = when (tab) {
            AppTab.Dashboard -> DashboardRoute
            AppTab.Inventory -> SkusRoute
            AppTab.GoodsIn -> StockDeliveriesRoute
            AppTab.GoodsOut -> DeliveryNotesRoute
        }
        navController.navigate(route) {
            popUpTo<DashboardRoute> { saveState = true }
            launchSingleTop = true
            restoreState = true
        }
    }

    override fun back() {
        navController.popBackStack()
    }

    override fun showWarehouseSwitcher() = onShowSwitcher()

    override fun signOut() = onSignOut()
}

@Composable
private fun WarehouseSwitcherSheet(
    warehouses: List<WarehouseAccess>,
    selected: WarehouseAccess,
    onSwitch: (Long) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    var choice by remember { mutableLongStateOf(selected.id) }

    AikuBottomSheet(title = stringResource(R.string.switcher_title), onDismiss = onDismiss) {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            warehouses.forEach { warehouse ->
                val isChosen = warehouse.id == choice
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(min = 56.dp)
                        .clip(ControlShape)
                        .background(if (isChosen) colors.soft else colors.surface)
                        .border(if (isChosen) 2.dp else 1.dp, if (isChosen) colors.link else colors.border, ControlShape)
                        .selectable(selected = isChosen, role = Role.RadioButton, onClick = { choice = warehouse.id })
                        .padding(horizontal = 8.dp, vertical = 8.dp),
                ) {
                    RadioButton(selected = isChosen, onClick = null, colors = RadioButtonDefaults.colors(selectedColor = colors.link))
                    Column(modifier = Modifier.padding(start = 8.dp)) {
                        Text(text = warehouse.name, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
                        Text(
                            text = when {
                                warehouse.id == selected.id -> stringResource(R.string.switcher_current, warehouse.code)
                                warehouse.organisation.id != selected.organisation.id -> "${warehouse.code} · ${warehouse.organisation.name}"
                                else -> warehouse.code
                            },
                            fontSize = 12.sp,
                            color = colors.textTertiary,
                        )
                    }
                }
            }
        }
        Text(text = stringResource(R.string.switcher_note), fontSize = 12.sp, color = colors.textTertiary)
        AikuButton(
            text = stringResource(R.string.switcher_switch),
            onClick = { onSwitch(choice) },
            modifier = Modifier.fillMaxWidth(),
            height = 52.dp,
        )
    }
}
