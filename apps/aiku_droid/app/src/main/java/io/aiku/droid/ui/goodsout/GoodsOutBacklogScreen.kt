package io.aiku.droid.ui.goodsout

import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.ChannelCounts
import io.aiku.droid.data.api.DeliveryNoteBucket
import io.aiku.droid.data.api.DispatchPerson
import io.aiku.droid.data.api.DispatchRole
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AikuCard
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.ModuleHeader
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SegmentedControl
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.components.WarehouseSection
import io.aiku.droid.ui.goodsout.extra.KICKER_SEPARATOR
import io.aiku.droid.ui.goodsout.extra.sessionPersonName
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.PickingSessionRoute
import io.aiku.droid.ui.navigation.PickingSessionsRoute
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

private const val TILES_PER_ROW = 4
private const val SEGMENT_DELIVERY_NOTES = 0
private const val SEGMENT_SESSIONS = 1
private const val SEGMENT_PICKERS = 2
private const val SEGMENT_PACKERS = 3

private data class BacklogTile(val label: String, val count: Int, val bucket: DeliveryNoteBucket, val background: Color, val content: Color)

@Composable
fun GoodsOutBacklogScreen(backlog: GoodsOutBacklogViewModel) {
    val navigator = LocalAppNavigator.current
    val state by backlog.state.collectAsStateWithLifecycle()
    var segment by rememberSaveable { mutableIntStateOf(SEGMENT_DELIVERY_NOTES) }
    var openPerson by remember { mutableStateOf<DispatchPerson?>(null) }
    val segments = listOf(
        stringResource(R.string.goodsout_backlog_delivery_notes),
        stringResource(R.string.goodsout_backlog_sessions),
        stringResource(R.string.goodsout_backlog_pickers),
        stringResource(R.string.goodsout_backlog_packers),
    )

    LifecycleEventEffect(Lifecycle.Event.ON_START) { backlog.onStart() }

    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        if (state.isSessionExpired) {
            SessionExpiredScreen(onSignIn = backlog::signInAgain)
            return@RequireAbility
        }
        AppScreen(
            header = {
                ModuleHeader(
                    section = WarehouseSection.GoodsOutBacklog,
                    title = stringResource(R.string.goodsout_backlog_title),
                )
            },
            activeTab = MainTab.Menu,
            showBar = true,
        ) {
            SegmentedControl(
                options = segments,
                selectedIndex = segment,
                onSelect = { index ->
                    when (index) {
                        SEGMENT_SESSIONS -> navigator.switchSection(PickingSessionsRoute)
                        SEGMENT_PICKERS -> backlog.loadPersonnel(DispatchRole.Pickers)
                        SEGMENT_PACKERS -> backlog.loadPersonnel(DispatchRole.Packers)
                    }
                    if (index != SEGMENT_SESSIONS) {
                        segment = index
                    }
                },
            )
            when (segment) {
                SEGMENT_PICKERS -> PersonnelList(role = DispatchRole.Pickers, people = state.pickers, onRetry = { backlog.loadPersonnel(DispatchRole.Pickers) }, onOpen = { openPerson = it })
                SEGMENT_PACKERS -> PersonnelList(role = DispatchRole.Packers, people = state.packers, onRetry = { backlog.loadPersonnel(DispatchRole.Packers) }, onOpen = { openPerson = it })
                else -> {
                    BacklogChannels(state = state, onRetry = backlog::retry, onOpen = { bucket, channel -> navigator.switchSection(DeliveryNotesRoute(bucket = bucket.key, channel = channel)) })
                    GoodsOutHeading(title = stringResource(R.string.goodsout_backlog_pickers_now))
                    PersonnelList(role = DispatchRole.Pickers, people = state.pickers, onRetry = { backlog.loadPersonnel(DispatchRole.Pickers) }, onOpen = { openPerson = it })
                }
            }
            GoodsOutFootNote(text = stringResource(R.string.goodsout_backlog_note))
        }
        openPerson?.let { person -> PersonSheet(person = person, role = if (segment == SEGMENT_PACKERS) DispatchRole.Packers else DispatchRole.Pickers, onDismiss = { openPerson = null }) }
    }
}

