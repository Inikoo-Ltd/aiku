package io.aiku.droid.ui.goodsin

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.geometry.Rect
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.drawscope.scale
import androidx.compose.ui.platform.LocalResources
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LifecycleEventEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.aiku.droid.R
import io.aiku.droid.data.api.Ability
import io.aiku.droid.data.api.StockDelivery
import io.aiku.droid.data.api.StockDeliveryItem
import io.aiku.droid.ui.components.AikuBottomSheet
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.ButtonLook
import io.aiku.droid.ui.components.ButtonStyle
import io.aiku.droid.ui.components.ControlShape
import io.aiku.droid.ui.components.ProductPhoto
import io.aiku.droid.ui.components.ProgressBar
import io.aiku.droid.ui.components.RequireAbility
import io.aiku.droid.ui.components.rememberTabIndication
import io.aiku.droid.ui.goodsout.quantityText
import io.aiku.droid.ui.inventory.SheetButtons
import io.aiku.droid.ui.inventory.SheetTextField
import io.aiku.droid.ui.inventory.scanProblemText
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.navigation.MainTab
import io.aiku.droid.ui.scan.BarcodeSet
import io.aiku.droid.ui.scan.CameraBarcodePreview
import io.aiku.droid.ui.scan.LocalScanController
import io.aiku.droid.ui.scan.ScanEffect
import io.aiku.droid.ui.scan.ScannerReadyNotice
import io.aiku.droid.ui.scan.rememberCameraAccess
import io.aiku.droid.ui.scan.rememberScanCamera
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.LocalAikuColors

private const val CAMERA_OVERLAY_ALPHA = 0.6f

private sealed interface CameraFeedback {
    data class Found(val code: String) : CameraFeedback
    data class Problem(val text: String) : CameraFeedback
}

private sealed interface CameraSheet {
    data object TypeCode : CameraSheet
    data class Check(val itemId: Long) : CameraSheet
    data class ChooseLine(val code: String, val itemIds: List<Long>) : CameraSheet
}

@Composable
fun ScanCameraScreen(delivery: StockDeliveryViewModel, reference: String) {
    val state by delivery.state.collectAsStateWithLifecycle()

    LifecycleEventEffect(Lifecycle.Event.ON_START) { delivery.onStart() }
    LaunchedEffect(delivery) { delivery.selectView(DeliveryView.Check) }

    RequireAbility(ability = Ability.ViewGoodsIn, title = stringResource(R.string.module_goods_in), activeTab = MainTab.Menu) {
        val loaded = state.delivery
        when {
            state.isSessionExpired -> SessionExpiredScreen(onSignIn = delivery::signInAgain)
            loaded != null -> ScanCameraContent(viewModel = delivery, state = state, delivery = loaded)
            else -> StockDeliveryLoading(reference = reference, problem = state.problem, onRetry = delivery::retry)
        }
    }
}

