package io.aiku.droid.ui.states

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.SkeletonBlock
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.ToastHost
import io.aiku.droid.ui.components.ToastState
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.LocalWarehouses
import io.aiku.droid.ui.navigation.PreviewWarehouse
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun LoadingScreen() {
    val colors = LocalAikuColors.current

    Column(modifier = Modifier.fillMaxSize().background(colors.background)) {
        Column(
            verticalArrangement = Arrangement.spacedBy(18.dp),
            modifier = Modifier
                .fillMaxWidth()
                .background(colors.nav)
                .statusBarsPadding()
                .padding(start = 20.dp, end = 20.dp, top = 16.dp, bottom = 76.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                LoadingNavPlaceholder(width = 44, height = 44, alpha = 0.18f, shape = CircleShape)
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    LoadingNavPlaceholder(width = 90, height = 10, alpha = 0.18f)
                    LoadingNavPlaceholder(width = 140, height = 14, alpha = 0.24f)
                }
            }
            LoadingNavPlaceholder(width = 200, height = 44, alpha = 0.14f, shape = ControlShape)
        }
        Column(
            verticalArrangement = Arrangement.spacedBy(16.dp),
            modifier = Modifier
                .fillMaxWidth()
                .offset(y = (-52).dp)
                .padding(horizontal = 16.dp),
        ) {
            AikuCard {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                    repeat(4) {
                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(8.dp),
                            modifier = Modifier.weight(1f).padding(vertical = 10.dp),
                        ) {
                            SkeletonBlock(modifier = Modifier.size(44.dp), shape = ControlShape)
                            SkeletonBlock(modifier = Modifier.size(width = 54.dp, height = 10.dp))
                        }
                    }
                }
            }
            AikuCard {
                SkeletonBlock(modifier = Modifier.size(width = 120.dp, height = 14.dp))
                repeat(2) {
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
                        repeat(2) { LoadingStatTile(modifier = Modifier.weight(1f)) }
                    }
                }
            }
            SkeletonListCard()
            Text(
                text = stringResource(R.string.states_loading_warehouses),
                fontSize = 13.sp,
                fontWeight = FontWeight.Normal,
                color = colors.textTertiary,
                modifier = Modifier.align(Alignment.CenterHorizontally),
            )
        }
    }
}

@Composable
private fun LoadingNavPlaceholder(width: Int, height: Int, alpha: Float, shape: Shape = RoundedCornerShape(6.dp)) {
    Box(
        modifier = Modifier
            .size(width = width.dp, height = height.dp)
            .clip(shape)
            .background(Color.White.copy(alpha = alpha)),
    )
}

@Composable
private fun LoadingStatTile(modifier: Modifier = Modifier) {
    val shape = RoundedCornerShape(10.dp)

    Column(
        verticalArrangement = Arrangement.spacedBy(8.dp),
        modifier = modifier
            .border(1.dp, LocalAikuColors.current.border, shape)
            .padding(12.dp),
    ) {
        SkeletonBlock(modifier = Modifier.fillMaxWidth(0.7f).height(10.dp))
        SkeletonBlock(modifier = Modifier.fillMaxWidth(0.4f).height(22.dp))
    }
}

@Composable
fun OfflineScreen(onRetry: () -> Unit, onKeepWorking: (() -> Unit)? = null, onViewQueue: (() -> Unit)? = null) {
    val colors = LocalAikuColors.current

    StatesFullScreen(
        actions = {
            StatesButton(text = stringResource(R.string.states_try_again), onClick = onRetry, icon = R.drawable.ic_states_retry)
            if (onKeepWorking != null) {
                StatesButton(
                    text = stringResource(R.string.states_offline_keep_working),
                    onClick = onKeepWorking,
                    style = ButtonStyle.Outline,
                )
            }
        },
    ) {
        StatesHeroIcon(icon = R.drawable.ic_feedback_offline, container = colors.surfaceMuted, tint = colors.textTertiary)
        StatesTitle(text = stringResource(R.string.states_offline_title))
        StatesBody(text = AnnotatedString(stringResource(R.string.states_offline_body)))
        if (onViewQueue != null) {
            SyncQueueCard(onClick = onViewQueue)
        }
    }
}

