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
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalResources
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.compose.LifecycleEventEffect
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
import io.aiku.droid.data.api.ChatContextKind
import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.DispatchChannel
import io.aiku.droid.data.api.LocationTab
import io.aiku.droid.data.api.OfflineOperationRecord
import io.aiku.droid.data.api.PickedBay
import io.aiku.droid.data.api.ReturnStateFilter
import io.aiku.droid.data.api.SkoBucket
import io.aiku.droid.data.api.StockDeliverySource
import io.aiku.droid.data.api.StockDeliveryStateFilter
import io.aiku.droid.data.api.Trolley
import io.aiku.droid.data.api.WarehouseAccess
import io.aiku.droid.data.offline.SyncReason
import io.aiku.droid.data.session.StaffSession
import io.aiku.droid.push.AskForNotificationsOnce
import io.aiku.droid.push.PushDestination
import io.aiku.droid.push.destination
import io.aiku.droid.ui.asText
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
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ConfirmDialog
import io.aiku.droid.ui.components.FullScreenMessage
import io.aiku.droid.ui.components.LocalImageSource
import io.aiku.droid.ui.components.LocalOfflineIndicator
import io.aiku.droid.ui.components.LocalTabHandoff
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.TabHandoff
import io.aiku.droid.ui.components.ToastHost
import io.aiku.droid.ui.components.ToastKind
import io.aiku.droid.ui.components.ToastState
import io.aiku.droid.ui.components.forConfirm
import io.aiku.droid.ui.dashboard.WarehouseDashboardScreen
import io.aiku.droid.ui.dashboard.WarehouseDashboardViewModel
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
import io.aiku.droid.ui.goodsin.GoodsInBacklogScreen
import io.aiku.droid.ui.goodsin.GoodsInBacklogViewModel
import io.aiku.droid.ui.goodsin.GoodsInReturnScreen
import io.aiku.droid.ui.goodsin.GoodsInReturnsScreen
import io.aiku.droid.ui.goodsin.PlaceItemScreen
import io.aiku.droid.ui.goodsin.ReturnViewModel
import io.aiku.droid.ui.goodsin.ReturnsViewModel
import io.aiku.droid.ui.goodsin.ScanCameraScreen
import io.aiku.droid.ui.goodsin.StockDeliveriesScreen
import io.aiku.droid.ui.goodsin.StockDeliveriesViewModel
import io.aiku.droid.ui.goodsin.StockDeliveryFilter
import io.aiku.droid.ui.goodsin.StockDeliveryScreen
import io.aiku.droid.ui.goodsin.StockDeliveryViewModel
import io.aiku.droid.ui.goodsout.AddShipmentScreen
import io.aiku.droid.ui.goodsout.DeliveryNoteDispatchedScreen
import io.aiku.droid.ui.goodsout.DeliveryNoteFilter
import io.aiku.droid.ui.goodsout.DeliveryNoteFinalisedScreen
import io.aiku.droid.ui.goodsout.DeliveryNotePackingScreen
import io.aiku.droid.ui.goodsout.DeliveryNotePickedScreen
import io.aiku.droid.ui.goodsout.DeliveryNotePickingScreen
import io.aiku.droid.ui.goodsout.DeliveryNoteToDoScreen
import io.aiku.droid.ui.goodsout.DeliveryNoteViewModel
import io.aiku.droid.ui.goodsout.DeliveryNotesScreen
import io.aiku.droid.ui.goodsout.DeliveryNotesViewModel
import io.aiku.droid.ui.goodsout.GoodsOutBacklogScreen
import io.aiku.droid.ui.goodsout.GoodsOutBacklogViewModel
import io.aiku.droid.ui.goodsout.ScanToPickScreen
import io.aiku.droid.ui.goodsout.SessionTarget
import io.aiku.droid.ui.goodsout.extra.AskCrmScreen
import io.aiku.droid.ui.goodsout.extra.DeliveryNoteCancelledScreen
import io.aiku.droid.ui.goodsout.extra.EquipmentListViewModel
import io.aiku.droid.ui.goodsout.extra.ExternalShipmentScreen
import io.aiku.droid.ui.goodsout.extra.PackByScanScreen
import io.aiku.droid.ui.goodsout.extra.PickByLocationScreen
import io.aiku.droid.ui.goodsout.extra.PickedBaysScreen
import io.aiku.droid.ui.goodsout.extra.PickingSessionScreen
import io.aiku.droid.ui.goodsout.extra.PickingSessionViewModel
import io.aiku.droid.ui.goodsout.extra.PickingSessionsScreen
import io.aiku.droid.ui.goodsout.extra.PickingSessionsViewModel
import io.aiku.droid.ui.goodsout.extra.StaffChatViewModel
import io.aiku.droid.ui.goodsout.extra.TrolleysScreen
import io.aiku.droid.ui.home.HomeScreen
import io.aiku.droid.ui.inbox.InboxScreen
import io.aiku.droid.ui.inbox.InboxViewModel
import io.aiku.droid.ui.inbox.LocalRecordOpener
import io.aiku.droid.ui.inbox.RecordOpener
import io.aiku.droid.ui.inbox.warehouseToSwitchTo
import io.aiku.droid.ui.inventory.InventoryDashboardScreen
import io.aiku.droid.ui.inventory.InventoryDashboardViewModel
import io.aiku.droid.ui.inventory.InventoryServices
import io.aiku.droid.ui.inventory.LocalInventoryServices
import io.aiku.droid.ui.inventory.MovementSource
import io.aiku.droid.ui.inventory.SkoFamiliesViewModel
import io.aiku.droid.ui.inventory.SkoFamilyViewModel
import io.aiku.droid.ui.inventory.SkoViewModel
import io.aiku.droid.ui.inventory.SkosViewModel
import io.aiku.droid.ui.inventory.SkuFamiliesScreen
import io.aiku.droid.ui.inventory.SkuFamilyScreen
import io.aiku.droid.ui.inventory.SkuScreen
import io.aiku.droid.ui.inventory.SkusScreen
import io.aiku.droid.ui.inventory.StockHistoryScreen
import io.aiku.droid.ui.inventory.StockMovementsViewModel
import io.aiku.droid.ui.locations.AreaScreen
import io.aiku.droid.ui.locations.AreasScreen
import io.aiku.droid.ui.locations.AreasViewModel
import io.aiku.droid.ui.locations.LocationScreen
import io.aiku.droid.ui.locations.LocationSkuScreen
import io.aiku.droid.ui.locations.LocationViewModel
import io.aiku.droid.ui.locations.LocationsScreen
import io.aiku.droid.ui.locations.LocationsViewModel
import io.aiku.droid.ui.locations.WarehouseOverviewScreen
import io.aiku.droid.ui.locations.WarehouseOverviewViewModel
import io.aiku.droid.ui.menu.MenuScreen
import io.aiku.droid.ui.mywork.LocalWorkBadges
import io.aiku.droid.ui.mywork.MyWorkScreen
import io.aiku.droid.ui.mywork.MyWorkViewModel
import io.aiku.droid.ui.mywork.WorkBadgesViewModel
import io.aiku.droid.ui.navigation.AddShipmentRoute
import io.aiku.droid.ui.navigation.AppNavigator
import io.aiku.droid.ui.navigation.AppearanceController
import io.aiku.droid.ui.navigation.AppearanceRoute
import io.aiku.droid.ui.navigation.AreaRoute
import io.aiku.droid.ui.navigation.AreasRoute
import io.aiku.droid.ui.navigation.AskCrmRoute
import io.aiku.droid.ui.navigation.ChangePasswordRoute
import io.aiku.droid.ui.navigation.ClockScanQrRoute
import io.aiku.droid.ui.navigation.ClockingAdjustmentsRoute
import io.aiku.droid.ui.navigation.ClockingCalendarRoute
import io.aiku.droid.ui.navigation.ClockingErrorRoute
import io.aiku.droid.ui.navigation.ClockingRoute
import io.aiku.droid.ui.navigation.ConflictRecordRoute
import io.aiku.droid.ui.navigation.ConflictRoute
import io.aiku.droid.ui.navigation.DeliveryNoteCancelledRoute
import io.aiku.droid.ui.navigation.DeliveryNoteDispatchedRoute
import io.aiku.droid.ui.navigation.DeliveryNoteFinalisedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePackingRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickedRoute
import io.aiku.droid.ui.navigation.DeliveryNotePickingRoute
import io.aiku.droid.ui.navigation.DeliveryNoteToDoRoute
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.ExternalShipmentRoute
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
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalAppearanceController
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.LocalStartScreen
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocalWarehouses
import io.aiku.droid.ui.navigation.LocationRoute
import io.aiku.droid.ui.navigation.LocationSkuRoute
import io.aiku.droid.ui.navigation.LocationsRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.MenuRoute
import io.aiku.droid.ui.navigation.MyWorkRoute
import io.aiku.droid.ui.navigation.NoWarehouseAccess
import io.aiku.droid.ui.navigation.OnlineOnlyRoute
import io.aiku.droid.ui.navigation.OvertimeRoute
import io.aiku.droid.ui.navigation.PackByScanRoute
import io.aiku.droid.ui.navigation.PalletIncidentsRoute
import io.aiku.droid.ui.navigation.PalletRoute
import io.aiku.droid.ui.navigation.PickByLocationRoute
import io.aiku.droid.ui.navigation.PickedBaysRoute
import io.aiku.droid.ui.navigation.PickingSessionRoute
import io.aiku.droid.ui.navigation.PickingSessionsRoute
import io.aiku.droid.ui.navigation.PlaceItemRoute
import io.aiku.droid.ui.navigation.ProfileRoute
import io.aiku.droid.ui.navigation.ResolveConflictsRoute
import io.aiku.droid.ui.navigation.ResumableScreens
import io.aiku.droid.ui.navigation.ResumeTarget
import io.aiku.droid.ui.navigation.ReturnDispatchedRoute
import io.aiku.droid.ui.navigation.ReturnPickItemsRoute
import io.aiku.droid.ui.navigation.ReturnPickPalletsRoute
import io.aiku.droid.ui.navigation.ReturnPickedRoute
import io.aiku.droid.ui.navigation.ReturnShipmentRoute
import io.aiku.droid.ui.navigation.ScanResultRoute
import io.aiku.droid.ui.navigation.ScanRoute
import io.aiku.droid.ui.navigation.ScanToPickRoute
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.navigation.SkuFamiliesRoute
import io.aiku.droid.ui.navigation.SkuFamilyRoute
import io.aiku.droid.ui.navigation.SkuRoute
import io.aiku.droid.ui.navigation.SkusRoute
import io.aiku.droid.ui.navigation.StartScreenController
import io.aiku.droid.ui.navigation.StatePreviewRoute
import io.aiku.droid.ui.navigation.StockDeliveriesRoute
import io.aiku.droid.ui.navigation.StockDeliveryRoute
import io.aiku.droid.ui.navigation.StockDeliveryScanRoute
import io.aiku.droid.ui.navigation.StockHistoryRoute
import io.aiku.droid.ui.navigation.StoredItemAuditRoute
import io.aiku.droid.ui.navigation.StoredItemsRoute
import io.aiku.droid.ui.navigation.StoredPalletsRoute
import io.aiku.droid.ui.navigation.SyncQueueRoute
import io.aiku.droid.ui.navigation.TimesheetDayRoute
import io.aiku.droid.ui.navigation.TimesheetsRoute
import io.aiku.droid.ui.navigation.TrolleysRoute
import io.aiku.droid.ui.navigation.WarehouseDashboardRoute
import io.aiku.droid.ui.navigation.WarehouseOverviewRoute
import io.aiku.droid.ui.profile.AppearanceScreen
import io.aiku.droid.ui.profile.ChangePasswordScreen
import io.aiku.droid.ui.profile.ChangePasswordViewModel
import io.aiku.droid.ui.profile.LanguageScreen
import io.aiku.droid.ui.profile.ProfileDetailsViewModel
import io.aiku.droid.ui.profile.ProfileScreen
import io.aiku.droid.ui.scan.LocalScanController
import io.aiku.droid.ui.scan.ScanLookupViewModel
import io.aiku.droid.ui.scan.ScanResultScreen
import io.aiku.droid.ui.scan.ScanScreen
import io.aiku.droid.ui.search.SearchScreen
import io.aiku.droid.ui.search.SearchViewModel
import io.aiku.droid.ui.states.LoadingScreen
import io.aiku.droid.ui.states.LocalSupportServices
import io.aiku.droid.ui.states.OfflineScreen
import io.aiku.droid.ui.states.ReportProblemSheet
import io.aiku.droid.ui.states.ServerErrorScreen
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.states.StatePreviewScreen
import io.aiku.droid.ui.states.SupportServices
import io.aiku.droid.ui.sync.ConflictRecordScreen
import io.aiku.droid.ui.sync.ConflictRecordViewModel
import io.aiku.droid.ui.sync.ConflictScreen
import io.aiku.droid.ui.sync.ConflictViewModel
import io.aiku.droid.ui.sync.OnlineOnlyGate
import io.aiku.droid.ui.sync.OnlineOnlyScreen
import io.aiku.droid.ui.sync.ResolveConflictsScreen
import io.aiku.droid.ui.sync.ResolveConflictsViewModel
import io.aiku.droid.ui.sync.SyncQueueScreen
import io.aiku.droid.ui.sync.SyncQueueViewModel
import io.aiku.droid.ui.theme.Appearance
import java.time.LocalDate
import java.time.ZoneId