@Composable
private fun ScanCameraContent(viewModel: StockDeliveryViewModel, state: StockDeliveryUiState, delivery: StockDelivery) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val resources = LocalResources.current
    val scanCamera = rememberScanCamera()
    val access = if (scanCamera.isOn) rememberCameraAccess() else null
    val isCameraAllowed = access?.isAllowed == true
    val scanFeedback = LocalScanController.current.feedback
    var isTorchOn by remember { mutableStateOf(false) }
    var currentItemId by remember { mutableStateOf<Long?>(null) }
    var feedback by remember { mutableStateOf<CameraFeedback?>(null) }
    var sheet by remember { mutableStateOf<CameraSheet?>(null) }
    var sheetProblem by remember { mutableStateOf<String?>(null) }
    val current = currentItemId?.let(delivery::item) ?: delivery.items.firstOrNull { it.needsCheck }
    val outcome = state.scanOutcome
    val notOnDelivery = (outcome as? LineScanOutcome.NotOnDelivery)?.let { stringResource(R.string.goodsin_scan_not_on_delivery, it.code, delivery.reference) }
    val failed = (outcome as? LineScanOutcome.Failed)?.let { scanProblemText(it.code, it.problem) }
    val closeSheet: () -> Unit = {
        sheet = null
        sheetProblem = null
    }
    val showLine: (Long) -> Unit = { itemId ->
        currentItemId = itemId
        feedback = delivery.item(itemId)?.let { CameraFeedback.Found(it.code) }
    }
    val cameraBackground = Brush.radialGradient(colors = listOf(colors.toast, colors.scrim.copy(alpha = 1f)))

    ScanEffect(enabled = sheet == null) { scan -> viewModel.scanLine(scan.code) }
    GoodsInEvents(
        event = state.event,
        onHandled = viewModel::eventHandled,
        onSheetProblem = if (sheet != null) ({ sheetProblem = it }) else null,
        doneText = { done -> checkDoneText(done, state.delivery ?: delivery) { id, args -> resources.getString(id, *args) } },
        onDone = { closeSheet() },
    )
    LaunchedEffect(outcome) {
        when (outcome) {
            is LineScanOutcome.Line -> showLine(outcome.itemId)
            is LineScanOutcome.Choose -> sheet = CameraSheet.ChooseLine(outcome.code, outcome.itemIds)
            is LineScanOutcome.NotOnDelivery -> {
                scanFeedback.rejected()
                feedback = notOnDelivery?.let { CameraFeedback.Problem(it) }
            }
            is LineScanOutcome.Failed -> feedback = failed?.let { CameraFeedback.Problem(it) }
            null -> return@LaunchedEffect
        }
        viewModel.scanOutcomeHandled()
    }

    Column(modifier = Modifier.fillMaxSize().background(cameraBackground)) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .padding(top = 8.dp, bottom = 16.dp),
        ) {
            CameraTopBar(
                reference = delivery.reference,
                isTorchOn = isTorchOn,
                showTorch = isCameraAllowed,
                onToggleTorch = { isTorchOn = !isTorchOn },
                onClose = { navigator.back() },
            )
            Spacer(modifier = Modifier.height(16.dp))
            CameraModeSwitch(isCameraOn = scanCamera.isOn, onCamera = scanCamera.useCamera, onBluetoothScanner = { navigator.back() })
            Spacer(modifier = Modifier.height(24.dp))
            val scannerReady = scanCamera.scannerReady
            if (scannerReady != null) {
                ScannerReadyNotice(reason = scannerReady, onUseCamera = scanCamera.useCamera)
            } else {
                Viewfinder {
                    if (isCameraAllowed && sheet == null) {
                        CameraBarcodePreview(
                            barcodes = BarcodeSet.Warehouse,
                            onScanned = viewModel::scanLine,
                            isTorchOn = isTorchOn,
                            modifier = Modifier.fillMaxSize(),
                        )
                    }
                }
                Spacer(modifier = Modifier.height(16.dp))
                Text(
                    text = stringResource(if (access?.isRefused == true) R.string.scan_camera_off_body else R.string.goodsin_point_at_barcode),
                    fontSize = 14.sp,
                    color = colors.onNav,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.padding(horizontal = 16.dp),
                )
            }
            Spacer(modifier = Modifier.height(16.dp))
            val lookingUp = state.lookingUpCode
            when {
                lookingUp != null -> ScanFeedback(text = stringResource(R.string.scan_looking_up, lookingUp), isProblem = false)
                feedback is CameraFeedback.Found -> ScanFeedback(text = stringResource(R.string.goodsin_scan_found, (feedback as CameraFeedback.Found).code), isProblem = false)
                feedback is CameraFeedback.Problem -> ScanFeedback(text = (feedback as CameraFeedback.Problem).text, isProblem = true)
            }
        }
        CurrentItemPanel(
            item = current,
            isScanned = currentItemId != null,
            canCheck = delivery.canCheck,
            onTypeCode = { sheet = CameraSheet.TypeCode },
            onSetQuantity = { item -> sheet = CameraSheet.Check(item.id) },
            onDone = { navigator.back() },
        )
    }

    when (val open = sheet) {
        CameraSheet.TypeCode -> TypeCodeSheet(
            onFind = { code ->
                sheet = null
                viewModel.scanLine(code)
            },
            onDismiss = closeSheet,
        )
        is CameraSheet.Check -> delivery.item(open.itemId)?.let { item ->
            CheckedQuantitySheet(
                item = item,
                isWorking = state.isWorking,
                problem = sheetProblem,
                onSave = { quantity -> viewModel.check(item.id, quantity) },
                onNotReceived = { viewModel.notReceived(item.id) },
                onDismiss = closeSheet,
            )
        }
        is CameraSheet.ChooseLine -> ChooseLineSheet(
            code = open.code,
            items = open.itemIds.mapNotNull(delivery::item),
            onChoose = { item ->
                sheet = null
                showLine(item.id)
            },
            onDismiss = closeSheet,
        )
        null -> Unit
    }
}

@Composable
private fun CameraTopBar(reference: String, isTorchOn: Boolean, showTorch: Boolean, onToggleTorch: () -> Unit, onClose: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp),
    ) {
        CameraRoundButton(icon = R.drawable.ic_ui_close, description = stringResource(R.string.goodsin_close_scanner), onClick = onClose)
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.weight(1f).padding(vertical = 2.dp)) {
            Text(text = stringResource(R.string.goodsin_camera_context), fontSize = 12.sp, color = colors.borderStrong)
            Text(text = reference, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.onNav)
        }
        if (showTorch) {
            CameraRoundButton(
                icon = R.drawable.ic_ui_torch,
                description = stringResource(if (isTorchOn) R.string.goodsin_torch_off else R.string.goodsin_torch_on),
                onClick = onToggleTorch,
                isActive = isTorchOn,
            )
        } else {
            Spacer(modifier = Modifier.size(44.dp))
        }
    }
}

