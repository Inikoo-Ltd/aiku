package io.aiku.droid.ui.states

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.LocalAikuColors

data class ConflictChange(val label: String, val detail: String)

@Composable
fun ActionFailedSheet(
    title: String,
    errorTitle: String,
    errorBody: String,
    retryLabel: String,
    onRetry: () -> Unit,
    onDismiss: () -> Unit,
    @DrawableRes icon: Int? = null,
    context: String? = null,
    fields: @Composable ColumnScope.() -> Unit,
) {
    AikuBottomSheet(title = title, onDismiss = onDismiss, icon = icon, context = context) {
        ActionFailedContent(
            errorTitle = errorTitle,
            errorBody = errorBody,
            retryLabel = retryLabel,
            onRetry = onRetry,
            fields = fields,
        )
    }
}

@Composable
fun ActionFailedContent(
    errorTitle: String,
    errorBody: String,
    retryLabel: String,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
    fields: @Composable ColumnScope.() -> Unit,
) {
    Column(verticalArrangement = Arrangement.spacedBy(16.dp), modifier = modifier.fillMaxWidth()) {
        InlineAlert(title = errorTitle, body = errorBody, tone = AlertTone.Danger)
        fields()
        AikuButton(text = retryLabel, onClick = onRetry, modifier = Modifier.fillMaxWidth(), height = 52.dp)
    }
}

@Composable
fun FieldErrorMessage(text: String, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = modifier.semantics { liveRegion = LiveRegionMode.Polite },
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_alert_circle),
            contentDescription = null,
            tint = colors.danger,
            modifier = Modifier.size(15.dp),
        )
        Text(text = text, fontSize = 13.sp, fontWeight = FontWeight.Normal, color = colors.danger)
    }
}

@Composable
fun EditConflictScreen(
    kicker: String,
    title: String,
    reloadLabel: String,
    onReload: () -> Unit,
    onBackToList: () -> Unit,
    modifier: Modifier = Modifier,
    content: @Composable ColumnScope.() -> Unit,
) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier.fillMaxSize().background(colors.background)) {
        AppHeader(title = title, kicker = kicker)
        Column(
            verticalArrangement = Arrangement.spacedBy(16.dp),
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            content = content,
        )
        Column(
            verticalArrangement = Arrangement.spacedBy(10.dp),
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(start = 16.dp, end = 16.dp, bottom = 16.dp),
        ) {
            AikuButton(
                text = reloadLabel,
                onClick = onReload,
                modifier = Modifier.fillMaxWidth(),
                icon = R.drawable.ic_states_retry,
                height = 52.dp,
            )
            AikuButton(
                text = stringResource(R.string.states_conflict_back_to_list),
                onClick = onBackToList,
                modifier = Modifier.fillMaxWidth(),
                style = ButtonStyle.Outline,
                height = 52.dp,
            )
        }
    }
}

@Composable
fun EditConflictAlert(title: String, body: String, onReload: () -> Unit, modifier: Modifier = Modifier) {
    InlineAlert(
        title = title,
        body = body,
        tone = AlertTone.Warn,
        actionLabel = stringResource(R.string.states_conflict_reload_now),
        onAction = onReload,
        modifier = modifier,
    )
}

@Composable
fun ConflictChangesCard(changes: List<ConflictChange>, modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(CardShape)
            .background(colors.surface)
            .border(1.dp, colors.border, CardShape)
            .padding(top = 16.dp),
    ) {
        Text(
            text = stringResource(R.string.states_conflict_what_changed).uppercase(),
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            color = colors.textTertiary,
            modifier = Modifier.padding(horizontal = 16.dp),
        )
        changes.forEachIndexed { index, change ->
            if (index > 0) {
                RowDivider()
            }
            ConflictChangeRow(change = change)
        }
    }
}

@Composable
private fun ConflictChangeRow(change: ConflictChange) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 12.dp),
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(2.dp), modifier = Modifier.weight(1f)) {
            Text(text = change.label, fontSize = 15.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = change.detail, fontSize = 13.sp, fontWeight = FontWeight.Normal, color = colors.textTertiary)
        }
        Text(
            text = stringResource(R.string.states_conflict_updated),
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.warn,
            maxLines = 1,
            modifier = Modifier
                .clip(CircleShape)
                .background(colors.warnSoft)
                .padding(horizontal = 9.dp, vertical = 3.dp),
        )
    }
}

