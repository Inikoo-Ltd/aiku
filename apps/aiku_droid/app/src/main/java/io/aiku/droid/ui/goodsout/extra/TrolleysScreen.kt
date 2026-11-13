package io.aiku.droid.ui.goodsout.extra

import androidx.annotation.DrawableRes
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.res.stringResource
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.Trolley
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.goodsout.GoodsOutLoadProblem
import io.aiku.droid.ui.goodsout.deliveryNoteRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PickingSessionRoute
import io.aiku.droid.ui.states.SessionExpiredScreen

private const val UNKNOWN_STATE = ""

@Composable
fun TrolleysScreen(trolleys: EquipmentListViewModel<Trolley>) {
    val state by trolleys.state.collectAsStateWithLifecycle()
    val navigator = LocalAppNavigator.current

    LifecycleEventEffect(Lifecycle.Event.ON_START) { trolleys.onStart() }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = trolleys::signInAgain)
            return@RequireAbility
        }
        AppScreen(
            header = { ModuleHeader(section = WarehouseSection.Trolleys) },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            EquipmentList(
                state = state,
                failedTitle = stringResource(R.string.goodsout_trolley_failed),
                emptyIcon = R.drawable.ic_ui_trolley,
                emptyTitle = stringResource(R.string.goodsout_extra_trolleys_empty_title),
                emptyBody = stringResource(R.string.goodsout_extra_trolleys_hint),
                onRetry = trolleys::retry,
            ) {
                state.items.forEachIndexed { index, trolley ->
                    if (index > 0) {
                        RowDivider()
                    }
                    val note = trolley.currentDeliveryNote
                    val session = trolley.currentPickingSession
                    val isFree = note == null && session == null
                    val tone = if (isFree) Tone.Success else Tone.Primary
                    ExtraRow(
                        title = trolley.name,
                        subtitle = when {
                            note != null -> stringResource(R.string.goodsout_extra_trolley_delivery_note_use, note.reference)
                            session != null -> stringResource(R.string.goodsout_extra_trolley_session_use, session.reference)
                            else -> null
                        },
                        chip = stringResource(if (isFree) R.string.goodsout_extra_free else R.string.goodsout_extra_in_use),
                        chipTone = tone,
                        leading = { ExtraIconTile(icon = R.drawable.ic_ui_trolley, tone = tone) },
                        onClick = when {
                            note != null -> ({ navigator.open(deliveryNoteRoute(id = note.id, reference = note.reference, state = UNKNOWN_STATE)) })
                            session != null -> ({ navigator.open(PickingSessionRoute(id = session.id, reference = session.reference)) })
                            else -> null
                        },
                    )
                }
            }
            HintText(text = stringResource(R.string.goodsout_extra_trolleys_hint))
        }
    }
}

@Composable
internal fun <T> ColumnScope.EquipmentList(
    state: EquipmentListUiState<T>,
    failedTitle: String,
    @DrawableRes emptyIcon: Int,
    emptyTitle: String,
    emptyBody: String,
    onRetry: () -> Unit,
    rows: @Composable ColumnScope.() -> Unit,
) {
    state.problem?.let { GoodsOutLoadProblem(problem = it, title = failedTitle, onRetry = onRetry) }
    when {
        state.items.isNotEmpty() -> ListGroup(content = rows)
        state.hasLoaded -> EmptyState(icon = emptyIcon, title = emptyTitle, body = emptyBody)
        state.problem == null -> SkeletonListCard(rows = 4)
    }
}