@Composable
private fun BacklogChannels(state: GoodsOutBacklogUiState, onRetry: () -> Unit, onOpen: (DeliveryNoteBucket, String) -> Unit) {
    val counts = state.counts
    state.problem?.let { problem ->
        GoodsOutLoadProblem(problem = problem, title = stringResource(R.string.goodsout_backlog_failed_title), onRetry = onRetry)
    }
    when {
        counts == null && state.problem == null -> SkeletonListCard(rows = 3)
        counts != null && counts.channels.isEmpty() -> EmptyState(
            icon = R.drawable.ic_ui_delivery_note,
            title = stringResource(R.string.goodsout_backlog_empty_title),
            body = stringResource(R.string.goodsout_backlog_empty_body),
        )
        counts != null -> counts.channels.forEach { channel ->
            BacklogChannelCard(channel = channel, onOpen = { bucket -> onOpen(bucket, channel.channel) })
        }
    }
}

@Composable
private fun PersonnelList(role: DispatchRole, people: Choices<DispatchPerson>, onRetry: () -> Unit, onOpen: (DispatchPerson) -> Unit) {
    when {
        people.problem != null -> GoodsOutLoadProblem(problem = people.problem, title = stringResource(R.string.goodsout_backlog_people_failed), onRetry = onRetry)
        people.isLoading && people.items.isEmpty() -> SkeletonListCard(rows = 2)
        people.items.isEmpty() -> Text(
            text = stringResource(if (role == DispatchRole.Packers) R.string.goodsout_backlog_nobody_packing else R.string.goodsout_backlog_nobody_picking),
            fontSize = 14.sp,
            color = LocalAikuColors.current.textTertiary,
            modifier = Modifier.padding(horizontal = 4.dp),
        )
        else -> ListGroup {
            people.items.forEachIndexed { index, person ->
                if (index > 0) {
                    RowDivider()
                }
                IconInfoRow(
                    icon = R.drawable.ic_ui_person,
                    title = sessionPersonName(person.user) ?: person.user.name,
                    subtitle = personSummary(person),
                    tone = Tone.Primary,
                    onClick = { onOpen(person) },
                )
            }
        }
    }
}

@Composable
private fun personSummary(person: DispatchPerson): String = listOfNotNull(
    pluralStringResource(R.plurals.goodsout_extra_delivery_notes, person.deliveryNotes.size, person.deliveryNotes.size),
    person.trolleys.size.takeIf { it > 0 }?.let { pluralStringResource(R.plurals.goodsout_extra_trolleys, it, it) },
    person.pickingSessions.takeIf { it.isNotEmpty() }?.let { sessions -> stringResource(R.string.goodsout_extra_trolley_session_use, sessions.joinToString(", ") { it.reference }) },
).joinToString(KICKER_SEPARATOR)

