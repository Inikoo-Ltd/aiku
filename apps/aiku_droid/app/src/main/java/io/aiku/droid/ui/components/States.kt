package io.aiku.droid.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.navigation.AppTab
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
fun MessagePanel(
    title: String,
    body: String,
    modifier: Modifier = Modifier,
    tone: Tone = Tone.Neutral,
    actions: @Composable () -> Unit = {},
) {
    val colors = LocalAikuColors.current

    AikuCard(modifier = modifier) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(8.dp), modifier = Modifier.fillMaxWidth()) {
            IconTile(icon = R.drawable.ic_alert_circle, tone = tone, size = 48.dp, iconSize = 24.dp)
            Text(text = title, fontSize = 17.sp, fontWeight = FontWeight.Bold, color = colors.text, textAlign = TextAlign.Center)
            Text(text = body, fontSize = 14.sp, color = colors.textTertiary, textAlign = TextAlign.Center)
        }
        actions()
    }
}

@Composable
fun RequireAbility(
    isAllowed: Boolean,
    title: String,
    activeTab: AppTab? = null,
    content: @Composable () -> Unit,
) {
    if (isAllowed) {
        content()
        return
    }
    val navigator = LocalAppNavigator.current

    AppScreen(header = { AppHeader(title = title) }, activeTab = activeTab) {
        MessagePanel(
            title = stringResource(R.string.no_access_title),
            body = stringResource(R.string.no_access_body),
            tone = Tone.Warn,
        ) {
            AikuButton(
                text = stringResource(R.string.no_access_back),
                onClick = { navigator.openTab(AppTab.Dashboard) },
                style = ButtonStyle.Outline,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
fun FullScreenLoading(modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Box(contentAlignment = Alignment.Center, modifier = modifier.fillMaxSize().background(colors.background)) {
        CircularProgressIndicator(color = colors.primary, modifier = Modifier.size(40.dp))
    }
}

@Composable
fun FullScreenMessage(
    title: String,
    body: String,
    modifier: Modifier = Modifier,
    actions: @Composable () -> Unit = {},
) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = modifier.fillMaxSize().background(colors.background).statusBarsPadding().padding(16.dp),
    ) {
        MessagePanel(title = title, body = body, tone = Tone.Warn, actions = actions)
    }
}
