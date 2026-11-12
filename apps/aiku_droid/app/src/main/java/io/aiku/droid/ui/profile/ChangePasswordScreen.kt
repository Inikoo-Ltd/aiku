package io.aiku.droid.ui.profile

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.ContentType
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.contentType
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.UiMessage
import io.aiku.droid.ui.asText
import io.aiku.droid.ui.components.AikuButton
import io.aiku.droid.ui.components.AlertTone
import io.aiku.droid.ui.components.AppHeader
import io.aiku.droid.ui.components.CardShape
import io.aiku.droid.ui.components.InlineAlert
import io.aiku.droid.ui.components.LocalToasts
import io.aiku.droid.ui.components.animateFocusProgress
import io.aiku.droid.ui.components.focusHalo
import io.aiku.droid.ui.navigation.LocalAppNavigator
import io.aiku.droid.ui.states.SessionExpiredScreen
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

private val PasswordFieldShape = RoundedCornerShape(6.dp)
private val PasswordFieldHeight = 48.dp

@Composable
fun ChangePasswordScreen(state: ChangePasswordUiState, actions: ChangePasswordActions) {
    val colors = LocalAikuColors.current
    val navigator = LocalAppNavigator.current
    val toasts = LocalToasts.current
    val keyboard = LocalSoftwareKeyboardController.current
    val changedMessage = stringResource(R.string.profile_password_changed)
    val isEnabled = !state.isSubmitting
    val submit = {
        keyboard?.hide()
        actions.changePassword()
    }

    LaunchedEffect(state.isChanged) {
        if (state.isChanged) {
            toasts.show(changedMessage)
            navigator.back()
        }
    }

    if (state.isSessionExpired) {
        SessionExpiredScreen(onSignIn = actions::signInAgain)
        return
    }

    Column(modifier = Modifier.fillMaxSize().background(colors.background).imePadding()) {
        AppHeader(
            title = stringResource(R.string.profile_change_password),
            kicker = stringResource(R.string.profile_title),
        )
        Column(
            verticalArrangement = Arrangement.spacedBy(14.dp),
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
        ) {
            Text(
                text = stringResource(R.string.profile_password_intro),
                fontSize = 13.sp,
                lineHeight = 19.sp,
                color = colors.textTertiary,
                modifier = Modifier.padding(horizontal = 4.dp),
            )
            state.alert?.let { alert ->
                InlineAlert(title = alert.title.asText(), body = alert.body?.asText(), tone = AlertTone.Danger)
            }
            Column(
                verticalArrangement = Arrangement.spacedBy(16.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(CardShape)
                    .background(colors.surface)
                    .border(1.dp, colors.border, CardShape)
                    .padding(16.dp),
            ) {
                PasswordField(
                    label = stringResource(R.string.profile_password_current),
                    value = state.currentPassword,
                    onValueChange = actions::onCurrentPasswordChange,
                    isVisible = state.isCurrentPasswordVisible,
                    onToggleVisibility = actions::onCurrentPasswordVisibilityToggle,
                    error = state.currentPasswordError,
                    isEnabled = isEnabled,
                    autofill = ContentType.Password,
                    imeAction = ImeAction.Next,
                )
                PasswordField(
                    label = stringResource(R.string.password_new),
                    value = state.newPassword,
                    onValueChange = actions::onNewPasswordChange,
                    isVisible = state.isNewPasswordVisible,
                    onToggleVisibility = actions::onNewPasswordVisibilityToggle,
                    error = state.newPasswordError,
                    isEnabled = isEnabled,
                    autofill = ContentType.NewPassword,
                    imeAction = ImeAction.Next,
                )
                PasswordField(
                    label = stringResource(R.string.password_confirm),
                    value = state.confirmation,
                    onValueChange = actions::onConfirmationChange,
                    isVisible = state.isConfirmationVisible,
                    onToggleVisibility = actions::onConfirmationVisibilityToggle,
                    error = state.confirmationError,
                    isEnabled = isEnabled,
                    autofill = ContentType.NewPassword,
                    imeAction = ImeAction.Done,
                    keyboardActions = KeyboardActions(onDone = { submit() }),
                )
            }
        }
        Column(modifier = Modifier.fillMaxWidth().background(colors.surface)) {
            HorizontalDivider(thickness = 1.dp, color = colors.border)
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .navigationBarsPadding()
                    .padding(horizontal = 16.dp, vertical = 12.dp),
            ) {
                AikuButton(
                    text = stringResource(if (state.isSubmitting) R.string.profile_password_saving else R.string.profile_change_password),
                    onClick = submit,
                    isLoading = state.isSubmitting,
                    height = 52.dp,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }
    }
}

@Composable
private fun PasswordField(
    label: String,
    value: String,
    onValueChange: (String) -> Unit,
    isVisible: Boolean,
    onToggleVisibility: () -> Unit,
    error: UiMessage?,
    isEnabled: Boolean,
    autofill: ContentType,
    imeAction: ImeAction,
    keyboardActions: KeyboardActions = KeyboardActions.Default,
) {
    val colors = LocalAikuColors.current
    val interactionSource = remember { MutableInteractionSource() }
    val isFocused by interactionSource.collectIsFocusedAsState()
    val focusProgress = animateFocusProgress(isFocused && isEnabled)
    val borderColor = when {
        !isEnabled -> colors.border
        error != null -> colors.danger
        else -> lerp(colors.borderStrong, colors.focus, focusProgress)
    }

    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            text = label,
            fontSize = 14.sp,
            fontWeight = FontWeight.Medium,
            color = if (isEnabled) colors.textSecondary else colors.textQuaternary,
        )
        Row(
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier
                .fillMaxWidth()
                .height(PasswordFieldHeight)
                .focusHalo(progress = focusProgress, color = colors.focus, cornerRadius = 6.dp)
                .clip(PasswordFieldShape)
                .background(if (isEnabled) colors.surface else colors.surfaceMuted)
                .border(1.dp, borderColor, PasswordFieldShape),
        ) {
            BasicTextField(
                value = value,
                onValueChange = onValueChange,
                enabled = isEnabled,
                singleLine = true,
                textStyle = TextStyle(fontFamily = Inter, fontSize = 16.sp, color = if (isEnabled) colors.text else colors.textQuaternary),
                cursorBrush = SolidColor(colors.primary),
                keyboardOptions = KeyboardOptions(autoCorrectEnabled = false, keyboardType = KeyboardType.Password, imeAction = imeAction),
                keyboardActions = keyboardActions,
                visualTransformation = if (isVisible) VisualTransformation.None else PasswordVisualTransformation(),
                interactionSource = interactionSource,
                modifier = Modifier
                    .weight(1f)
                    .padding(horizontal = 12.dp)
                    .semantics {
                        contentDescription = label
                        contentType = autofill
                    },
            )
            VisibilityToggle(isVisible = isVisible, isEnabled = isEnabled, onToggle = onToggleVisibility)
        }
        if (error != null) {
            FieldError(message = error)
        }
    }
}

