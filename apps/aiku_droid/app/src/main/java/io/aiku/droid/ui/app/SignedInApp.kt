package io.aiku.droid.ui.app

import androidx.compose.animation.EnterTransition
import androidx.compose.animation.ExitTransition
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
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
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.FullScreenMessage
import io.aiku.droid.ui.components.LocalSectionTabsHandoff
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.SectionTabsHandoff
import io.aiku.droid.ui.components.ToastHost
import io.aiku.droid.ui.components.ToastState
import io.aiku.droid.ui.navigation.AppNavigator
import io.aiku.droid.ui.navigation.AppearanceController
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalAppearanceController
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocalWarehouses
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.NoWarehouseAccess
import io.aiku.droid.ui.states.LoadingScreen
import io.aiku.droid.ui.states.OfflineScreen
import io.aiku.droid.ui.states.ReportProblemSheet
import io.aiku.droid.ui.states.ServerErrorScreen
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.Appearance
import io.aiku.droid.ui.clocking.ClockScanQrScreen
import io.aiku.droid.ui.clocking.ClockingAdjustmentsScreen
import io.aiku.droid.ui.clocking.ClockingAdjustmentsViewModel
import io.aiku.droid.ui.clocking.ClockingCalendarScreen
import io.aiku.droid.ui.clocking.ClockingCalendarViewModel
import io.aiku.droid.ui.clocking.ClockingErrorScreen
import io.aiku.droid.ui.clocking.ClockingScreen
import io.aiku.droid.ui.clocking.ClockingViewModel
import io.aiku.droid.ui.clocking.LeaveScreen
import io.aiku.droid.ui.clocking.LeaveViewModel
import io.aiku.droid.ui.clocking.LocalClocking
import io.aiku.droid.ui.clocking.LocalClockingAdjustments
import io.aiku.droid.ui.clocking.LocalClockingCalendar
import io.aiku.droid.ui.clocking.LocalLeave
import io.aiku.droid.ui.clocking.LocalOvertime
import io.aiku.droid.ui.clocking.OvertimeScreen
import io.aiku.droid.ui.clocking.OvertimeViewModel
import io.aiku.droid.ui.clocking.TimesheetDayScreen
import io.aiku.droid.ui.clocking.TimesheetsScreen
import io.aiku.droid.ui.dashboard.WarehouseDashboardScreen
import io.aiku.droid.ui.fulfilment.FulfilmentBookInScreen
import io.aiku.droid.ui.fulfilment.FulfilmentBookedInScreen
import io.aiku.droid.ui.fulfilment.FulfilmentDeliveriesScreen
import io.aiku.droid.ui.fulfilment.FulfilmentDeliveryScreen
import io.aiku.droid.ui.fulfilment.FulfilmentReturnsScreen
import io.aiku.droid.ui.fulfilment.PalletIncidentsScreen
import io.aiku.droid.ui.fulfilment.PalletScreen
import io.aiku.droid.ui.fulfilment.ReturnDispatchedScreen
import io.aiku.droid.ui.fulfilment.ReturnPickItemsScreen
import io.aiku.droid.ui.fulfilment.ReturnPickPalletsScreen
import io.aiku.droid.ui.fulfilment.ReturnPickedScreen
import io.aiku.droid.ui.fulfilment.ReturnShipmentScreen
import io.aiku.droid.ui.fulfilment.StoredItemAuditScreen
import io.aiku.droid.ui.fulfilment.StoredItemsScreen
import io.aiku.droid.ui.fulfilment.StoredPalletsScreen
import io.aiku.droid.ui.goodsin.BookedInScreen
import io.aiku.droid.ui.goodsin.BookingInScreen
import io.aiku.droid.ui.goodsin.GoodsInBacklogScreen
import io.aiku.droid.ui.goodsin.GoodsInReturnScreen
import io.aiku.droid.ui.goodsin.GoodsInReturnsScreen
import io.aiku.droid.ui.goodsin.PlaceItemNoPickingLocationScreen
import io.aiku.droid.ui.goodsin.PlaceItemScreen
import io.aiku.droid.ui.goodsin.ScanCameraScreen
import io.aiku.droid.ui.goodsin.StockDeliveriesScreen
import io.aiku.droid.ui.goodsin.StockDeliveryArrivingScreen
import io.aiku.droid.ui.goodsin.StockDeliveryCheckingScreen
import io.aiku.droid.ui.goodsin.StockDeliveryChecklistScreen
import io.aiku.droid.ui.goodsout.AddShipmentScreen
import io.aiku.droid.ui.goodsout.DeliveryNoteAllPickedScreen
import io.aiku.droid.ui.goodsout.DeliveryNoteCollectedScreen
import io.aiku.droid.ui.goodsout.DeliveryNoteCollectionScreen
import io.aiku.droid.ui.goodsout.DeliveryNoteDispatchedScreen
import io.aiku.droid.ui.goodsout.DeliveryNoteFinalisedScreen
import io.aiku.droid.ui.goodsout.DeliveryNotePackingScreen
import io.aiku.droid.ui.goodsout.DeliveryNotePickedScreen
import io.aiku.droid.ui.goodsout.DeliveryNotePickingScreen
import io.aiku.droid.ui.goodsout.DeliveryNoteToDoScreen
import io.aiku.droid.ui.goodsout.DeliveryNotesScreen
import io.aiku.droid.ui.goodsout.FastPickingScreen
import io.aiku.droid.ui.goodsout.GoodsOutBacklogScreen
import io.aiku.droid.ui.goodsout.ManualShipmentScreen
import io.aiku.droid.ui.goodsout.PackingQueueScreen
import io.aiku.droid.ui.goodsout.ScanToPickScreen
import io.aiku.droid.ui.goodsout.ShipmentsScreen
import io.aiku.droid.ui.goodsout.extra.AskCrmScreen
import io.aiku.droid.ui.goodsout.extra.CustomsFileScreen
import io.aiku.droid.ui.goodsout.extra.DeliveryNoteCancelledScreen
import io.aiku.droid.ui.goodsout.extra.DeliveryNoteIncompleteSetScreen
import io.aiku.droid.ui.goodsout.extra.DeliveryNoteTakeOverScreen
import io.aiku.droid.ui.goodsout.extra.DeliveryNoteWaitingScreen
import io.aiku.droid.ui.goodsout.extra.DispatchedEmailsScreen
import io.aiku.droid.ui.goodsout.extra.ExternalShipmentScreen
import io.aiku.droid.ui.goodsout.extra.PackByScanScreen
import io.aiku.droid.ui.goodsout.extra.PickByLocationScreen
import io.aiku.droid.ui.goodsout.extra.PickedBaysScreen
import io.aiku.droid.ui.goodsout.extra.PickingSessionScreen
import io.aiku.droid.ui.goodsout.extra.PickingSessionsScreen
import io.aiku.droid.ui.goodsout.extra.ShippersScreen
import io.aiku.droid.ui.goodsout.extra.TariffCodesScreen
import io.aiku.droid.ui.goodsout.extra.TrolleysScreen
import io.aiku.droid.ui.home.HomeScreen
import io.aiku.droid.ui.inbox.InboxScreen
import io.aiku.droid.ui.inventory.InventoryDashboardScreen
import io.aiku.droid.ui.inventory.SkuAbnormalitiesScreen
import io.aiku.droid.ui.inventory.SkuFamiliesScreen
import io.aiku.droid.ui.inventory.SkuFamilyScreen
import io.aiku.droid.ui.inventory.SkuScreen
import io.aiku.droid.ui.inventory.SkusScreen
import io.aiku.droid.ui.inventory.StockHistoryScreen
import io.aiku.droid.ui.locations.AreaScreen
import io.aiku.droid.ui.locations.AreasScreen
import io.aiku.droid.ui.locations.LocationScreen
import io.aiku.droid.ui.locations.LocationSkuScreen
import io.aiku.droid.ui.locations.LocationsScreen
import io.aiku.droid.ui.locations.WarehouseOverviewScreen
import io.aiku.droid.ui.menu.MenuScreen
import io.aiku.droid.ui.mywork.MyWorkScreen
import io.aiku.droid.ui.navigation.AddShipmentRoute
import io.aiku.droid.ui.navigation.AppearanceRoute
import io.aiku.droid.ui.navigation.AreaRoute
import io.aiku.droid.ui.navigation.AreasRoute
import io.aiku.droid.ui.navigation.AskCrmRoute
import io.aiku.droid.ui.navigation.BookedInRoute
import io.aiku.droid.ui.navigation.BookingInRoute
import io.aiku.droid.ui.navigation.ClockScanQrRoute
import io.aiku.droid.ui.navigation.ClockingAdjustmentsRoute
import io.aiku.droid.ui.navigation.ClockingCalendarRoute
import io.aiku.droid.ui.navigation.ClockingErrorRoute
import io.aiku.droid.ui.navigation.ClockingRoute
import io.aiku.droid.ui.navigation.ConflictRoute
import io.aiku.droid.ui.navigation.CustomsFileRoute
import io.aiku.droid.ui.navigation.DeliveryNoteAllPickedRoute
import io.aiku.droid.ui.navigation.DeliveryNoteCancelledRoute
import io.aiku.droid.ui.navigation.DeliveryNoteCollectedRoute
import io.aiku.droid.ui.navigation.DeliveryNoteCollectionRoute
import io.aiku.droid.ui.navigation.DeliveryNoteDispatchedRoute
import io.aiku.droid.ui.navigation.DeliveryNoteFinalisedRoute
import io.aiku.droid.ui.navigation.DeliveryNoteIncompleteSetRoute
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.DeliveryNoteTakeOverRoute
import io.aiku.droid.ui.navigation.DeliveryNoteToDoRoute
import io.aiku.droid.ui.navigation.DeliveryNoteWaitingRoute
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.DispatchedEmailsRoute
import io.aiku.droid.ui.navigation.ExternalShipmentRoute
import io.aiku.droid.ui.navigation.FastPickingRoute
import io.aiku.droid.ui.navigation.FulfilmentBookInRoute
import io.aiku.droid.ui.navigation.FulfilmentBookedInRoute
import io.aiku.droid.ui.navigation.FulfilmentDeliveriesRoute
import io.aiku.droid.ui.navigation.FulfilmentDeliveryRoute
import io.aiku.droid.ui.navigation.FulfilmentReturnsRoute
import io.aiku.droid.ui.navigation.GoodsInBacklogRoute
import io.aiku.droid.ui.navigation.GoodsInReturnRoute
import io.aiku.droid.ui.navigation.GoodsInReturnsRoute
import io.aiku.droid.ui.navigation.GoodsOutBacklogRoute
import io.aiku.droid.ui.navigation.HomeRoute
import io.aiku.droid.ui.navigation.InboxRoute
import io.aiku.droid.ui.navigation.InventoryDashboardRoute
import io.aiku.droid.ui.navigation.LanguageRoute
import io.aiku.droid.ui.navigation.LeaveRoute
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.LocationSkuRoute
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.navigation.ManualShipmentRoute
import io.aiku.droid.ui.navigation.MenuRoute
import io.aiku.droid.ui.navigation.MyWorkRoute
import io.aiku.droid.ui.navigation.NoTasksRoute
import io.aiku.droid.ui.navigation.NotDesignedRoute
import io.aiku.droid.ui.navigation.OnlineOnlyRoute
import io.aiku.droid.ui.navigation.OvertimeRoute
import io.aiku.droid.ui.navigation.PackByScanRoute
import io.aiku.droid.ui.navigation.PackingQueueRoute
import io.aiku.droid.ui.navigation.PalletIncidentsRoute
import io.aiku.droid.ui.navigation.PalletRoute
import io.aiku.droid.ui.navigation.PickByLocationRoute
import io.aiku.droid.ui.navigation.PickedBaysRoute
import io.aiku.droid.ui.navigation.PickingSessionRoute
import io.aiku.droid.ui.navigation.PickingSessionsRoute
import io.aiku.droid.ui.navigation.PlaceItemNoPickingLocationRoute
import io.aiku.droid.ui.navigation.PlaceItemRoute
import io.aiku.droid.ui.navigation.ProfileRoute
import io.aiku.droid.ui.navigation.ResolveConflictsRoute
import io.aiku.droid.ui.navigation.ReturnDispatchedRoute
import io.aiku.droid.ui.navigation.ReturnPickItemsRoute
import io.aiku.droid.ui.navigation.ReturnPickPalletsRoute
import io.aiku.droid.ui.navigation.ReturnPickedRoute
import io.aiku.droid.ui.navigation.ReturnShipmentRoute
import io.aiku.droid.ui.navigation.ScanCameraRoute
import io.aiku.droid.ui.navigation.ScanResultRoute
import io.aiku.droid.ui.navigation.ScanToPickRoute
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.navigation.ShipmentsRoute
import io.aiku.droid.ui.navigation.ShippersRoute
import io.aiku.droid.ui.navigation.SkuAbnormalitiesRoute
import io.aiku.droid.ui.navigation.SkuFamiliesRoute
import io.aiku.droid.ui.navigation.SkuFamilyRoute
import io.aiku.droid.ui.navigation.SkuRoute
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.navigation.StatePreviewRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import io.aiku.droid.ui.navigation.StockDeliveryArrivingRoute
import io.aiku.droid.ui.navigation.StockDeliveryCheckingRoute
import io.aiku.droid.ui.navigation.StockDeliveryChecklistRoute
import io.aiku.droid.ui.navigation.StockHistoryRoute
import io.aiku.droid.ui.navigation.StoredItemAuditRoute
import io.aiku.droid.ui.navigation.StoredItemsRoute
import io.aiku.droid.ui.navigation.StoredPalletsRoute
import io.aiku.droid.ui.navigation.SyncQueueRoute
import io.aiku.droid.ui.navigation.TariffCodesRoute
import io.aiku.droid.ui.navigation.TimesheetDayRoute
import io.aiku.droid.ui.navigation.TimesheetsRoute
import io.aiku.droid.ui.navigation.TrolleysRoute
import io.aiku.droid.ui.navigation.WarehouseDashboardRoute
import io.aiku.droid.ui.navigation.WarehouseOverviewRoute
import io.aiku.droid.ui.profile.AppearanceScreen
import io.aiku.droid.ui.profile.LanguageScreen
import io.aiku.droid.ui.profile.ProfileScreen
import io.aiku.droid.ui.scan.ScanResultScreen
import io.aiku.droid.ui.search.SearchScreen
import io.aiku.droid.ui.states.NoTasksScreen
import io.aiku.droid.ui.states.NotDesignedScreen
import io.aiku.droid.ui.states.StatePreviewScreen
import io.aiku.droid.ui.sync.ConflictScreen
import io.aiku.droid.ui.sync.OnlineOnlyScreen
import io.aiku.droid.ui.sync.ResolveConflictsScreen
import io.aiku.droid.ui.sync.SyncQueueScreen