@Composable
private fun PersonSheet(person: DispatchPerson, role: DispatchRole, onDismiss: () -> Unit) {
    val navigator = LocalAppNavigator.current
    val open: (Any) -> Unit = { route ->
        onDismiss()
        navigator.open(route)
    }
    val rows = buildList<@Composable () -> Unit> {
        person.pickingSessions.forEach { session ->
            add {
                IconInfoRow(
                    icon = R.drawable.ic_ui_trolley,
                    title = session.reference,
                    subtitle = stringResource(R.string.goodsout_extra_picking_session),
                    onClick = { open(PickingSessionRoute(id = session.id, reference = session.reference)) },
                )
            }
        }
        person.deliveryNotes.forEach { note ->
            add {
                IconInfoRow(
                    icon = R.drawable.ic_ui_delivery_note,
                    title = note.reference,
                    subtitle = stringResource(R.string.goodsout_card_delivery_note),
                    onClick = { open(deliveryNoteRoute(id = note.id, reference = note.reference, state = "")) },
                )
            }
        }
        person.trolleys.forEach { trolley ->
            add { IconInfoRow(icon = R.drawable.ic_ui_trolley, title = trolley.name, subtitle = stringResource(R.string.goodsout_backlog_trolley), tone = Tone.Neutral) }
        }
    }

    AikuBottomSheet(
        title = sessionPersonName(person.user) ?: person.user.name,
        subtitle = stringResource(if (role == DispatchRole.Packers) R.string.goodsout_backlog_packing_now else R.string.goodsout_backlog_picking_now),
        icon = R.drawable.ic_ui_person,
        onDismiss = onDismiss,
        footer = {
            AikuButton(
                text = stringResource(R.string.goodsout_extra_close),
                onClick = onDismiss,
                style = ButtonStyle.Outline,
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        },
    ) {
        if (rows.isEmpty()) {
            Text(text = stringResource(R.string.goodsout_backlog_person_nothing), fontSize = 14.sp, color = LocalAikuColors.current.textTertiary)
        } else {
            ListGroup {
                rows.forEachIndexed { index, row ->
                    if (index > 0) {
                        RowDivider()
                    }
                    row()
                }
            }
        }
    }
}

@Composable
private fun BacklogChannelCard(channel: ChannelCounts, onOpen: (DeliveryNoteBucket) -> Unit) {
    val colors = LocalAikuColors.current
    val neutral = colors.background to colors.textSecondary
    val active = colors.soft to colors.link
    val waitingCount = channel.count(DeliveryNoteBucket.HandlingBlocked)
    val waiting = if (waitingCount > 0) colors.warnSoft to colors.warn else neutral
    val finalised = colors.successSoft to colors.success
    val total = channel.buckets[DeliveryNoteBucket.InWarehouse.key] ?: backlogBuckets.sumOf { channel.count(it) }
    val tiles = listOf(
        backlogTile(R.string.dn_state_to_do, channel, DeliveryNoteBucket.Unassigned, neutral),
        backlogTile(R.string.dn_state_picking, channel, DeliveryNoteBucket.Handling, active),
        backlogTile(R.string.dn_state_waiting, channel, DeliveryNoteBucket.HandlingBlocked, waiting),
        backlogTile(R.string.dn_state_picked, channel, DeliveryNoteBucket.Picked, active),
        backlogTile(R.string.dn_state_packing, channel, DeliveryNoteBucket.Packing, active),
        backlogTile(R.string.dn_state_packed, channel, DeliveryNoteBucket.Packed, active),
        backlogTile(R.string.dn_state_finalised, channel, DeliveryNoteBucket.Finalised, finalised),
        BacklogTile(stringResource(R.string.goodsout_backlog_total), total, DeliveryNoteBucket.InWarehouse, neutral.first, neutral.second),
    )
    val name = channel.label.ifBlank { channelName(channel.channel).orEmpty() }

    AikuCard(padding = 14.dp, spacing = 10.dp) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 44.dp)
                .clip(RoundedCornerShape(6.dp))
                .clickable(role = Role.Button) { onOpen(DeliveryNoteBucket.InWarehouse) },
        ) {
            Text(text = name, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f))
            Icon(painter = painterResource(R.drawable.ic_ui_chevron_right), contentDescription = null, tint = colors.faint, modifier = Modifier.size(20.dp))
        }
        tiles.chunked(TILES_PER_ROW).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
                row.forEach { tile ->
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(2.dp),
                        modifier = Modifier
                            .weight(1f)
                            .heightIn(min = 44.dp)
                            .clip(RoundedCornerShape(8.dp))
                            .background(tile.background)
                            .clickable(role = Role.Button) { onOpen(tile.bucket) }
                            .padding(horizontal = 2.dp, vertical = 8.dp),
                    ) {
                        Text(text = tile.count.toString(), fontSize = 17.sp, fontWeight = FontWeight.Bold, color = tile.content)
                        Text(text = tile.label, fontSize = 10.sp, fontWeight = FontWeight.SemiBold, color = colors.textTertiary, maxLines = 1)
                    }
                }
            }
        }
    }
}

private val backlogBuckets = listOf(
    DeliveryNoteBucket.Unassigned,
    DeliveryNoteBucket.Queued,
    DeliveryNoteBucket.Handling,
    DeliveryNoteBucket.HandlingBlocked,
    DeliveryNoteBucket.Picked,
    DeliveryNoteBucket.Packing,
    DeliveryNoteBucket.Packed,
    DeliveryNoteBucket.Finalised,
)

@Composable
private fun backlogTile(@StringRes label: Int, channel: ChannelCounts, bucket: DeliveryNoteBucket, palette: Pair<Color, Color>): BacklogTile =
    BacklogTile(stringResource(label), channel.count(bucket), bucket, palette.first, palette.second)
