package io.aiku.droid.ui.goodsout

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.ui.components.HeaderTab
import io.aiku.droid.ui.components.HeaderTabs
import io.aiku.droid.ui.navigation.DeliveryNotesRoute
import io.aiku.droid.ui.navigation.GoodsOutBacklogRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.ShippersRoute

internal enum class GoodsOutSection { Backlog, DeliveryNotes, Shippers }

@Composable
internal fun GoodsOutSectionTabs(active: GoodsOutSection, modifier: Modifier = Modifier) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can

    HeaderTabs(
        modifier = modifier,
        tabs = listOfNotNull(
            HeaderTab(
                label = stringResource(R.string.goodsout_tab_backlog),
                icon = R.drawable.ic_goodsout_backlog,
                isActive = active == GoodsOutSection.Backlog,
                onClick = { navigator.switchSection(GoodsOutBacklogRoute) },
            ),
            HeaderTab(
                label = stringResource(R.string.goodsout_tab_delivery_notes),
                icon = R.drawable.ic_goodsout_delivery_note,
                isActive = active == GoodsOutSection.DeliveryNotes,
                onClick = { navigator.switchSection(DeliveryNotesRoute) },
            ),
            HeaderTab(
                label = stringResource(R.string.goodsout_tab_shippers),
                icon = R.drawable.ic_goodsout_shipper,
                isActive = active == GoodsOutSection.Shippers,
                onClick = { navigator.switchSection(ShippersRoute) },
            ),
            if (can.viewFulfilment) {
                HeaderTab(
                    label = stringResource(R.string.goodsout_tab_fulfilment_returns),
                    icon = R.drawable.ic_tab_goods_in,
                    isActive = false,
                    onClick = {},
                )
            } else {
                null
            },
        ),
    )
}