@Composable
private fun SampleValueField(label: String, value: String, isError: Boolean = false, helper: (@Composable () -> Unit)? = null) {
    val colors = LocalAikuColors.current
    val shape = RoundedCornerShape(6.dp)
    val borderColor: Color = if (isError) colors.danger else colors.borderStrong

    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(text = label, fontSize = 14.sp, fontWeight = FontWeight.Medium, color = colors.textSecondary)
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .fillMaxWidth()
                .height(48.dp)
                .clip(shape)
                .background(colors.surface)
                .border(1.dp, borderColor, shape)
                .padding(horizontal = 12.dp),
        ) {
            Text(text = value, fontSize = 16.sp, fontWeight = FontWeight.Normal, color = colors.text)
        }
        helper?.invoke()
    }
}

@Preview(showBackground = true)
@Composable
private fun ActionFailedContentPreview() {
    AikuDroidTheme {
        StatesPreviewSurface {
            ActionFailedContent(
                errorTitle = StatesTransferFailureSample.ERROR_TITLE,
                errorBody = StatesTransferFailureSample.ERROR_BODY,
                retryLabel = StatesTransferFailureSample.RETRY_LABEL,
                onRetry = {},
                modifier = Modifier.fakeData().padding(8.dp),
            ) {
                SampleValueField(label = StatesTransferFailureSample.FROM_LABEL, value = StatesTransferFailureSample.FROM_LOCATION) {
                    Text(text = StatesTransferFailureSample.FROM_HELPER, fontSize = 13.sp, fontWeight = FontWeight.Normal, color = LocalAikuColors.current.textTertiary)
                }
                SampleValueField(label = StatesTransferFailureSample.TO_LABEL, value = StatesTransferFailureSample.TO_LOCATION)
                SampleValueField(
                    label = StatesTransferFailureSample.QUANTITY_LABEL,
                    value = StatesTransferFailureSample.QUANTITY,
                    isError = true,
                ) {
                    FieldErrorMessage(text = StatesTransferFailureSample.QUANTITY_ERROR)
                }
            }
        }
    }
}

@Preview(showSystemUi = true)
@Composable
private fun EditConflictScreenPreview() {
    AikuDroidTheme {
        EditConflictScreen(
            kicker = stringResource(R.string.states_conflict_delivery_note_kicker),
            title = StatesConflictSample.REFERENCE,
            reloadLabel = stringResource(R.string.states_conflict_delivery_note_reload),
            onReload = {},
            onBackToList = {},
        ) {
            EditConflictAlert(
                title = stringResource(R.string.states_conflict_delivery_note_title),
                body = StatesConflictSample.MESSAGE,
                onReload = {},
                modifier = Modifier.fakeData(8.dp),
            )
            ConflictChangesCard(changes = statesConflictChangesSample, modifier = Modifier.fakeData())
        }
    }
}

@Composable
internal fun ActionFailedPreview() {
    val navigator = io.aiku.droid.ui.navigation.LocalAppNavigator.current

    io.aiku.droid.ui.components.AppScreen(
        header = {
            AppHeader(
                title = stringResource(R.string.states_action_failed_title),
                kicker = StatesTransferFailureSample.SKO,
                isSample = true,
            )
        },
        showBar = false,
    ) {
        ActionFailedContent(
            errorTitle = StatesTransferFailureSample.ERROR_TITLE,
            errorBody = StatesTransferFailureSample.ERROR_BODY,
            retryLabel = StatesTransferFailureSample.RETRY_LABEL,
            onRetry = navigator::back,
            modifier = Modifier.fakeData().padding(8.dp),
        ) {
            SampleValueField(label = StatesTransferFailureSample.FROM_LABEL, value = StatesTransferFailureSample.FROM_LOCATION) {
                Text(text = StatesTransferFailureSample.FROM_HELPER, fontSize = 13.sp, fontWeight = FontWeight.Normal, color = LocalAikuColors.current.textTertiary)
            }
            SampleValueField(label = StatesTransferFailureSample.TO_LABEL, value = StatesTransferFailureSample.TO_LOCATION)
            SampleValueField(label = StatesTransferFailureSample.QUANTITY_LABEL, value = StatesTransferFailureSample.QUANTITY, isError = true) {
                FieldErrorMessage(text = StatesTransferFailureSample.QUANTITY_ERROR)
            }
        }
    }
}
