package io.aiku.droid.ui.clocking

import androidx.annotation.DrawableRes
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.components.EmptyState
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.SkeletonListCard
import io.aiku.droid.ui.components.StatusBadge
import io.aiku.droid.ui.theme.LocalAikuColors

@Composable
internal fun <T> HrPagedList(
    list: PagedList<T>,
    @DrawableRes emptyIcon: Int,
    emptyTitle: String,
    emptyBody: String,
    onRetry: () -> Unit,
    onLoadMore: () -> Unit,
    row: @Composable (T) -> Unit,
) {
    list.firstPageProblem?.let { problem -> ClockingLoadProblem(problem = problem, onRetry = onRetry) }
    when {
        list.items.isNotEmpty() -> ListGroup {
            list.items.forEachIndexed { index, item ->
                if (index > 0) {
                    RowDivider()
                }
                row(item)
            }
        }
        list.hasLoaded -> EmptyState(icon = emptyIcon, title = emptyTitle, body = emptyBody)
        list.problem == null -> SkeletonListCard(rows = 3)
    }
    PagedListFooter(list = list, onRetry = onLoadMore)
}

@Composable
internal fun HrRequestRow(content: @Composable ColumnScope.() -> Unit) {
    Column(
        verticalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 14.dp),
        content = content,
    )
}

@Composable
internal fun HrRequestTitle(title: String, statusLabel: String, status: String, leading: (@Composable () -> Unit)? = null) {
    val colors = LocalAikuColors.current

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        leading?.invoke()
        Text(
            text = title,
            fontSize = 15.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
        )
        if (statusLabel.isNotBlank()) {
            StatusBadge(text = statusLabel, tone = hrStatusTone(status))
        }
    }
}

@Composable
internal fun HrRequestLine(label: String, value: String, valueColor: Color? = null) {
    val colors = LocalAikuColors.current

    Row(horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth()) {
        Text(text = label, fontSize = 13.sp, color = colors.textTertiary, modifier = Modifier.width(96.dp))
        Text(text = value, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = valueColor ?: colors.text, modifier = Modifier.weight(1f))
    }
}

@Composable
internal fun HrRequestReason(reason: String?, refusal: String? = null) {
    val colors = LocalAikuColors.current

    reason?.takeIf { it.isNotBlank() }?.let {
        Text(text = it, fontSize = 13.sp, lineHeight = 18.sp, color = colors.textSecondary)
    }
    refusal?.takeIf { it.isNotBlank() }?.let {
        Text(text = stringResource(R.string.clocking_rejection_reason, it), fontSize = 13.sp, lineHeight = 18.sp, color = colors.dangerInk)
    }
}

@Composable
internal fun HrSectionIntro(text: String) {
    Text(
        text = text,
        fontSize = 13.sp,
        lineHeight = 19.sp,
        color = LocalAikuColors.current.textTertiary,
        modifier = Modifier.padding(horizontal = 4.dp),
    )
}