@Composable
private fun CameraRoundButton(@DrawableRes icon: Int, description: String, onClick: () -> Unit, isActive: Boolean = false) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .size(44.dp)
            .clip(CircleShape)
            .background(if (isActive) colors.scan else colors.scrim.copy(alpha = CAMERA_OVERLAY_ALPHA))
            .clickable(role = Role.Button, onClickLabel = description, onClick = onClick)
            .semantics { contentDescription = description },
    ) {
        Icon(painter = painterResource(icon), contentDescription = null, tint = if (isActive) colors.onScan else colors.onNav, modifier = Modifier.size(22.dp))
    }
}

@Composable
private fun CameraModeSwitch(isCameraOn: Boolean, onCamera: () -> Unit, onBluetoothScanner: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        modifier = Modifier
            .clip(CircleShape)
            .background(colors.scrim.copy(alpha = CAMERA_OVERLAY_ALPHA))
            .padding(horizontal = 2.dp),
    ) {
        CameraModePill(icon = R.drawable.ic_ui_camera, label = stringResource(R.string.goodsin_camera), isActive = isCameraOn, onClick = if (isCameraOn) ({}) else onCamera)
        CameraModePill(
            icon = R.drawable.ic_ui_scanner,
            label = stringResource(R.string.goodsin_bluetooth_scanner),
            isActive = !isCameraOn,
            onClick = if (isCameraOn) onBluetoothScanner else ({}),
        )
    }
}

@Composable
private fun CameraModePill(@DrawableRes icon: Int, label: String, isActive: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val content = if (isActive) colors.onScan else colors.onNav

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .height(44.dp)
            .selectable(
                selected = isActive,
                interactionSource = remember { MutableInteractionSource() },
                indication = rememberTabIndication(cornerRadius = 22.dp),
                role = Role.Tab,
                onClick = onClick,
            )
            .padding(horizontal = 2.dp, vertical = 5.dp)
            .clip(CircleShape)
            .background(if (isActive) colors.scan else Color.Transparent)
            .padding(horizontal = 12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(16.dp))
            Text(text = label, fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = content, maxLines = 1)
        }
    }
}

@Composable
private fun Viewfinder(content: @Composable () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        modifier = Modifier
            .size(width = 300.dp, height = 190.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(colors.scrim.copy(alpha = CAMERA_OVERLAY_ALPHA))
            .drawWithContent {
                drawContent()
                val stroke = 4.dp.toPx()
                val arm = 36.dp.toPx()
                val radius = 10.dp.toPx()
                drawCorner(color = colors.scan, stroke = stroke, arm = arm, radius = radius)
                scale(scaleX = -1f, scaleY = 1f) { drawCorner(color = colors.scan, stroke = stroke, arm = arm, radius = radius) }
                scale(scaleX = 1f, scaleY = -1f) { drawCorner(color = colors.scan, stroke = stroke, arm = arm, radius = radius) }
                scale(scaleX = -1f, scaleY = -1f) { drawCorner(color = colors.scan, stroke = stroke, arm = arm, radius = radius) }
            },
    ) {
        content()
    }
}

private fun DrawScope.drawCorner(color: Color, stroke: Float, arm: Float, radius: Float) {
    val inset = stroke / 2
    val path = Path().apply {
        moveTo(x = inset, y = arm)
        lineTo(x = inset, y = inset + radius)
        arcTo(
            rect = Rect(left = inset, top = inset, right = inset + radius * 2, bottom = inset + radius * 2),
            startAngleDegrees = 180f,
            sweepAngleDegrees = 90f,
            forceMoveTo = false,
        )
        lineTo(x = arm, y = inset)
    }
    drawPath(path = path, color = color, style = Stroke(width = stroke))
}

@Composable
private fun ScanFeedback(text: String, isProblem: Boolean) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp)
            .clip(ControlShape)
            .background(if (isProblem) colors.danger else colors.success)
            .semantics { liveRegion = LiveRegionMode.Polite }
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Icon(
            painter = painterResource(if (isProblem) R.drawable.ic_ui_close else R.drawable.ic_ui_check),
            contentDescription = null,
            tint = colors.onPrimary,
            modifier = Modifier.size(20.dp),
        )
        Text(text = text, fontSize = 14.sp, color = colors.onPrimary, modifier = Modifier.weight(1f))
    }
}

