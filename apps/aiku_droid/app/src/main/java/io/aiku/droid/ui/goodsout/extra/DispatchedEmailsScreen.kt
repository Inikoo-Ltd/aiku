package io.aiku.droid.ui.goodsout.extra

import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.AppScreen
import io.aiku.droid.ui.components.ListGroup
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.RowDivider
import io.aiku.droid.ui.components.fakeData
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.navigation.NotDesignedRoute

@Composable
fun DispatchedEmailsScreen(reference: String) {
    RequireAbility(ability = Ability.ViewGoodsOut, title = stringResource(R.string.module_goods_out), activeTab = MainTab.Menu) {
        DispatchedEmailsContent(reference = reference)
    }
}

@Composable
private fun DispatchedEmailsContent(reference: String) {
    val navigator = LocalAppNavigator.current
    val previewTitle = stringResource(R.string.goodsout_extra_email_preview_title)

    AppScreen(
        header = {
            AppHeader(
                isSample = true,
                title = stringResource(R.string.goodsout_extra_dispatched_emails),
                kicker = stringResource(R.string.goodsout_extra_delivery_note_reference, reference),
            )
        },
        activeTab = MainTab.Menu,
        showBar = true,
    ) {
        ListGroup(modifier = Modifier.fakeData()) {
            GoodsOutExtraSamples.emails.forEachIndexed { index, email ->
                if (index > 0) {
                    RowDivider()
                }
                ExtraRow(
                    title = stringResource(email.kind.label),
                    subtitle = listOfNotNull(
                        email.recipient,
                        email.sentAt,
                        email.orderReference?.let { stringResource(R.string.goodsout_extra_email_order, it) },
                    ).joinToString(KICKER_SEPARATOR),
                    chip = stringResource(email.state.label),
                    chipTone = email.state.tone,
                    leading = { ExtraIconTile(icon = R.drawable.ic_ui_mail, tone = email.state.tone) },
                    trailing = {
                        if (email.hasPreview) {
                            RowLink(
                                text = stringResource(R.string.goodsout_extra_email_preview),
                                onClick = { navigator.open(NotDesignedRoute(previewTitle)) },
                            )
                        }
                    },
                )
            }
        }
        HintText(text = stringResource(R.string.goodsout_extra_emails_hint))
    }
}