@Composable
fun SignedInApp(container: AppContainer, session: StaffSession) {
    val warehousesViewModel: WarehousesViewModel = viewModel(
        factory = viewModelFactory {
            initializer { WarehousesViewModel(container.warehouseApi, container.authApi, container.sessions, container.settings) }
        },
    )
    val state by warehousesViewModel.state.collectAsStateWithLifecycle()

    when (val current = state) {
        WarehousesUiState.Loading -> LoadingScreen()
        WarehousesUiState.Offline -> OfflineScreen(onRetry = warehousesViewModel::load)
        is WarehousesUiState.ServerError -> ServerErrorScreen(onRetry = warehousesViewModel::load, statusCode = current.statusCode)
        WarehousesUiState.SessionExpired -> SessionExpiredScreen(onSignIn = warehousesViewModel::signInAgain)
        is WarehousesUiState.Failed -> FullScreenMessage(
            title = stringResource(R.string.warehouses_failed_title),
            body = current.message.asText(),
        ) {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                AikuButton(text = stringResource(R.string.warehouses_retry), onClick = warehousesViewModel::load, modifier = Modifier.fillMaxWidth())
                AikuButton(text = stringResource(R.string.sign_out), onClick = warehousesViewModel::signOut, style = ButtonStyle.Outline, modifier = Modifier.fillMaxWidth())
            }
        }
        WarehousesUiState.NoWarehouses -> WarehouseApp(
            container = container,
            session = session,
            warehouses = emptyList(),
            selected = null,
            onSelectWarehouse = warehousesViewModel::select,
            onSignOut = warehousesViewModel::signOut,
            onSignInAgain = warehousesViewModel::signInAgain,
        )
        is WarehousesUiState.Ready -> WarehouseApp(
            container = container,
            session = session,
            warehouses = current.warehouses,
            selected = current.selected,
            onSelectWarehouse = warehousesViewModel::select,
            onSignOut = warehousesViewModel::signOut,
            onSignInAgain = warehousesViewModel::signInAgain,
        )
    }
}

