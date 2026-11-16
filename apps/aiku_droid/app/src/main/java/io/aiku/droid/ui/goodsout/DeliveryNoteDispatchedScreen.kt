package io.aiku.droid.ui.goodsout

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.DeliveryNote
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.GoodsOutBacklogRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun DeliveryNoteDispatchedScreen(note: DeliveryNoteViewModel, reference: String) {
    DeliveryNoteScreen(note = note, reference = reference)
}

@Composable
internal fun DispatchedHero(note: DeliveryNote) {
    val navigator = LocalAppNavigator.current
    val colors = LocalAikuColors.current
    val detail = shipmentSummary(note)

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 20.dp),
    ) {
        Box(
            contentAlignment = Alignment.Center,
            modifier = Modifier.size(72.dp).clip(CircleShape).background(colors.successSoft),
        ) {
            Icon(painter = painterResource(R.drawable.ic_ui_check), contentDescription = null, tint = colors.success, modifier = Modifier.size(36.dp))
        }
        Text(
            text = stringResource(R.string.goodsout_dispatched_title, note.reference),
            fontSize = 22.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
            textAlign = TextAlign.Center,
        )
        if (detail.isNotEmpty()) {
            Text(text = detail, fontSize = 14.sp, color = colors.textTertiary, textAlign = TextAlign.Center)
        }
    }
    AikuButton(
        text = stringResource(R.string.goodsout_next_delivery_note),
        onClick = { navigator.switchSection(DeliveryNotesRoute()) },
        height = 52.dp,
        modifier = Modifier.fillMaxWidth(),
    )
    AikuButton(
        text = stringResource(R.string.goodsout_back_to_backlog),
        onClick = { navigator.switchSection(GoodsOutBacklogRoute) },
        style = ButtonStyle.Outline,
        height = 52.dp,
        modifier = Modifier.fillMaxWidth(),
    )
}
