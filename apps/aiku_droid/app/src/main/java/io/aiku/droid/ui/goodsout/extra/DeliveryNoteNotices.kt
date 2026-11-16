package io.aiku.droid.ui.goodsout.extra

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.Tone
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
internal fun joinedWithAnd(items: List<String>): String = when (items.size) {
    0 -> ""
    1 -> items.first()
    else -> stringResource(R.string.goodsout_extra_list_and, items.dropLast(1).joinToString(", "), items.last())
}

@Composable
internal fun IncompleteSetNotice(body: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.warnSoft)
            .border(1.dp, colors.warnBorder, CardShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Text(
            text = stringResource(R.string.goodsout_extra_incomplete_set_title),
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
        )
        Text(
            text = body,
            fontSize = 13.sp,
            lineHeight = 19.sp,
            color = colors.warnInk,
        )
    }
}

@Composable
internal fun AssignedElsewhereCard(title: String, body: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.warnSoft)
            .border(1.dp, colors.warnBorder, CardShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        ExtraIconTile(icon = R.drawable.ic_ui_lock, tone = Tone.Warn)
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = body, fontSize = 13.sp, color = colors.warnInk)
        }
    }
}
