package io.aiku.droid.ui.goodsin

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.GoodsInHubKey
import io.aiku.droid.data.api.GoodsInHubRow
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.PullRefresh
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.inventory.countText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

private data class FigureLook(val container: Color, val content: Color)

@Composable
fun GoodsInBacklogScreen(backlog: GoodsInBacklogViewModel) {
    val state by backlog.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { backlog.onStart() }

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = backlog::signInAgain)
        } else {
            GoodsInBacklogContent(state = state, onRetry = backlog::retry, onRefresh = backlog::refresh)
        }
    }
}

@Composable
private fun GoodsInBacklogContent(state: GoodsInBacklogUiState, onRetry: () -> Unit, onRefresh: () -> Unit) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can
    val rows = state.rows?.filter { it.isVisible(can) }

    AppScreen(
        header = { ModuleHeader(section = WarehouseSection.GoodsInBacklog, title = stringResource(R.string.goodsin_hub_title)) },
        activeTab = MainTab.Menu,
        showBar = true,
        refresh = PullRefresh(isRefreshing = state.isRefreshing, onRefresh = onRefresh),
    ) {
        state.problem?.let { GoodsOutLoadProblem(problem = it, title = stringResource(R.string.goodsin_hub_failed), onRetry = onRetry) }
        when {
            rows == null -> if (state.problem == null) SkeletonListCard(rows = 3)
            rows.isEmpty() -> HelperNote(text = stringResource(R.string.goodsin_hub_empty))
            else -> rows.forEach { row ->
                SourceCard(row = row, onOpen = { figure -> row.route(figure)?.let(navigator::switchSection) })
            }
        }
        HelperNote(text = stringResource(R.string.goodsin_hub_note))
    }
}

@Composable
private fun figureLabel(row: GoodsInHubRow, figure: HubFigure): String = stringResource(
    when (figure) {
        HubFigure.Arriving -> R.string.goodsin_state_arriving
        HubFigure.Received -> R.string.sd_state_received
        HubFigure.Checked -> R.string.sd_state_checked
        HubFigure.BookingIn -> if (row.key == GoodsInHubKey.RETURNS) R.string.goodsin_return_step_returning else R.string.sd_state_booking_in
        HubFigure.BookedInWeek -> R.string.goodsin_booked_in_7d
        HubFigure.PlacedWeek -> R.string.goodsin_placed_7d
    },
)

@Composable
private fun figureLook(figure: HubFigure): FigureLook {
    val colors = LocalAikuColors.current
    return when (figure) {
        HubFigure.Arriving -> FigureLook(container = colors.background, content = colors.textSecondary)
        HubFigure.Received, HubFigure.Checked, HubFigure.BookingIn -> FigureLook(container = colors.soft, content = colors.link)
        HubFigure.BookedInWeek, HubFigure.PlacedWeek -> FigureLook(container = colors.successSoft, content = colors.success)
    }
}

@Composable
private fun SourceCard(row: GoodsInHubRow, onOpen: (HubFigure?) -> Unit) {
    val colors = LocalAikuColors.current
    val counts = row.counts()
    val title = row.label.ifBlank { stringResource(R.string.module_goods_in) }

    Column(
        verticalArrangement = Arrangement.spacedBy(4.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(width = 1.dp, color = colors.border, shape = CardShape)
            .padding(bottom = 14.dp),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 48.dp)
                .clickable(role = Role.Button, onClick = { onOpen(null) })
                .padding(horizontal = 14.dp, vertical = 10.dp),
        ) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f))
            Icon(painter = painterResource(R.drawable.ic_ui_chevron_right), contentDescription = null, tint = colors.faint, modifier = Modifier.size(20.dp))
        }
        if (counts.isNotEmpty()) {
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth().padding(horizontal = 14.dp)) {
                counts.forEach { count ->
                    val look = figureLook(count.figure)
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(2.dp),
                        modifier = Modifier
                            .weight(1f)
                            .heightIn(min = 44.dp)
                            .clip(ControlShape)
                            .background(look.container)
                            .clickable(role = Role.Button, onClick = { onOpen(count.figure) })
                            .padding(horizontal = 2.dp, vertical = 8.dp),
                    ) {
                        Text(text = countText(count.value), fontSize = 17.sp, fontWeight = FontWeight.Bold, color = look.content)
                        Text(
                            text = figureLabel(row, count.figure),
                            fontSize = 10.sp,
                            lineHeight = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = colors.textTertiary,
                            textAlign = TextAlign.Center,
                        )
                    }
                }
            }
        }
    }
}
