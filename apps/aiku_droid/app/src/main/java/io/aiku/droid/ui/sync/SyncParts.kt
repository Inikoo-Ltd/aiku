package io.aiku.droid.ui.sync

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
internal fun OfflineStatusBar(text: String, onView: () -> Unit, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(10.dp)

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = modifier
            .fillMaxWidth()
            .fakeData(cornerRadius = 10.dp)
            .clip(shape)
            .background(colors.warnSoft)
            .padding(horizontal = 12.dp, vertical = 8.dp),
    ) {
        Box(modifier = Modifier.size(8.dp).clip(CircleShape).background(colors.warn))
        Text(text = text, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = colors.warn, modifier = Modifier.weight(1f))
        Text(
            text = stringResource(R.string.sync_view),
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            color = colors.warn,
            modifier = Modifier.clickable(role = Role.Button, onClick = onView).padding(vertical = 6.dp),
        )
    }
}
