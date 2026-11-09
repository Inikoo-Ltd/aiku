package io.aiku.droid.ui.goodsin

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.ui.components.HeaderTab
import io.aiku.droid.ui.components.HeaderTabs
import io.aiku.droid.ui.navigation.GoodsInBacklogRoute
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.LocalWarehouse
import io.aiku.droid.ui.navigation.StockDeliveriesRoute

internal enum class GoodsInSection { Backlog, StockDeliveries }

@Composable
internal fun GoodsInSectionTabs(active: GoodsInSection, modifier: Modifier = Modifier) {
    val navigator = LocalAppNavigator.current
    val can = LocalWarehouse.current.can

    HeaderTabs(
        modifier = modifier,
        tabs = listOfNotNull(
            HeaderTab(
                label = stringResource(R.string.goodsin_tab_backlog),
                icon = R.drawable.ic_goodsin_backlog,
                isActive = active == GoodsInSection.Backlog,
                onClick = { navigator.switchSection(GoodsInBacklogRoute) },
            ),
            HeaderTab(
                label = stringResource(R.string.goodsin_tab_stock_deliveries),
                icon = R.drawable.ic_goodsin_stock_delivery,
                isActive = active == GoodsInSection.StockDeliveries,
                onClick = { navigator.switchSection(StockDeliveriesRoute) },
            ),
            if (can.viewFulfilment) {
                HeaderTab(
                    label = stringResource(R.string.goodsin_tab_fulfilment_deliveries),
                    icon = R.drawable.ic_goodsin_stock_delivery,
                    isActive = false,
                    onClick = {},
                )
            } else {
                null
            },
        ),
    )
}