@Composable
private fun WarehouseApp(
    container: AppContainer,
    session: StaffSession,
    warehouses: List<WarehouseAccess>,
    selected: WarehouseAccess?,
    onSelectWarehouse: (Long) -> Unit,
    onSignOut: () -> Unit,
    onSignInAgain: () -> Unit,
) {
    val clockingViewModel: ClockingViewModel = viewModel(
        factory = viewModelFactory {
            initializer { ClockingViewModel(container.clockingApi, container.sessions) }
        },
    )
    val leaveViewModel: LeaveViewModel = viewModel(
        factory = viewModelFactory {
            initializer { LeaveViewModel(container.hrApi, container.sessions) }
        },
    )
    val adjustmentsViewModel: ClockingAdjustmentsViewModel = viewModel(
        factory = viewModelFactory {
            initializer { ClockingAdjustmentsViewModel(container.hrApi, container.sessions) }
        },
    )
    val overtimeViewModel: OvertimeViewModel = viewModel(
        factory = viewModelFactory {
            initializer { OvertimeViewModel(container.hrApi, container.sessions) }
        },
    )
    val calendarViewModel: ClockingCalendarViewModel = viewModel(
        factory = viewModelFactory {
            initializer { ClockingCalendarViewModel(container.hrApi, container.sessions) }
        },
    )
    val isClockingSessionExpired by clockingViewModel.isSessionExpired.collectAsStateWithLifecycle()
    val isLeaveSessionExpired by leaveViewModel.isSessionExpired.collectAsStateWithLifecycle()
    val isAdjustmentsSessionExpired by adjustmentsViewModel.isSessionExpired.collectAsStateWithLifecycle()
    val isOvertimeSessionExpired by overtimeViewModel.isSessionExpired.collectAsStateWithLifecycle()
    val isCalendarSessionExpired by calendarViewModel.isSessionExpired.collectAsStateWithLifecycle()
    if (isClockingSessionExpired || isLeaveSessionExpired || isAdjustmentsSessionExpired || isOvertimeSessionExpired || isCalendarSessionExpired) {
        SessionExpiredScreen(onSignIn = onSignInAgain)
        return
    }
    val navController = rememberNavController()
    val toasts = remember { ToastState() }
    var isSwitcherOpen by remember { mutableStateOf(false) }
    var isReportOpen by remember { mutableStateOf(false) }
    val navigator = remember(navController) {
        NavControllerNavigator(
            navController = navController,
            onShowSwitcher = { isSwitcherOpen = true },
            onShowReport = { isReportOpen = true },
            onSignOut = onSignOut,
        )
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
        LocalWarehouse provides (selected ?: NoWarehouseAccess),
        LocalWarehouses provides warehouses,
        LocalStaff provides session,
        LocalAppearanceController provides appearanceController,
        LocalToasts provides toasts,
        LocalClocking provides clockingViewModel,
        LocalLeave provides leaveViewModel,
        LocalClockingAdjustments provides adjustmentsViewModel,
        LocalOvertime provides overtimeViewModel,
        LocalClockingCalendar provides calendarViewModel,
        LocalSectionTabsHandoff provides remember { SectionTabsHandoff() },
    ) {
        Box(modifier = Modifier.fillMaxSize()) {
            AppNavHost(navController)
            ToastHost(
                state = toasts,
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .navigationBarsPadding()
                    .padding(start = 16.dp, end = 16.dp, bottom = 96.dp),
            )
        }
        if (isSwitcherOpen && selected != null) {
            WarehouseSwitcherSheet(
                warehouses = warehouses,
                selected = selected,
                onSwitch = { warehouseId ->
                    isSwitcherOpen = false
                    onSelectWarehouse(warehouseId)
                },
                onDismiss = { isSwitcherOpen = false },
            )
        }
        if (isReportOpen) {
            ReportProblemSheet(onDismiss = { isReportOpen = false })
        }
    }
}

