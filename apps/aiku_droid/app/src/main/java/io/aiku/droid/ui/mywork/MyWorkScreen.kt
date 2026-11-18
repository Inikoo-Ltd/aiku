package io.aiku.droid.ui.mywork

import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshDefaults
import androidx.compose.material3.pulltorefresh.pullToRefresh
import androidx.compose.material3.pulltorefresh.rememberPullToRefreshState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.onSizeChanged
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.MyWorkKind
import io.aiku.droid.data.api.MyWorkRow
import io.aiku.droid.data.api.PalletReturnTypeGroup
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.EmptyTone
import io.aiku.droid.ui.components.HeaderIconButton
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalStaff
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.SearchRoute
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors
import java.time.Instant

private const val SUBTITLE_SEPARATOR = " · "
private const val TROLLEY_SEPARATOR = ", "

@Composable
fun MyWorkScreen(work: MyWorkViewModel) {
    val state by work.state.collectAsStateWithLifecycle()
    val badges = LocalWorkBadges.current

    LifecycleEventEffect(Lifecycle.Event.ON_START) {
        work.onStart()
        badges.refresh()
    }

    if (state.isSessionExpired) {
        SessionExpiredScreen(onSignIn = work::signInAgain)
        return
    }

    MyWorkContent(
        state = state,
        onRefresh = {
            work.refresh()
            badges.refresh()
        },
        onRetry = work::retry,
    )
}

@Composable
private fun MyWorkContent(state: MyWorkUiState, onRefresh: () -> Unit, onRetry: () -> Unit) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val staff = LocalStaff.current
    val refreshState = rememberPullToRefreshState()
    var headerHeight by remember { mutableIntStateOf(0) }
    val now = remember(state.rows) { Instant.now() }

    Box(modifier = Modifier.fillMaxSize().pullToRefresh(isRefreshing = state.isRefreshing, state = refreshState, onRefresh = onRefresh)) {
        AppScreen(
            header = {
                AppHeader(
                    title = stringResource(R.string.tab_my_work),
                    kicker = stringResource(R.string.mywork_kicker, staff.displayName.ifBlank { staff.username }),
                    showBack = false,
                    modifier = Modifier.onSizeChanged { headerHeight = it.height },
                    actions = {
                        HeaderIconButton(icon = R.drawable.ic_ui_search, description = stringResource(R.string.search_title), onClick = { navigator.open(SearchRoute) }, filled = true)
                    },
                )
            },
            activeTab = MainTab.MyWork,
            spacing = 10.dp,
        ) {
            state.problem?.let { problem ->
                GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.mywork_failed), onRetry = onRetry)
            }
            when {
                state.rows.isNotEmpty() -> Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    state.rows.forEach { row ->
                        WorkRow(row = row, now = now, onClick = { navigator.open(row.route()) })
                    }
                }
                state.isEmpty -> EmptyState(
                    icon = R.drawable.ic_feedback_done,
                    title = stringResource(R.string.mywork_empty_title),
                    body = stringResource(R.string.mywork_empty_body),
                    tone = EmptyTone.Success,
                )
                state.problem == null -> SkeletonListCard(rows = 4)
            }
            Text(
                text = stringResource(R.string.mywork_note),
                fontSize = 12.sp,
                lineHeight = 18.sp,
                color = colors.textQuaternary,
                modifier = Modifier.padding(start = 4.dp, end = 4.dp, top = 6.dp),
            )
        }
        PullToRefreshDefaults.Indicator(
            state = refreshState,
            isRefreshing = state.isRefreshing,
            containerColor = colors.surface,
            color = colors.primary,
            modifier = Modifier.align(Alignment.TopCenter).offset { IntOffset(0, headerHeight) },
        )
    }
}

@Composable
private fun WorkRow(row: MyWorkRow, now: Instant, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val tone = myWorkStepTone(row.step)
    val bar = when (tone) {
        Tone.Warn -> colors.warn
        Tone.Primary -> colors.primary
        else -> colors.borderStrong
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 72.dp)
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Box(modifier = Modifier.width(6.dp).height(44.dp).clip(RoundedCornerShape(999.dp)).background(bar))
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(text = row.reference, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
                StatusBadge(text = row.stepLabel.ifBlank { row.stateLabel }.ifBlank { row.step }, tone = tone)
            }
            Text(text = workSubtitle(row, now), fontSize = 13.sp, color = colors.textTertiary, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
    }
}

@Composable
private fun workSubtitle(row: MyWorkRow, now: Instant): String = listOfNotNull(
    stringResource(kindLabel(row.kind)),
    row.items?.let { workSize(row, it) },
    row.customer,
    row.trolleys.takeIf { it.isNotEmpty() }?.let { pluralStringResource(R.plurals.mywork_trolleys, it.size, it.joinToString(TROLLEY_SEPARATOR)) },
    elapsedSince(row.since, now)?.let { elapsedText(it) },
).joinToString(SUBTITLE_SEPARATOR)

@StringRes
private fun kindLabel(kind: MyWorkKind): Int = when (kind) {
    MyWorkKind.DeliveryNote -> R.string.mywork_kind_delivery_note
    MyWorkKind.PickingSession -> R.string.mywork_kind_picking_session
    MyWorkKind.CustomerReturn -> R.string.mywork_kind_return
    MyWorkKind.PalletReturn -> R.string.mywork_kind_pallet_return
}

@Composable
private fun workSize(row: MyWorkRow, count: Int): String {
    val plural = when (row.kind) {
        MyWorkKind.DeliveryNote -> R.plurals.mywork_lines
        MyWorkKind.PickingSession -> R.plurals.mywork_delivery_notes
        MyWorkKind.CustomerReturn -> R.plurals.mywork_items
        MyWorkKind.PalletReturn -> if (row.typeGroup == PalletReturnTypeGroup.ITEMS) R.plurals.mywork_stored_items else R.plurals.mywork_pallets
    }
    return pluralStringResource(plural, count, count)
}

@Composable
private fun elapsedText(elapsed: Elapsed): String = when (elapsed.unit) {
    ElapsedUnit.JustNow -> stringResource(R.string.mywork_since_just_now)
    ElapsedUnit.Minutes -> pluralStringResource(R.plurals.mywork_since_minutes, elapsed.amount, elapsed.amount)
    ElapsedUnit.Hours -> pluralStringResource(R.plurals.mywork_since_hours, elapsed.amount, elapsed.amount)
    ElapsedUnit.Days -> pluralStringResource(R.plurals.mywork_since_days, elapsed.amount, elapsed.amount)
}
