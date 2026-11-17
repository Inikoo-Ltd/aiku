package io.aiku.droid.ui.fulfilment

import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.theme.LocalAikuColors

internal enum class FulfilmentDeliveryState(@StringRes val label: Int, val tone: Tone) {
    Confirmed(R.string.fulfilment_state_confirmed, Tone.Neutral),
    Received(R.string.fulfilment_state_received, Tone.Primary),
    BookingIn(R.string.fulfilment_state_booking_in, Tone.Primary),
    BookedIn(R.string.fulfilment_state_booked_in, Tone.Success),
    NotReceived(R.string.fulfilment_state_not_received, Tone.Danger),
}

internal enum class FulfilmentPalletType(@StringRes val label: Int) {
    Pallet(R.string.fulfilment_type_pallet),
    Box(R.string.fulfilment_type_box),
    Oversize(R.string.fulfilment_type_oversize),
}

internal enum class StoredPalletFilter(@StringRes val label: Int) {
    Storing(R.string.fulfilment_state_storing),
    Receiving(R.string.fulfilment_state_receiving),
    Returning(R.string.fulfilment_state_returning),
    Incidents(R.string.fulfilment_state_incidents),
    Returned(R.string.fulfilment_state_returned),
}

internal enum class PalletIncidentKind(@StringRes val label: Int, val tone: Tone) {
    Damaged(R.string.fulfilment_state_damaged, Tone.Danger),
    Lost(R.string.fulfilment_state_lost, Tone.Danger),
    Other(R.string.fulfilment_state_other, Tone.Warn),
}

internal fun dotJoined(vararg parts: String?): String = parts.filterNotNull().filter { it.isNotBlank() }.joinToString(" · ")

@Composable
internal fun filterChipLabel(label: String, count: Int?): String =
    if (count == null) label else stringResource(R.string.fulfilment_filter_with_count, label, count)

@Composable
internal fun RequireFulfilment(@StringRes section: Int, content: @Composable () -> Unit) {
    RequireAbility(
        ability = Ability.ViewFulfilment,
        title = stringResource(section),
        activeTab = MainTab.Menu,
        content = content,
    )
}

@Composable
internal fun FulfilmentRow(
    title: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    chip: String? = null,
    chipTone: Tone = Tone.Neutral,
    leading: (@Composable () -> Unit)? = null,
    trailing: (@Composable () -> Unit)? = null,
    onClick: (() -> Unit)? = null,
) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .heightIn(min = 60.dp)
            .then(if (onClick != null) Modifier.clickable(role = Role.Button, onClick = onClick) else Modifier)
            .padding(horizontal = 14.dp, vertical = 10.dp),
    ) {
        leading?.invoke()
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    text = title,
                    fontSize = 15.sp,
                    fontWeight = FontWeight.Bold,
                    color = colors.text,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis,
                    modifier = Modifier.weight(1f, fill = false),
                )
                if (chip != null) {
                    StatusBadge(text = chip, tone = chipTone)
                }
            }
            if (subtitle != null) {
                Text(text = subtitle, fontSize = 13.sp, color = colors.textTertiary, maxLines = 2, overflow = TextOverflow.Ellipsis)
            }
        }
        trailing?.invoke()
    }
}

@Composable
internal fun FulfilmentFootnote(text: String, modifier: Modifier = Modifier) {
    Text(
        text = text,
        fontSize = 12.sp,
        lineHeight = 18.sp,
        color = LocalAikuColors.current.textQuaternary,
        modifier = modifier.padding(horizontal = 4.dp),
    )
}

@Composable
internal fun FulfilmentScanPrompt(
    title: String,
    instruction: String,
    target: String,
    modifier: Modifier = Modifier,
    detail: String? = null,
) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.scan)
            .padding(14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(painter = painterResource(R.drawable.ic_ui_scan), contentDescription = null, tint = colors.onScan, modifier = Modifier.size(20.dp))
            Text(text = title, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.onScan)
        }
        Text(text = instruction, fontSize = 13.sp, color = colors.onScan)
        Text(text = target, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = colors.onScan)
        if (detail != null) {
            Text(text = detail, fontSize = 13.sp, color = colors.onScan)
        }
    }
}

@Composable
internal fun FulfilmentSuccessHero(title: String, body: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 24.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.size(72.dp).clip(CircleShape).background(colors.successSoft)) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.success, modifier = Modifier.size(36.dp))
        }
        Text(text = title, fontSize = 22.sp, fontWeight = FontWeight.Bold, color = colors.text, textAlign = TextAlign.Center)
        Text(text = body, fontSize = 14.sp, color = colors.textTertiary, textAlign = TextAlign.Center)
    }
}