@Composable
private fun SyncQueueCard(onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fakeData()
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(
                painter = painterResource(R.drawable.ic_states_sync),
                contentDescription = null,
                tint = colors.textSecondary,
                modifier = Modifier.size(18.dp),
            )
            Text(
                text = stringResource(R.string.states_offline_waiting),
                fontSize = 14.sp,
                fontWeight = FontWeight.Normal,
                color = colors.textSecondary,
                modifier = Modifier.weight(1f),
            )
            Text(
                text = pluralStringResource(R.plurals.states_offline_actions, STATES_SYNC_QUEUE_COUNT_SAMPLE, STATES_SYNC_QUEUE_COUNT_SAMPLE),
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = colors.text,
            )
        }
        Text(text = STATES_SYNC_QUEUE_SUMMARY_SAMPLE, fontSize = 12.sp, fontWeight = FontWeight.Normal, color = colors.textTertiary)
        Text(
            text = stringResource(R.string.states_offline_view_queue),
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            color = colors.link,
        )
    }
}

@Composable
fun ServerErrorScreen(onRetry: () -> Unit, statusCode: Int? = null) {
    val colors = LocalAikuColors.current
    val toasts = remember { ToastState() }
    var isReportOpen by remember { mutableStateOf(false) }

    CompositionLocalProvider(LocalToasts provides toasts) {
        Box(modifier = Modifier.fillMaxSize()) {
            StatesFullScreen(
                actions = {
                    StatesButton(text = stringResource(R.string.states_try_again), onClick = onRetry, icon = R.drawable.ic_states_retry)
                    StatesButton(
                        text = stringResource(R.string.states_report_problem),
                        onClick = { isReportOpen = true },
                        style = ButtonStyle.Outline,
                    )
                },
            ) {
                StatesHeroIcon(icon = R.drawable.ic_states_server, container = colors.dangerSoft, tint = colors.danger)
                StatesTitle(text = stringResource(R.string.states_server_title))
                StatesBody(text = AnnotatedString(stringResource(R.string.states_server_body)))
                ServerErrorReference(statusCode = statusCode)
            }
            ToastHost(
                state = toasts,
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .navigationBarsPadding()
                    .padding(start = 16.dp, end = 16.dp, bottom = 150.dp),
            )
        }
        if (isReportOpen) {
            ReportProblemSheet(onDismiss = { isReportOpen = false }, initialCategory = ReportProblemCategory.AppError)
        }
    }
}

@Composable
private fun ServerErrorReference(statusCode: Int?) {
    val colors = LocalAikuColors.current

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(2.dp)) {
        if (statusCode != null) {
            Text(
                text = stringResource(R.string.states_server_error_code, statusCode),
                fontSize = 12.sp,
                fontWeight = FontWeight.Normal,
                color = colors.textQuaternary,
            )
        }
        Text(
            text = STATES_SERVER_REFERENCE_SAMPLE,
            fontSize = 12.sp,
            fontWeight = FontWeight.Normal,
            color = colors.textQuaternary,
            modifier = Modifier
                .fakeData(6.dp)
                .padding(horizontal = 6.dp, vertical = 3.dp),
        )
    }
}

@Composable
fun SessionExpiredScreen(onSignIn: () -> Unit) {
    val colors = LocalAikuColors.current

    StatesFullScreen(
        actions = {
            StatesButton(text = stringResource(R.string.states_session_sign_in), onClick = onSignIn)
        },
    ) {
        StatesHeroIcon(icon = R.drawable.ic_states_lock, container = colors.soft, tint = colors.link)
        StatesTitle(text = stringResource(R.string.states_session_title))
        StatesBody(text = AnnotatedString(stringResource(R.string.states_session_body)))
    }
}

@Composable
fun NoAccessScreen(moduleName: String, missingPermission: String?, activeTab: MainTab?) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val warehouseName = LocalWarehouse.current.name
    val canChangeWarehouse = LocalWarehouses.current.size > 1
    val body = if (warehouseName.isBlank()) {
        stringResource(R.string.states_no_access_body_without_warehouse, moduleName)
    } else {
        stringResource(R.string.states_no_access_body, moduleName, warehouseName)
    }

    StatesFullScreen(
        header = {
            AppHeader(
                title = warehouseName.ifBlank { moduleName },
                kicker = stringResource(R.string.states_no_access_kicker),
            )
        },
        actions = {
            StatesButton(text = stringResource(R.string.states_back_to_dashboard), onClick = { navigator.openTab(MainTab.Home) })
            if (canChangeWarehouse) {
                StatesButton(
                    text = stringResource(R.string.states_change_warehouse),
                    onClick = navigator::showWarehouseSwitcher,
                    style = ButtonStyle.Outline,
                )
            }
        },
    ) {
        StatesHeroIcon(icon = R.drawable.ic_shield_check, container = colors.warnSoft, tint = colors.warn)
        StatesTitle(text = stringResource(R.string.states_no_access_title))
        StatesBody(text = emphasise(body, moduleName, warehouseName))
        if (missingPermission != null) {
            Text(
                text = stringResource(R.string.states_no_access_missing, missingPermission),
                fontSize = 12.sp,
                fontWeight = FontWeight.Normal,
                color = colors.textQuaternary,
                textAlign = TextAlign.Center,
            )
        }
    }
}