@Composable
private fun AppNavHost(navController: NavHostController) {
    NavHost(
        navController = navController,
        startDestination = HomeRoute,
        enterTransition = { EnterTransition.None },
        exitTransition = { ExitTransition.None },
        popEnterTransition = { EnterTransition.None },
        popExitTransition = { ExitTransition.None },
    ) {
        composable<HomeRoute> { HomeScreen() }
        composable<MyWorkRoute> { MyWorkScreen() }
        composable<ScanResultRoute> { ScanResultScreen() }
        composable<InboxRoute> { InboxScreen() }
        composable<MenuRoute> { MenuScreen() }
        composable<SearchRoute> { SearchScreen() }
        composable<WarehouseDashboardRoute> { WarehouseDashboardScreen() }
        composable<ProfileRoute> { ProfileScreen() }
        composable<AppearanceRoute> { AppearanceScreen() }
        composable<LanguageRoute> { LanguageScreen() }
        composable<SyncQueueRoute> { SyncQueueScreen() }
        composable<ResolveConflictsRoute> { ResolveConflictsScreen() }
        composable<ConflictRoute> { entry -> entry.toRoute<ConflictRoute>().let { route -> ConflictScreen(route.reference) } }
        composable<OnlineOnlyRoute> { entry -> entry.toRoute<OnlineOnlyRoute>().let { route -> OnlineOnlyScreen(route.reference) } }
        composable<NoTasksRoute> { NoTasksScreen() }
        composable<StatePreviewRoute> { entry -> entry.toRoute<StatePreviewRoute>().let { route -> StatePreviewScreen(route.kind) } }
        composable<NotDesignedRoute> { entry -> entry.toRoute<NotDesignedRoute>().let { route -> NotDesignedScreen(route.title) } }
        composable<InventoryDashboardRoute> { InventoryDashboardScreen() }
        composable<SkuFamiliesRoute> { SkuFamiliesScreen() }
        composable<SkuFamilyRoute> { entry -> entry.toRoute<SkuFamilyRoute>().let { route -> SkuFamilyScreen(route.code) } }
        composable<SkusRoute> { SkusScreen() }
        composable<SkuAbnormalitiesRoute> { SkuAbnormalitiesScreen() }
        composable<SkuRoute> { entry -> entry.toRoute<SkuRoute>().let { route -> SkuScreen(route.code) } }
        composable<StockHistoryRoute> { StockHistoryScreen() }
        composable<WarehouseOverviewRoute> { WarehouseOverviewScreen() }
        composable<AreasRoute> { AreasScreen() }
        composable<AreaRoute> { entry -> entry.toRoute<AreaRoute>().let { route -> AreaScreen(route.code) } }
        composable<LocationsRoute> { LocationsScreen() }
        composable<LocationRoute> { entry -> entry.toRoute<LocationRoute>().let { route -> LocationScreen(route.code) } }
        composable<LocationSkuRoute> { entry -> entry.toRoute<LocationSkuRoute>().let { route -> LocationSkuScreen(route.locationCode, route.skuCode) } }
        composable<GoodsInBacklogRoute> { GoodsInBacklogScreen() }
        composable<StockDeliveriesRoute> { StockDeliveriesScreen() }
        composable<StockDeliveryArrivingRoute> { entry -> entry.toRoute<StockDeliveryArrivingRoute>().let { route -> StockDeliveryArrivingScreen(route.reference) } }
        composable<StockDeliveryCheckingRoute> { entry -> entry.toRoute<StockDeliveryCheckingRoute>().let { route -> StockDeliveryCheckingScreen(route.reference) } }
        composable<ScanCameraRoute> { ScanCameraScreen() }
        composable<StockDeliveryChecklistRoute> { entry -> entry.toRoute<StockDeliveryChecklistRoute>().let { route -> StockDeliveryChecklistScreen(route.reference) } }
        composable<BookingInRoute> { entry -> entry.toRoute<BookingInRoute>().let { route -> BookingInScreen(route.reference) } }
        composable<PlaceItemRoute> { entry -> entry.toRoute<PlaceItemRoute>().let { route -> PlaceItemScreen(route.reference, route.skuCode) } }
        composable<PlaceItemNoPickingLocationRoute> { entry -> entry.toRoute<PlaceItemNoPickingLocationRoute>().let { route -> PlaceItemNoPickingLocationScreen(route.reference, route.skuCode) } }
        composable<BookedInRoute> { entry -> entry.toRoute<BookedInRoute>().let { route -> BookedInScreen(route.reference) } }
        composable<GoodsInReturnsRoute> { GoodsInReturnsScreen() }
        composable<GoodsInReturnRoute> { entry -> entry.toRoute<GoodsInReturnRoute>().let { route -> GoodsInReturnScreen(route.reference) } }
        composable<GoodsOutBacklogRoute> { GoodsOutBacklogScreen() }
        composable<DeliveryNotesRoute> { DeliveryNotesScreen() }
        composable<DeliveryNoteToDoRoute> { entry -> entry.toRoute<DeliveryNoteToDoRoute>().let { route -> DeliveryNoteToDoScreen(route.reference) } }
        composable<DeliveryNotePickingRoute> { entry -> entry.toRoute<DeliveryNotePickingRoute>().let { route -> DeliveryNotePickingScreen(route.reference) } }
        composable<ScanToPickRoute> { entry -> entry.toRoute<ScanToPickRoute>().let { route -> ScanToPickScreen(route.reference) } }
        composable<FastPickingRoute> { entry -> entry.toRoute<FastPickingRoute>().let { route -> FastPickingScreen(route.reference) } }
        composable<DeliveryNoteAllPickedRoute> { entry -> entry.toRoute<DeliveryNoteAllPickedRoute>().let { route -> DeliveryNoteAllPickedScreen(route.reference) } }
        composable<DeliveryNotePickedRoute> { entry -> entry.toRoute<DeliveryNotePickedRoute>().let { route -> DeliveryNotePickedScreen(route.reference) } }
        composable<DeliveryNotePackingRoute> { entry -> entry.toRoute<DeliveryNotePackingRoute>().let { route -> DeliveryNotePackingScreen(route.reference) } }
        composable<PackingQueueRoute> { PackingQueueScreen() }
        composable<AddShipmentRoute> { entry -> entry.toRoute<AddShipmentRoute>().let { route -> AddShipmentScreen(route.reference) } }
        composable<ManualShipmentRoute> { entry -> entry.toRoute<ManualShipmentRoute>().let { route -> ManualShipmentScreen(route.reference) } }
        composable<ShipmentsRoute> { entry -> entry.toRoute<ShipmentsRoute>().let { route -> ShipmentsScreen(route.reference) } }
        composable<DeliveryNoteCollectionRoute> { entry -> entry.toRoute<DeliveryNoteCollectionRoute>().let { route -> DeliveryNoteCollectionScreen(route.reference) } }
        composable<DeliveryNoteCollectedRoute> { entry -> entry.toRoute<DeliveryNoteCollectedRoute>().let { route -> DeliveryNoteCollectedScreen(route.reference) } }
        composable<DeliveryNoteFinalisedRoute> { entry -> entry.toRoute<DeliveryNoteFinalisedRoute>().let { route -> DeliveryNoteFinalisedScreen(route.reference) } }
        composable<DeliveryNoteDispatchedRoute> { entry -> entry.toRoute<DeliveryNoteDispatchedRoute>().let { route -> DeliveryNoteDispatchedScreen(route.reference) } }
        composable<DeliveryNoteWaitingRoute> { entry -> entry.toRoute<DeliveryNoteWaitingRoute>().let { route -> DeliveryNoteWaitingScreen(route.reference) } }
        composable<DeliveryNoteCancelledRoute> { entry -> entry.toRoute<DeliveryNoteCancelledRoute>().let { route -> DeliveryNoteCancelledScreen(route.reference) } }
        composable<DeliveryNoteIncompleteSetRoute> { entry -> entry.toRoute<DeliveryNoteIncompleteSetRoute>().let { route -> DeliveryNoteIncompleteSetScreen(route.reference) } }
        composable<DeliveryNoteTakeOverRoute> { entry -> entry.toRoute<DeliveryNoteTakeOverRoute>().let { route -> DeliveryNoteTakeOverScreen(route.reference) } }
        composable<AskCrmRoute> { entry -> entry.toRoute<AskCrmRoute>().let { route -> AskCrmScreen(route.reference) } }
        composable<ExternalShipmentRoute> { entry -> entry.toRoute<ExternalShipmentRoute>().let { route -> ExternalShipmentScreen(route.reference) } }
        composable<TariffCodesRoute> { entry -> entry.toRoute<TariffCodesRoute>().let { route -> TariffCodesScreen(route.reference) } }
        composable<CustomsFileRoute> { entry -> entry.toRoute<CustomsFileRoute>().let { route -> CustomsFileScreen(route.reference) } }
        composable<DispatchedEmailsRoute> { entry -> entry.toRoute<DispatchedEmailsRoute>().let { route -> DispatchedEmailsScreen(route.reference) } }
        composable<ShippersRoute> { ShippersScreen() }
        composable<PickingSessionsRoute> { PickingSessionsScreen() }
        composable<PickingSessionRoute> { entry -> entry.toRoute<PickingSessionRoute>().let { route -> PickingSessionScreen(route.reference) } }
        composable<PickByLocationRoute> { entry -> entry.toRoute<PickByLocationRoute>().let { route -> PickByLocationScreen(route.reference) } }
        composable<PackByScanRoute> { entry -> entry.toRoute<PackByScanRoute>().let { route -> PackByScanScreen(route.reference) } }
        composable<TrolleysRoute> { TrolleysScreen() }
        composable<PickedBaysRoute> { PickedBaysScreen() }
        composable<FulfilmentDeliveriesRoute> { FulfilmentDeliveriesScreen() }
        composable<FulfilmentDeliveryRoute> { entry -> entry.toRoute<FulfilmentDeliveryRoute>().let { route -> FulfilmentDeliveryScreen(route.reference) } }
        composable<FulfilmentBookInRoute> { entry -> entry.toRoute<FulfilmentBookInRoute>().let { route -> FulfilmentBookInScreen(route.reference) } }
        composable<FulfilmentBookedInRoute> { entry -> entry.toRoute<FulfilmentBookedInRoute>().let { route -> FulfilmentBookedInScreen(route.reference) } }
        composable<StoredPalletsRoute> { StoredPalletsScreen() }
        composable<PalletRoute> { entry -> entry.toRoute<PalletRoute>().let { route -> PalletScreen(route.reference) } }
        composable<PalletIncidentsRoute> { PalletIncidentsScreen() }
        composable<StoredItemsRoute> { StoredItemsScreen() }
        composable<StoredItemAuditRoute> { entry -> entry.toRoute<StoredItemAuditRoute>().let { route -> StoredItemAuditScreen(route.reference) } }
        composable<FulfilmentReturnsRoute> { FulfilmentReturnsScreen() }
        composable<ReturnPickPalletsRoute> { entry -> entry.toRoute<ReturnPickPalletsRoute>().let { route -> ReturnPickPalletsScreen(route.reference) } }
        composable<ReturnPickItemsRoute> { entry -> entry.toRoute<ReturnPickItemsRoute>().let { route -> ReturnPickItemsScreen(route.reference) } }
        composable<ReturnPickedRoute> { entry -> entry.toRoute<ReturnPickedRoute>().let { route -> ReturnPickedScreen(route.reference) } }
        composable<ReturnShipmentRoute> { entry -> entry.toRoute<ReturnShipmentRoute>().let { route -> ReturnShipmentScreen(route.reference) } }
        composable<ReturnDispatchedRoute> { entry -> entry.toRoute<ReturnDispatchedRoute>().let { route -> ReturnDispatchedScreen(route.reference) } }
        composable<ClockingRoute> { ClockingScreen() }
        composable<ClockScanQrRoute> { ClockScanQrScreen() }
        composable<TimesheetsRoute> { TimesheetsScreen() }
        composable<TimesheetDayRoute> { entry -> entry.toRoute<TimesheetDayRoute>().let { route -> TimesheetDayScreen(route.date) } }
        composable<LeaveRoute> { LeaveScreen() }
        composable<ClockingAdjustmentsRoute> { ClockingAdjustmentsScreen() }
        composable<OvertimeRoute> { OvertimeScreen() }
        composable<ClockingCalendarRoute> { ClockingCalendarScreen() }
        composable<ClockingErrorRoute> { entry -> entry.toRoute<ClockingErrorRoute>().let { route -> ClockingErrorScreen(route.kind, route.message, route.scanned) } }
    }
}

private class NavControllerNavigator(
    private val navController: NavHostController,
    private val onShowSwitcher: () -> Unit,
    private val onShowReport: () -> Unit,
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

    override fun openTab(tab: MainTab) {
        val route: Any = when (tab) {
            MainTab.Home -> HomeRoute
            MainTab.MyWork -> MyWorkRoute
            MainTab.Scan -> ScanResultRoute
            MainTab.Inbox -> InboxRoute
            MainTab.Menu -> MenuRoute
        }
        if (route == HomeRoute) {
            navController.popBackStack(HomeRoute, inclusive = false)
            return
        }
        navController.navigate(route) {
            popUpTo(HomeRoute) { inclusive = false }
            launchSingleTop = true
        }
    }

    override fun back() {
        navController.popBackStack()
    }

    override fun showWarehouseSwitcher() = onShowSwitcher()

    override fun showReportProblem() = onShowReport()

    override fun signOut() = onSignOut()
}