@Composable
private fun VisibilityToggle(isVisible: Boolean, isEnabled: Boolean, onToggle: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .width(PasswordFieldHeight)
            .fillMaxHeight()
            .background(colors.surfaceMuted)
            .drawBehind { drawLine(colors.borderStrong, Offset(0f, 0f), Offset(0f, size.height), strokeWidth = 1.dp.toPx()) }
            .clickable(enabled = isEnabled, role = Role.Button, onClick = onToggle),
    ) {
        Icon(
            painter = painterResource(if (isVisible) R.drawable.ic_eye_off else R.drawable.ic_eye),
            contentDescription = stringResource(if (isVisible) R.string.login_hide_password else R.string.login_show_password),
            tint = colors.textQuaternary,
            modifier = Modifier.size(20.dp),
        )
    }
}

@Composable
private fun FieldError(message: UiMessage) {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier.semantics { liveRegion = LiveRegionMode.Polite },
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_alert_circle),
            contentDescription = null,
            tint = colors.danger,
            modifier = Modifier.padding(top = 1.dp).size(16.dp),
        )
        Text(text = message.asText(), fontSize = 13.sp, lineHeight = 18.sp, color = colors.danger)
    }
}

private object PreviewChangePasswordActions : ChangePasswordActions {
    override fun onCurrentPasswordChange(value: String) = Unit
    override fun onNewPasswordChange(value: String) = Unit
    override fun onConfirmationChange(value: String) = Unit
    override fun onCurrentPasswordVisibilityToggle() = Unit
    override fun onNewPasswordVisibilityToggle() = Unit
    override fun onConfirmationVisibilityToggle() = Unit
    override fun changePassword() = Unit
    override fun signInAgain() = Unit
}

@Preview
@Composable
private fun ChangePasswordScreenPreview() {
    AikuDroidTheme {
        ChangePasswordScreen(state = ChangePasswordUiState(), actions = PreviewChangePasswordActions)
    }
}

@Preview
@Composable
private fun ChangePasswordRejectedPreview() {
    AikuDroidTheme {
        ChangePasswordScreen(
            state = ChangePasswordUiState(
                currentPassword = "secret",
                newPassword = "short",
                confirmation = "short",
                currentPasswordError = UiMessage.FromServer("The current password is incorrect."),
                newPasswordError = UiMessage.FromServer("The password field must be at least 8 characters."),
            ),
            actions = PreviewChangePasswordActions,
        )
    }
}