@Composable
fun SearchEmptyState(query: String, onScan: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = modifier
            .fillMaxWidth()
            .padding(top = 40.dp),
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(14.dp),
            modifier = Modifier.padding(horizontal = 12.dp),
        ) {
            StatesHeroIcon(
                icon = R.drawable.ic_feedback_search,
                container = colors.surfaceMuted,
                tint = colors.textQuaternary,
                size = 80.dp,
                iconSize = 36.dp,
            )
            StatesTitle(text = stringResource(R.string.states_search_empty_title, query.trim()), fontSize = 20.sp)
            StatesBody(
                text = AnnotatedString(stringResource(R.string.states_search_empty_body)),
                fontSize = 14.sp,
                lineHeight = 21.sp,
            )
            FlowRow(
                horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally),
                verticalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier
                    .fakeData(8.dp)
                    .padding(4.dp),
            ) {
                statesSearchSuggestionsSample.forEach { suggestion -> SearchSuggestionChip(text = suggestion) }
            }
        }
        Spacer(modifier = Modifier.height(32.dp))
        StatesButton(
            text = stringResource(R.string.states_search_scan_instead),
            onClick = onScan,
            style = ButtonStyle.Scan,
            icon = R.drawable.ic_scan,
        )
    }
}

@Composable
private fun SearchSuggestionChip(text: String) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .heightIn(min = 36.dp)
            .clip(CircleShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CircleShape)
            .padding(horizontal = 12.dp),
    ) {
        Text(text = text, fontSize = 13.sp, fontWeight = FontWeight.Normal, color = colors.text)
    }
}

@Composable
fun NoTasksState(modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = modifier
            .fillMaxWidth()
            .padding(top = 40.dp),
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(14.dp),
            modifier = Modifier.padding(horizontal = 12.dp),
        ) {
            StatesHeroIcon(icon = R.drawable.ic_feedback_done, container = colors.successSoft, tint = colors.success)
            StatesTitle(text = stringResource(R.string.states_tasks_empty_title))
            StatesBody(text = AnnotatedString(stringResource(R.string.states_tasks_empty_body)))
        }
        Spacer(modifier = Modifier.height(32.dp))
        Column(verticalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
            if (can.viewGoodsOut && can.pickAndPack) {
                StatesButton(
                    text = stringResource(R.string.states_tasks_pick_from_queue),
                    onClick = { navigator.open(DeliveryNotesRoute) },
                )
            }
            StatesButton(
                text = stringResource(R.string.states_back_to_dashboard),
                onClick = { navigator.openTab(MainTab.Home) },
                style = ButtonStyle.Outline,
            )
        }
    }
}

@Preview(showSystemUi = true)
@Composable
private fun LoadingScreenPreview() {
    AikuDroidTheme {
        LoadingScreen()
    }
}

@Preview(showSystemUi = true)
@Composable
private fun OfflineScreenPreview() {
    AikuDroidTheme {
        OfflineScreen(onRetry = {}, onKeepWorking = {}, onViewQueue = {})
    }
}

@Preview(showSystemUi = true)
@Composable
private fun ServerErrorScreenPreview() {
    AikuDroidTheme {
        ServerErrorScreen(onRetry = {}, statusCode = 500)
    }
}

@Preview(showSystemUi = true)
@Composable
private fun SessionExpiredScreenPreview() {
    AikuDroidTheme {
        SessionExpiredScreen(onSignIn = {})
    }
}

@Preview(showSystemUi = true)
@Composable
private fun NoAccessScreenPreview() {
    AikuDroidTheme {
        CompositionLocalProvider(
            LocalWarehouses provides listOf(PreviewWarehouse, PreviewWarehouse.copy(id = 2, slug = "wh2", code = "WH2", name = "Warehouse 2")),
        ) {
            NoAccessScreen(moduleName = "Goods in", missingPermission = "incoming.view", activeTab = MainTab.Menu)
        }
    }
}

@Preview(showBackground = true)
@Composable
private fun SearchEmptyStatePreview() {
    AikuDroidTheme {
        Box(modifier = Modifier.background(LocalAikuColors.current.background).padding(16.dp)) {
            SearchEmptyState(query = STATES_SEARCH_QUERY_SAMPLE, onScan = {})
        }
    }
}

@Preview(showBackground = true)
@Composable
private fun NoTasksStatePreview() {
    AikuDroidTheme {
        Box(modifier = Modifier.background(LocalAikuColors.current.background).padding(16.dp)) {
            NoTasksState()
        }
    }
}