fun warehousesViewModelFactory(container: AppContainer): ViewModelProvider.Factory = viewModelFactory {
    initializer {
        WarehousesViewModel(container.warehouseApi, container.authApi, container.sessions, container.settings, container.warehouseCache, container.pushRegistration, container.recentSearches)
    }
}

@Composable
fun SignedInApp(container: AppContainer, session: StaffSession) {
    val warehousesViewModel: WarehousesViewModel = viewModel(factory = warehousesViewModelFactory(container))
    val state by warehousesViewModel.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { warehousesViewModel.refresh() }

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
                AikuButton(text = stringResource(R.string.warehouses_retry), onClick = warehousesViewModel::load, icon = R.drawable.ic_states_retry, modifier = Modifier.fillMaxWidth())
                AikuButton(text = stringResource(R.string.sign_out), onClick = warehousesViewModel::signOut, style = ButtonStyle.Danger, icon = R.drawable.ic_ui_log_out, modifier = Modifier.fillMaxWidth())
            }
        }
        WarehousesUiState.NoWarehouses, is WarehousesUiState.Ready -> {
            val ready = current as? WarehousesUiState.Ready
            WarehouseApp(
                container = container,
                session = session,
                warehouses = ready?.warehouses.orEmpty(),
                selected = ready?.selected,
                onSelectWarehouse = warehousesViewModel::select,
                onSignOut = warehousesViewModel::signOut,
                onSignInAgain = warehousesViewModel::signInAgain,
            )
        }
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
            initializer {
                ClockingViewModel(container.clockingApi, container.sessions, container.screenCache, onClockedOut = { container.offlineWork.syncNow(SyncReason.CLOCK_OUT) })
            }
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
    val badgesViewModel: WorkBadgesViewModel = viewModel(
        factory = viewModelFactory {
            initializer { WorkBadgesViewModel(container.myWorkApi, container.sessions, container.inboxApi, container.screenCache) }
        },
    )
    LaunchedEffect(selected?.id) { badgesViewModel.showWarehouse(selected?.id) }
    LifecycleEventEffect(Lifecycle.Event.ON_START) { badgesViewModel.refresh() }
    LaunchedEffect(badgesViewModel) { container.pushInbox.arrivals.collect { badgesViewModel.refresh() } }
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
    var signOutWaiting by remember { mutableIntStateOf(0) }
    val navigator = remember(navController) {
        NavControllerNavigator(
            navController = navController,
            onShowSwitcher = { isSwitcherOpen = true },
            onShowReport = { isReportOpen = true },
            onSignOut = {
                val waiting = container.offlineWork.waitingCount.value
                if (!container.offlineWork.isOnline.value && waiting > 0) signOutWaiting = waiting else onSignOut()
            },
        )
    }
    val offlineOpen by container.offlineOpenRequests.isPending.collectAsStateWithLifecycle()
    LaunchedEffect(offlineOpen) {
        if (container.offlineOpenRequests.take()) {
            navigator.open(ResolveConflictsRoute)
        }
    }
    val resources = LocalResources.current
    val currentWarehouses by rememberUpdatedState(warehouses)
    val recordOpener = remember(navigator, selected?.id, resources) {
        RecordOpener { opening ->
            opening.warehouseToSwitchTo(selected?.id, currentWarehouses)?.let { warehouse ->
                onSelectWarehouse(warehouse.id)
                toasts.show(resources.getString(R.string.inbox_switched_warehouse, warehouse.name), ToastKind.Info)
            }
            navigator.open(opening.route)
        }
    }
    val pendingPush by container.pushInbox.pending.collectAsStateWithLifecycle()
    LaunchedEffect(pendingPush) {
        val pushOpen = container.pushInbox.take() ?: return@LaunchedEffect
        when (val destination = pushOpen.destination(warehouses.map { it.id })) {
            is PushDestination.Record -> recordOpener.open(destination.opening)
            PushDestination.Inbox -> navigator.openTab(MainTab.Inbox)
        }
        container.pushInbox.markRead(pushOpen.notificationId, onRead = badgesViewModel::refresh)
    }
    AskForNotificationsOnce(container.notificationPrompt)
    val organisationId = selected?.organisation?.id
    DisposableEffect(organisationId) {
        container.settings.showOrganisation(organisationId)
        onDispose { container.settings.showOrganisation(null) }
    }
    val appearance by container.settings.appearance.collectAsStateWithLifecycle()
    val isThemePerOrganisation by container.settings.isThemePerOrganisation.collectAsStateWithLifecycle()
    val appearanceController = remember(appearance, isThemePerOrganisation) {
        object : AppearanceController {
            override val appearance: Appearance = appearance
            override val isThemePerOrganisation: Boolean = isThemePerOrganisation
            override fun update(appearance: Appearance) = container.settings.updateAppearance(appearance)
            override fun updateThemePerOrganisation(isOn: Boolean) = container.settings.updateThemePerOrganisation(isOn)
        }
    }
    val warehouseId = selected?.id
    var resumeTarget by remember(session.userId, warehouseId) {
        mutableStateOf(warehouseId?.let { container.settings.lastScreen(session.userId, it) }?.let(ResumableScreens::restore))
    }
    LaunchedEffect(navController, session.userId, warehouseId) {
        if (warehouseId == null) {
            return@LaunchedEffect
        }
        navController.currentBackStackEntryFlow.collect { entry ->
            val saved = ResumableScreens.save(entry) ?: return@collect
            container.settings.saveLastScreen(session.userId, warehouseId, saved)
            resumeTarget = ResumableScreens.restore(saved)
        }
    }
    var hasOpenedStartScreen by rememberSaveable { mutableStateOf(false) }
    LaunchedEffect(Unit) {
        if (hasOpenedStartScreen) {
            return@LaunchedEffect
        }
        hasOpenedStartScreen = true
        if (container.settings.isOpeningOnLastScreen.value) {
            resumeTarget?.let { navigator.open(it.route) }
        }
    }
    val isOpeningOnLastScreen by container.settings.isOpeningOnLastScreen.collectAsStateWithLifecycle()
    val startScreenController = remember(isOpeningOnLastScreen, resumeTarget) {
        object : StartScreenController {
            override val isOpeningOnLastScreen: Boolean = isOpeningOnLastScreen
            override val resumeTarget: ResumeTarget? = resumeTarget
            override fun updateOpenOnLastScreen(isOn: Boolean) = container.settings.updateOpenOnLastScreen(isOn)
        }
    }

    CompositionLocalProvider(
        LocalAppNavigator provides navigator,
        LocalWarehouse provides (selected ?: NoWarehouseAccess),
        LocalWarehouses provides warehouses,
        LocalStaff provides session,
        LocalAppearanceController provides appearanceController,
        LocalStartScreen provides startScreenController,
        LocalToasts provides toasts,
        LocalClocking provides clockingViewModel,
        LocalLeave provides leaveViewModel,
        LocalClockingAdjustments provides adjustmentsViewModel,
        LocalOvertime provides overtimeViewModel,
        LocalClockingCalendar provides calendarViewModel,
        LocalWorkBadges provides badgesViewModel,
        LocalRecordOpener provides recordOpener,
        LocalTabHandoff provides remember { TabHandoff() },
        LocalScanController provides container.scans,
        LocalImageSource provides container.images,
        LocalInventoryServices provides remember(container) { InventoryServices(container.inventoryApi, container.sessions) },
        LocalSupportServices provides remember(container) { SupportServices(container.supportApi, container.sessions) },
        LocalOfflineIndicator provides container.offlineWork,
    ) {
        Box(modifier = Modifier.fillMaxSize()) {
            AppNavHost(
                navController = navController,
                container = container,
                warehouseId = selected?.id,
                today = { clockingViewModel.state.value.today },
                zone = { clockingViewModel.state.value.zone },
            )
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
        if (signOutWaiting > 0) {
            ConfirmDialog(
                title = stringResource(R.string.offline_sign_out_title),
                body = pluralStringResource(R.plurals.offline_sign_out_body, signOutWaiting, signOutWaiting),
                confirmLabel = stringResource(R.string.sign_out),
                onConfirm = {
                    signOutWaiting = 0
                    onSignOut()
                },
                onDismiss = { signOutWaiting = 0 },
                confirmLook = ButtonLook(ButtonStyle.Danger, R.drawable.ic_ui_log_out).forConfirm(),
            )
        }
    }
}

