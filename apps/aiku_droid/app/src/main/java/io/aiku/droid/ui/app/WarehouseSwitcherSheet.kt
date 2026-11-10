package io.aiku.droid.ui.app

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.data.api.WarehouseAccess
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.initialsOf
import io.aiku.droid.ui.theme.LocalAikuColors

private val GroupShape = RoundedCornerShape(10.dp)

@Composable
internal fun WarehouseSwitcherSheet(
    warehouses: List<WarehouseAccess>,
    selected: WarehouseAccess,
    onSwitch: (Long) -> Unit,
    onDismiss: () -> Unit,
) {
    val colors = LocalAikuColors.current
    var chosenId by remember(selected.id) { mutableLongStateOf(selected.id) }
    val chosen = warehouses.firstOrNull { it.id == chosenId } ?: selected
    val organisations = warehouses.groupBy { it.organisation.id }.values.toList()
    val chosenLabel = "${chosen.organisation.name} · ${chosen.name}"

    AikuBottomSheet(
        title = stringResource(R.string.profile_org_warehouse),
        onDismiss = onDismiss,
        icon = R.drawable.ic_ui_warehouse,
    ) {
        Column(verticalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.selectableGroup()) {
            organisations.forEach { organisationWarehouses ->
                val organisation = organisationWarehouses.first().organisation
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp),
                        modifier = Modifier.padding(top = 4.dp),
                    ) {
                        Box(
                            contentAlignment = Alignment.Center,
                            modifier = Modifier.size(28.dp).clip(RoundedCornerShape(6.dp)).background(colors.nav),
                        ) {
                            Text(text = initialsOf(organisation.name), fontSize = 11.sp, fontWeight = FontWeight.Bold, color = colors.onNav)
                        }
                        Text(text = organisation.name, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = colors.text, modifier = Modifier.weight(1f))
                        Text(
                            text = pluralStringResource(R.plurals.profile_switcher_warehouse_count, organisationWarehouses.size, organisationWarehouses.size),
                            fontSize = 12.sp,
                            color = colors.textTertiary,
                        )
                    }
                    Column(modifier = Modifier.fillMaxWidth().clip(GroupShape).border(1.dp, colors.border, GroupShape)) {
                        organisationWarehouses.forEachIndexed { index, warehouse ->
                            if (index > 0) {
                                RowDivider()
                            }
                            WarehouseChoice(
                                warehouse = warehouse,
                                isChosen = warehouse.id == chosenId,
                                isCurrent = warehouse.id == selected.id,
                                onChoose = { chosenId = warehouse.id },
                            )
                        }
                    }
                }
            }
            Text(text = stringResource(R.string.profile_switcher_note), fontSize = 12.sp, lineHeight = 17.sp, color = colors.textTertiary)
            AikuButton(
                text = if (chosenId == selected.id) {
                    stringResource(R.string.profile_switcher_keep, chosenLabel)
                } else {
                    stringResource(R.string.profile_switcher_switch_to, chosenLabel)
                },
                onClick = { if (chosenId == selected.id) onDismiss() else onSwitch(chosenId) },
                height = 52.dp,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun WarehouseChoice(warehouse: WarehouseAccess, isChosen: Boolean, isCurrent: Boolean, onChoose: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .heightIn(min = 56.dp)
            .background(if (isChosen) colors.soft else colors.surface)
            .then(if (isChosen) Modifier.border(2.dp, colors.primary) else Modifier)
            .selectable(selected = isChosen, role = Role.RadioButton, onClick = onChoose)
            .padding(horizontal = 14.dp, vertical = 8.dp),
    ) {
        Icon(painter = painterResource(R.drawable.ic_ui_warehouse), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
        Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = warehouse.name, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.text)
            Text(
                text = if (isCurrent) stringResource(R.string.switcher_current, warehouse.code) else warehouse.code,
                fontSize = 12.sp,
                color = colors.textTertiary,
            )
        }
        Icon(
            painter = painterResource(R.drawable.ic_ui_check),
            contentDescription = null,
            tint = colors.link,
            modifier = Modifier.size(20.dp).alpha(if (isChosen) 1f else 0f),
        )
    }
}