@Composable
private fun CurrentItemPanel(
    item: StockDeliveryItem?,
    isScanned: Boolean,
    canCheck: Boolean,
    onTypeCode: () -> Unit,
    onSetQuantity: (StockDeliveryItem) -> Unit,
    onDone: () -> Unit,
) {
    val colors = LocalAikuColors.current

    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(topStart = 16.dp, topEnd = 16.dp))
            .background(colors.surface)
            .navigationBarsPadding()
            .padding(start = 16.dp, end = 16.dp, top = 16.dp, bottom = 28.dp),
    ) {
        Box(
            modifier = Modifier
                .align(Alignment.CenterHorizontally)
                .size(width = 40.dp, height = 4.dp)
                .clip(CircleShape)
                .background(colors.borderStrong),
        )
        if (item != null) {
            Text(
                text = stringResource(if (isScanned) R.string.goodsin_camera_scanned else R.string.goodsin_camera_next),
                fontSize = 12.sp,
                fontWeight = FontWeight.SemiBold,
                color = colors.textTertiary,
            )
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                ProductPhoto(code = item.code, imageUrl = item.sko?.imageUrl)
                Column(modifier = Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                    Text(text = item.code, fontSize = 16.sp, fontWeight = FontWeight.Bold, color = colors.text)
                    Text(text = item.name, fontSize = 13.sp, color = colors.textTertiary)
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text(
                        text = buildAnnotatedString {
                            append(quantityText(item.skoChecked ?: 0.0, item.skoCheckedLabel))
                            withStyle(SpanStyle(fontSize = 14.sp, fontWeight = FontWeight.Medium, color = colors.textTertiary)) {
                                append(" / ")
                                append(quantityText(item.skoExpected, item.skoExpectedLabel))
                            }
                        },
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold,
                        color = colors.text,
                    )
                    Text(text = stringResource(R.string.goodsin_camera_checked), fontSize = 11.sp, color = colors.textTertiary)
                }
            }
            if (item.skoExpected > 0.0) {
                ProgressBar(fraction = ((item.skoChecked ?: 0.0) / item.skoExpected).toFloat().coerceIn(0f, 1f))
            }
        } else {
            Text(text = stringResource(R.string.goodsin_camera_all_checked), fontSize = 14.sp, color = colors.textSecondary)
        }
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp), modifier = Modifier.fillMaxWidth()) {
            PanelButton(
                text = stringResource(R.string.goodsin_type_code),
                icon = R.drawable.ic_ui_keyboard,
                container = Color.Transparent,
                content = colors.textSecondary,
                borderColor = colors.borderStrong,
                onClick = onTypeCode,
                modifier = Modifier.weight(1f),
            )
            if (canCheck && item != null && isScanned) {
                PanelButton(
                    text = stringResource(R.string.goodsin_set_checked_quantity),
                    icon = null,
                    container = colors.soft,
                    content = colors.softInk,
                    borderColor = colors.softBorder,
                    onClick = { onSetQuantity(item) },
                    modifier = Modifier.weight(1f),
                )
            }
        }
        AikuButton(text = stringResource(R.string.goodsin_done_back), onClick = onDone, icon = R.drawable.ic_ui_check, height = 52.dp, modifier = Modifier.fillMaxWidth())
    }
}

@Composable
private fun PanelButton(
    text: String,
    @DrawableRes icon: Int?,
    container: Color,
    content: Color,
    borderColor: Color,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(space = 8.dp, alignment = Alignment.CenterHorizontally),
        modifier = modifier
            .heightIn(min = 48.dp)
            .clip(ControlShape)
            .background(container)
            .border(width = 1.dp, color = borderColor, shape = ControlShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 10.dp, vertical = 6.dp),
    ) {
        if (icon != null) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = content, modifier = Modifier.size(18.dp))
        }
        Text(text = text, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = content, textAlign = TextAlign.Center)
    }
}

@Composable
private fun TypeCodeSheet(onFind: (String) -> Unit, onDismiss: () -> Unit) {
    var code by remember { mutableStateOf("") }

    AikuBottomSheet(title = stringResource(R.string.goodsin_type_code), subtitle = stringResource(R.string.goodsin_type_code_body), onDismiss = onDismiss) {
        SheetTextField(value = code, onValueChange = { code = it }, label = stringResource(R.string.goodsin_type_code_label))
        SheetButtons(
            confirmLabel = stringResource(R.string.goodsin_type_code_find),
            confirmLook = ButtonLook(ButtonStyle.Primary, R.drawable.ic_ui_search),
            onConfirm = { code.trim().takeIf { it.isNotEmpty() }?.let(onFind) },
            onCancel = onDismiss,
        )
    }
}