@Composable
private fun AppNavHost(navController: NavHostController, container: AppContainer, warehouseId: Long?, today: () -> LocalDate, zone: () -> ZoneId) {
    val currentWarehouseId = warehouseId ?: NoWarehouseAccess.id

    NavHost(
        navController = navController,
        startDestination = HomeRoute,
        enterTransition = { EnterTransition.None },
        exitTransition = { ExitTransition.None },
        popEnterTransition = { EnterTransition.None },
        popExitTransition = { ExitTransition.None },
    ) {
        composable<HomeRoute> {
            val update by container.appVersions.banner.collectAsStateWithLifecycle()
            HomeScreen(update = update, onDismissUpdate = container.appVersions::dismissBanner)
        }
        composable<MyWorkRoute> {
            val work: MyWorkViewModel = viewModel(
                key = "my-work-$currentWarehouseId",
                factory = viewModelFactory { initializer { MyWorkViewModel(container.myWorkApi, container.sessions, warehouseId, container.screenCache) } },
            )
            MyWorkScreen(work = work)
        }
        composable<ScanRoute> {
            val lookup: ScanLookupViewModel = viewModel(
                key = "scan-$currentWarehouseId",
                factory = viewModelFactory { initializer { ScanLookupViewModel(container.goodsOutApi, container.sessions, currentWarehouseId) } },
            )
            ScanScreen(lookup = lookup)
        }
        composable<ScanResultRoute> { entry -> entry.toRoute<ScanResultRoute>().let { route -> ScanResultScreen(route.code) } }
        composable<InboxRoute> {
            val inbox: InboxViewModel = viewModel(
                factory = viewModelFactory { initializer { InboxViewModel(container.inboxApi, container.sessions, container.pushInbox.arrivals, container.screenCache) } },
            )
            InboxScreen(inbox = inbox)
        }
        composable<MenuRoute> { MenuScreen() }
        composable<SearchRoute> {
            val search: SearchViewModel = viewModel(
                key = "search-$currentWarehouseId",
                factory = viewModelFactory { initializer { SearchViewModel(container.searchApi, container.sessions, container.recentSearches, warehouseId) } },
            )
            SearchScreen(search = search)
        }
        composable<WarehouseDashboardRoute> {
            val dashboard: WarehouseDashboardViewModel = viewModel(
                key = "warehouse-dashboard-$currentWarehouseId",
                factory = viewModelFactory { initializer { WarehouseDashboardViewModel(container.warehouseDashboardApi, container.sessions, warehouseId, container.screenCache) } },
            )
            WarehouseDashboardScreen(dashboard = dashboard)
        }
        composable<ProfileRoute> {
            val profileDetailsViewModel: ProfileDetailsViewModel = viewModel(
                factory = viewModelFactory {
                    initializer { ProfileDetailsViewModel(container.accountLanguage, container.sessions) }
                },
            )
            val profileDetails by profileDetailsViewModel.profile.collectAsStateWithLifecycle()
            val language by container.language.current.collectAsStateWithLifecycle()
            val isProfileRefreshing by profileDetailsViewModel.isRefreshing.collectAsStateWithLifecycle()
            val scanning by container.settings.scanPreferences.collectAsStateWithLifecycle()
            ProfileScreen(
                details = profileDetails,
                language = language,
                isRefreshing = isProfileRefreshing,
                onRefresh = { profileDetailsViewModel.refresh(isPull = true) },
                scanning = scanning,
                onScanningChange = container.settings::updateScanPreferences,
            )
        }
        composable<AppearanceRoute> { AppearanceScreen() }
        composable<LanguageRoute> { LanguageScreen(settings = container.language, onChoose = container.accountLanguage::choose) }
        composable<ChangePasswordRoute> {
            val changePasswordViewModel: ChangePasswordViewModel = viewModel(
                factory = viewModelFactory {
                    initializer { ChangePasswordViewModel(container.authApi, container.sessions) }
                },
            )
            val changePasswordState by changePasswordViewModel.state.collectAsStateWithLifecycle()
            ChangePasswordScreen(state = changePasswordState, actions = changePasswordViewModel)
        }
        composable<SyncQueueRoute> {
            SyncQueueScreen(viewModel(factory = viewModelFactory { initializer { SyncQueueViewModel(container.offlineWork, container.offlineOperationsApi, container.sessions) } }))
        }
        composable<ResolveConflictsRoute> {
            ResolveConflictsScreen(viewModel(factory = viewModelFactory { initializer { ResolveConflictsViewModel(container.offlineOperationsApi, container.sessions) } }))
        }
        composable<ConflictRecordRoute> { entry ->
            val route = entry.toRoute<ConflictRecordRoute>()
            val conflicts: ConflictRecordViewModel = viewModel(
                key = "conflict-record-${route.type}-${route.id}",
                factory = viewModelFactory {
                    initializer { ConflictRecordViewModel(container.offlineOperationsApi, container.sessions, container.offlineWork, OfflineOperationRecord(type = route.type, id = route.id)) }
                },
            )
            ConflictRecordScreen(conflicts, route.title)
        }
        composable<ConflictRoute> { entry ->
            val route = entry.toRoute<ConflictRoute>()
            val conflict: ConflictViewModel = viewModel(
                key = "conflict-${route.id}",
                factory = viewModelFactory { initializer { ConflictViewModel(container.offlineOperationsApi, container.sessions, container.offlineWork, route.id) } },
            )
            ConflictScreen(conflict, route.title)
        }
        composable<OnlineOnlyRoute> { entry -> entry.toRoute<OnlineOnlyRoute>().let { route -> OnlineOnlyScreen(route.reference, container.offlineWork) } }
        composable<StatePreviewRoute> { entry -> entry.toRoute<StatePreviewRoute>().let { route -> StatePreviewScreen(route.kind) } }
        composable<InventoryDashboardRoute> {
            val dashboard: InventoryDashboardViewModel = viewModel(
                key = "inventory-dashboard-$currentWarehouseId",
                factory = viewModelFactory { initializer { InventoryDashboardViewModel(container.inventoryApi, container.sessions, currentWarehouseId) } },
            )
            InventoryDashboardScreen(dashboard = dashboard)
        }
        composable<SkuFamiliesRoute> {
            val families: SkoFamiliesViewModel = viewModel(
                key = "sko-families-$currentWarehouseId",
                factory = viewModelFactory { initializer { SkoFamiliesViewModel(container.inventoryApi, container.sessions, currentWarehouseId) } },
            )
            SkuFamiliesScreen(families = families)
        }
        composable<SkuFamilyRoute> { entry ->
            val route = entry.toRoute<SkuFamilyRoute>()
            val family: SkoFamilyViewModel = viewModel(
                key = "sko-family-$currentWarehouseId-${route.id}",
                factory = viewModelFactory { initializer { SkoFamilyViewModel(container.inventoryApi, container.sessions, currentWarehouseId, route.id) } },
            )
            SkuFamilyScreen(family = family, code = route.code)
        }
        composable<SkusRoute> { entry ->
            val route = entry.toRoute<SkusRoute>()
            val bucket = SkoBucket.ofKey(route.bucket) ?: SkoBucket.Current
            val skos: SkosViewModel = viewModel(
                key = "skos-$currentWarehouseId-${bucket.key}",
                factory = viewModelFactory { initializer { SkosViewModel(container.inventoryApi, container.sessions, currentWarehouseId, initialBucket = bucket) } },
            )
            SkusScreen(skos = skos)
        }
        composable<SkuRoute> { entry ->
            val route = entry.toRoute<SkuRoute>()
            val sko: SkoViewModel = viewModel(
                key = "sko-$currentWarehouseId-${route.id}",
                factory = viewModelFactory { initializer {
                        SkoViewModel(
                            container.inventoryApi,
                            container.sessions,
                            currentWarehouseId,
                            route.id,
                            offlineWork = container.offlineWork,
                            reasonsMemory = container.movementReasons,
                        )
                    } },
            )
            SkuScreen(sko = sko, code = route.code)
        }
        composable<StockHistoryRoute> { entry ->
            val route = entry.toRoute<StockHistoryRoute>()
            val locationId = route.locationId
            val source = if (locationId != null) MovementSource.Location(locationId) else MovementSource.Sko(currentWarehouseId, route.orgStockId ?: 0)
            val movements: StockMovementsViewModel = viewModel(
                key = "movements-$source",
                factory = viewModelFactory { initializer { StockMovementsViewModel(container.inventoryApi, container.sessions, source) } },
            )
            StockHistoryScreen(movements = movements, code = route.code, isLocation = locationId != null)
        }
        composable<WarehouseOverviewRoute> {
            val overview: WarehouseOverviewViewModel = viewModel(
                key = "locations-overview-$currentWarehouseId",
                factory = viewModelFactory { initializer { WarehouseOverviewViewModel(container.inventoryApi, container.sessions, currentWarehouseId) } },
            )
            WarehouseOverviewScreen(overview = overview)
        }
        composable<AreasRoute> { AreasScreen(areas = rememberAreas(container, currentWarehouseId)) }
        composable<AreaRoute> { entry ->
            val route = entry.toRoute<AreaRoute>()
            val locations: LocationsViewModel = viewModel(
                key = "area-locations-$currentWarehouseId-${route.id}",
                factory = viewModelFactory { initializer { LocationsViewModel(container.inventoryApi, container.sessions, currentWarehouseId, areaId = route.id) } },
            )
            AreaScreen(areas = rememberAreas(container, currentWarehouseId), locations = locations, areaId = route.id, code = route.code)
        }
        composable<LocationsRoute> { entry ->
            val tab = LocationTab.ofKey(entry.toRoute<LocationsRoute>().tab)
            val locations: LocationsViewModel = viewModel(
                key = "locations-$currentWarehouseId-$tab",
                factory = viewModelFactory { initializer { LocationsViewModel(container.inventoryApi, container.sessions, currentWarehouseId, initialTab = tab) } },
            )
            LocationsScreen(locations = locations)
        }
        composable<LocationRoute> { entry ->
            val route = entry.toRoute<LocationRoute>()
            LocationScreen(location = rememberLocation(container, currentWarehouseId, route.id), code = route.code)
        }
        composable<LocationSkuRoute> { entry ->
            val route = entry.toRoute<LocationSkuRoute>()
            LocationSkuScreen(
                location = rememberLocation(container, currentWarehouseId, route.locationId),
                locationOrgStockId = route.locationOrgStockId,
                locationCode = route.locationCode,
                skoCode = route.skoCode,
            )
        }
        composable<GoodsInBacklogRoute> {
            val backlog: GoodsInBacklogViewModel = viewModel(
                key = "goods-in-hub-$currentWarehouseId",
                factory = viewModelFactory { initializer { GoodsInBacklogViewModel(container.goodsInApi, container.sessions, currentWarehouseId) } },
            )
            GoodsInBacklogScreen(backlog = backlog)
        }
        composable<StockDeliveriesRoute> { entry ->
            val route = entry.toRoute<StockDeliveriesRoute>()
            val filter = StockDeliveryFilter(
                state = StockDeliveryStateFilter.ofKey(route.state) ?: StockDeliveryStateFilter.Active,
                source = StockDeliverySource.ofKey(route.source),
            )
            val deliveries: StockDeliveriesViewModel = viewModel(
                key = "stock-deliveries-$currentWarehouseId-${route.source}-${route.state}",
                factory = viewModelFactory {
                    initializer {
                        StockDeliveriesViewModel(
                            api = container.goodsInApi,
                            sessions = container.sessions,
                            warehouseId = currentWarehouseId,
                            initialFilter = filter,
                            cache = container.screenCache,
                            zone = zone,
                        )
                    }
                },
            )
            StockDeliveriesScreen(deliveries = deliveries)
        }
        composable<StockDeliveryRoute> { entry ->
            entry.toRoute<StockDeliveryRoute>().let { route -> StockDeliveryScreen(rememberStockDelivery(container, currentWarehouseId, route.id), route.reference) }
        }
        composable<StockDeliveryScanRoute> { entry ->
            entry.toRoute<StockDeliveryScanRoute>().let { route -> ScanCameraScreen(rememberStockDelivery(container, currentWarehouseId, route.id), route.reference) }
        }
        composable<PlaceItemRoute> { entry ->
            entry.toRoute<PlaceItemRoute>().let { route -> PlaceItemScreen(rememberStockDelivery(container, currentWarehouseId, route.id), route.itemId, route.skoCode) }
        }
        composable<GoodsInReturnsRoute> { entry ->
            val state = ReturnStateFilter.ofKey(entry.toRoute<GoodsInReturnsRoute>().state) ?: ReturnStateFilter.All
            val returns: ReturnsViewModel = viewModel(
                key = "returns-$currentWarehouseId-${state.key}",
                factory = viewModelFactory {
                    initializer {
                        ReturnsViewModel(
                            api = container.goodsInApi,
                            sessions = container.sessions,
                            warehouseId = currentWarehouseId,
                            initialState = state,
                            cache = container.screenCache,
                            zone = zone,
                        )
                    }
                },
            )
            GoodsInReturnsScreen(returns = returns)
        }
        composable<GoodsInReturnRoute> { entry ->
            val route = entry.toRoute<GoodsInReturnRoute>()
            val customerReturn: ReturnViewModel = viewModel(
                key = "return-$currentWarehouseId-${route.id}",
                factory = viewModelFactory { initializer { ReturnViewModel(container.goodsInApi, container.sessions, currentWarehouseId, route.id, container.offlineWork) } },
            )
            GoodsInReturnScreen(customerReturn = customerReturn, reference = route.reference)
        }
        composable<GoodsOutBacklogRoute> {
            val backlog: GoodsOutBacklogViewModel = viewModel(
                key = "backlog-$currentWarehouseId",
                factory = viewModelFactory { initializer { GoodsOutBacklogViewModel(container.goodsOutApi, container.pickingSessionApi, container.sessions, currentWarehouseId) } },
            )
            GoodsOutBacklogScreen(backlog = backlog)
        }
        composable<DeliveryNotesRoute> { entry ->
            val route = entry.toRoute<DeliveryNotesRoute>()
            val notes: DeliveryNotesViewModel = viewModel(
                key = "delivery-notes-$currentWarehouseId",
                factory = viewModelFactory {
                    initializer {
                        DeliveryNotesViewModel(
                            api = container.goodsOutApi,
                            sessions = container.sessions,
                            warehouseId = currentWarehouseId,
                            today = today,
                            pickingSessions = container.pickingSessionApi,
                            initialFilter = DeliveryNoteFilter(
                                bucket = DeliveryNoteBucket.ofKey(route.bucket) ?: DeliveryNoteBucket.Unassigned,
                                channel = DispatchChannel.ofKey(route.channel) ?: DispatchChannel.All,
                            ),
                            addToSession = route.sessionId?.let { id -> SessionTarget(id = id, reference = route.sessionReference.orEmpty()) },
                            sessionHandoff = container.pickingSessionHandoff,
                            cache = container.screenCache,
                            zone = zone,
                        )
                    }
                },
            )
            DeliveryNotesScreen(notes = notes)
        }
        composable<DeliveryNoteToDoRoute> { entry ->
            entry.toRoute<DeliveryNoteToDoRoute>().let { route -> DeliveryNoteToDoScreen(rememberDeliveryNote(container, route.id), route.reference) }
        }
        composable<DeliveryNotePickingRoute> { entry ->
            entry.toRoute<DeliveryNotePickingRoute>().let { route -> DeliveryNotePickingScreen(rememberDeliveryNote(container, route.id), route.reference) }
        }
        composable<ScanToPickRoute> { entry ->
            entry.toRoute<ScanToPickRoute>().let { route -> ScanToPickScreen(route.reference, route.id?.let { rememberDeliveryNote(container, it) }) }
        }
        composable<DeliveryNotePickedRoute> { entry ->
            entry.toRoute<DeliveryNotePickedRoute>().let { route -> DeliveryNotePickedScreen(rememberDeliveryNote(container, route.id), route.reference) }
        }
        composable<DeliveryNotePackingRoute> { entry ->
            entry.toRoute<DeliveryNotePackingRoute>().let { route -> DeliveryNotePackingScreen(rememberDeliveryNote(container, route.id), route.reference) }
        }
        composable<AddShipmentRoute> { entry ->
            entry.toRoute<AddShipmentRoute>().let { route -> AddShipmentScreen(rememberDeliveryNote(container, route.id), route.reference) }
        }
        composable<DeliveryNoteFinalisedRoute> { entry ->
            entry.toRoute<DeliveryNoteFinalisedRoute>().let { route -> DeliveryNoteFinalisedScreen(rememberDeliveryNote(container, route.id), route.reference) }
        }
        composable<DeliveryNoteDispatchedRoute> { entry ->
            entry.toRoute<DeliveryNoteDispatchedRoute>().let { route -> DeliveryNoteDispatchedScreen(rememberDeliveryNote(container, route.id), route.reference) }
        }
        composable<DeliveryNoteCancelledRoute> { entry ->
            entry.toRoute<DeliveryNoteCancelledRoute>().let { route -> DeliveryNoteCancelledScreen(rememberDeliveryNote(container, route.id), route.reference) }
        }
        composable<AskCrmRoute> { entry ->
            val route = entry.toRoute<AskCrmRoute>()
            val kind = ChatContextKind.ofKey(route.context) ?: ChatContextKind.DeliveryNote
            val chat: StaffChatViewModel = viewModel(
                key = "chat-${route.context}-${route.id}",
                factory = viewModelFactory { initializer { StaffChatViewModel(container.staffChatApi, container.sessions, kind, route.id) } },
            )
            AskCrmScreen(chat = chat, kind = kind, reference = route.reference)
        }
        composable<ExternalShipmentRoute> { entry ->
            entry.toRoute<ExternalShipmentRoute>().let { route -> ExternalShipmentScreen(rememberDeliveryNote(container, route.id), route.reference) }
        }
        composable<PickingSessionsRoute> {
            val sessions: PickingSessionsViewModel = viewModel(
                key = "picking-sessions-$currentWarehouseId",
                factory = viewModelFactory {
                    initializer {
                        PickingSessionsViewModel(api = container.pickingSessionApi, sessions = container.sessions, warehouseId = currentWarehouseId, cache = container.screenCache)
                    }
                },
            )
            OnlineOnlyGate(reference = stringResource(R.string.section_picking_sessions), offlineWork = container.offlineWork) { PickingSessionsScreen(sessions = sessions) }
        }
        composable<PickingSessionRoute> { entry ->
            entry.toRoute<PickingSessionRoute>().let { route ->
                OnlineOnlyGate(reference = route.reference, offlineWork = container.offlineWork) {
                    PickingSessionScreen(rememberPickingSession(container, route.id, currentWarehouseId), route.reference)
                }
            }
        }
        composable<PickByLocationRoute> { entry ->
            entry.toRoute<PickByLocationRoute>().let { route ->
                OnlineOnlyGate(reference = route.reference, offlineWork = container.offlineWork) {
                    PickByLocationScreen(
                        session = rememberPickingSession(container, route.id, currentWarehouseId),
                        reference = route.reference,
                        rememberNote = { id -> rememberDeliveryNote(container, id) },
                    )
                }
            }
        }
        composable<PackByScanRoute> { entry ->
            entry.toRoute<PackByScanRoute>().let { route ->
                OnlineOnlyGate(reference = route.reference, offlineWork = container.offlineWork) {
                    PackByScanScreen(rememberPickingSession(container, route.id, currentWarehouseId), route.reference)
                }
            }
        }
        composable<TrolleysRoute> {
            val trolleys: EquipmentListViewModel<Trolley> = viewModel(
                key = "trolleys-$currentWarehouseId",
                factory = viewModelFactory {
                    initializer { EquipmentListViewModel(container.sessions) { token -> container.goodsOutApi.trolleys(token, currentWarehouseId, availableOnly = false) } }
                },
            )
            TrolleysScreen(trolleys = trolleys)
        }
        composable<PickedBaysRoute> {
            val bays: EquipmentListViewModel<PickedBay> = viewModel(
                key = "picked-bays-$currentWarehouseId",
                factory = viewModelFactory { initializer { EquipmentListViewModel(container.sessions) { token -> container.goodsOutApi.pickedBays(token, currentWarehouseId) } } },
            )
            PickedBaysScreen(bays = bays)
        }
        composable<FulfilmentDeliveriesRoute> { FulfilmentDeliveriesScreen(container = container, warehouseId = currentWarehouseId) }
        composable<FulfilmentDeliveryRoute> { entry -> entry.toRoute<FulfilmentDeliveryRoute>().let { route -> FulfilmentDeliveryScreen(container = container, warehouseId = currentWarehouseId, id = route.id, reference = route.reference) } }
        composable<FulfilmentBookInRoute> { entry -> entry.toRoute<FulfilmentBookInRoute>().let { route -> FulfilmentBookInScreen(container = container, warehouseId = currentWarehouseId, id = route.id, reference = route.reference) } }
        composable<FulfilmentBookedInRoute> { entry -> entry.toRoute<FulfilmentBookedInRoute>().let { route -> FulfilmentBookedInScreen(container = container, warehouseId = currentWarehouseId, id = route.id, reference = route.reference) } }
        composable<StoredPalletsRoute> { StoredPalletsScreen(container = container, warehouseId = currentWarehouseId) }
        composable<PalletRoute> { entry -> entry.toRoute<PalletRoute>().let { route -> PalletScreen(container = container, warehouseId = currentWarehouseId, id = route.id, reference = route.reference) } }
        composable<PalletIncidentsRoute> { PalletIncidentsScreen(container = container, warehouseId = currentWarehouseId) }
        composable<StoredItemsRoute> { StoredItemsScreen(container = container, warehouseId = currentWarehouseId) }
        composable<StoredItemAuditRoute> { entry -> entry.toRoute<StoredItemAuditRoute>().let { route -> StoredItemAuditScreen(container = container, warehouseId = currentWarehouseId, id = route.id, reference = route.reference) } }
        composable<FulfilmentReturnsRoute> { FulfilmentReturnsScreen(container = container, warehouseId = currentWarehouseId) }
        composable<ReturnPickPalletsRoute> { entry -> entry.toRoute<ReturnPickPalletsRoute>().let { route -> ReturnPickPalletsScreen(container = container, warehouseId = currentWarehouseId, id = route.id, reference = route.reference) } }
        composable<ReturnPickItemsRoute> { entry -> entry.toRoute<ReturnPickItemsRoute>().let { route -> ReturnPickItemsScreen(container = container, warehouseId = currentWarehouseId, id = route.id, reference = route.reference) } }
        composable<ReturnPickedRoute> { entry -> entry.toRoute<ReturnPickedRoute>().let { route -> ReturnPickedScreen(container = container, warehouseId = currentWarehouseId, id = route.id, reference = route.reference) } }
        composable<ReturnShipmentRoute> { entry -> entry.toRoute<ReturnShipmentRoute>().let { route -> ReturnShipmentScreen(container = container, warehouseId = currentWarehouseId, id = route.id, reference = route.reference) } }
        composable<ReturnDispatchedRoute> { entry -> entry.toRoute<ReturnDispatchedRoute>().let { route -> ReturnDispatchedScreen(container = container, warehouseId = currentWarehouseId, id = route.id, reference = route.reference) } }
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

@Composable
private fun rememberAreas(container: AppContainer, warehouseId: Long): AreasViewModel = viewModel(
    key = "areas-$warehouseId",
    factory = viewModelFactory { initializer { AreasViewModel(container.inventoryApi, container.sessions, warehouseId) } },
)

@Composable
private fun rememberLocation(container: AppContainer, warehouseId: Long, locationId: Long): LocationViewModel = viewModel(
    key = "location-$warehouseId-$locationId",
    factory = viewModelFactory { initializer {
        LocationViewModel(container.inventoryApi, container.sessions, warehouseId, locationId, container.offlineWork, container.movementReasons)
    } },
)

@Composable
private fun rememberStockDelivery(container: AppContainer, warehouseId: Long, stockDeliveryId: Long): StockDeliveryViewModel = viewModel(
    key = "stock-delivery-$warehouseId-$stockDeliveryId",
    factory = viewModelFactory {
        initializer {
            StockDeliveryViewModel(
                container.goodsInApi,
                container.inventoryApi,
                container.sessions,
                warehouseId,
                stockDeliveryId,
                container.stockDeliveryHandoff,
                container.offlineWork,
            )
        }
    },
)

@Composable
private fun rememberDeliveryNote(container: AppContainer, deliveryNoteId: Long): DeliveryNoteViewModel = viewModel(
    key = "delivery-note-$deliveryNoteId",
    factory = viewModelFactory {
        initializer { DeliveryNoteViewModel(container.goodsOutApi, container.sessions, deliveryNoteId, container.deliveryNoteHandoff, offline = container.offlineWork) }
    },
)

@Composable
private fun rememberPickingSession(container: AppContainer, sessionId: Long, warehouseId: Long): PickingSessionViewModel = viewModel(
    key = "picking-session-$sessionId",
    factory = viewModelFactory {
        initializer {
            PickingSessionViewModel(container.pickingSessionApi, container.goodsOutApi, container.sessions, sessionId, warehouseId, container.pickingSessionHandoff)
        }
    },
)

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
            MainTab.Scan -> ScanRoute
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
