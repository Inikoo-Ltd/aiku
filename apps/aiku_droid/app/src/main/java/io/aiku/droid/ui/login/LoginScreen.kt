package io.aiku.droid.ui.login

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsFocusedAsState
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.systemBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.ripple
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.ContentType
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.geometry.CornerRadius
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentType
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import io.aiku.droid.R
import io.aiku.droid.ui.UiMessage
import io.aiku.droid.ui.asText
import io.aiku.droid.ui.theme.AikuColors
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.Bitter
import io.aiku.droid.ui.theme.Caveat
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors

private val CardShape = RoundedCornerShape(12.dp)
private val ControlShape = RoundedCornerShape(6.dp)
private val ControlHeight = 48.dp

private const val AIKU_EMAIL_LINK = "mailto:hello@aiku.io"

@Composable
fun LoginScreen(
    state: LoginUiState,
    actions: LoginActions,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current

    Box(modifier = modifier.fillMaxSize().background(colors.background)) {
        Image(
            painter = painterResource(if (colors.isDark) R.drawable.login_background_dark else R.drawable.login_background),
            contentDescription = null,
            contentScale = ContentScale.Crop,
            modifier = Modifier.fillMaxSize(),
        )
        BoxWithConstraints(modifier = Modifier.fillMaxSize().systemBarsPadding().imePadding()) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .heightIn(min = maxHeight),
                verticalArrangement = Arrangement.SpaceBetween,
            ) {
                BrandHeader(modifier = Modifier.padding(start = 24.dp, end = 24.dp, top = 16.dp))
                LoginCard(
                    state = state,
                    actions = actions,
                    modifier = Modifier
                        .align(Alignment.CenterHorizontally)
                        .widthIn(max = 448.dp)
                        .padding(horizontal = 16.dp, vertical = 24.dp),
                )
                Footer(modifier = Modifier.fillMaxWidth().padding(start = 24.dp, end = 24.dp, top = 12.dp, bottom = 20.dp))
            }
        }
    }
}

@Composable
private fun BrandHeader(modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current

    Column(modifier = modifier, verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(
                painter = painterResource(R.drawable.aiku_invader),
                contentDescription = stringResource(R.string.brand_name),
                tint = Color.Unspecified,
                modifier = Modifier.size(40.dp),
            )
            Spacer(modifier = Modifier.width(12.dp))
            Text(
                text = stringResource(R.string.brand_name),
                fontFamily = Bitter,
                fontWeight = FontWeight.Bold,
                fontSize = 34.sp,
                lineHeight = 34.sp,
                letterSpacing = (-0.01).em,
                color = colors.text,
            )
        }
        Text(
            text = stringResource(R.string.login_tagline),
            fontFamily = Caveat,
            fontWeight = FontWeight.Medium,
            fontSize = 22.sp,
            lineHeight = 26.sp,
            color = colors.tagline,
        )
    }
}

@Composable
private fun LoginCard(
    state: LoginUiState,
    actions: LoginActions,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current

    Column(
        modifier = modifier
            .fillMaxWidth()
            .shadow(elevation = 12.dp, shape = CardShape, ambientColor = colors.shadow, spotColor = colors.shadow)
            .background(colors.surface, CardShape)
            .border(1.dp, colors.border, CardShape)
            .padding(horizontal = 20.dp, vertical = 24.dp),
        verticalArrangement = Arrangement.spacedBy(18.dp),
    ) {
        state.alert?.let { AlertBox(it) }
        when (state.step) {
            LoginStep.Credentials -> CredentialsStep(state, actions)
            LoginStep.OneTimePassword -> OneTimePasswordStep(state, actions)
            LoginStep.PasswordReset -> PasswordResetStep(state, actions)
            LoginStep.PasswordResetSent -> PasswordResetSentStep(actions)
        }
    }
}

@Composable
private fun CredentialsStep(state: LoginUiState, actions: LoginActions) {
    val colors = LocalAikuColors.current
    val keyboard = LocalSoftwareKeyboardController.current
    val passwordFocus = remember { FocusRequester() }
    val isEnabled = !state.isSubmitting

    LaunchedEffect(state.passwordFocusRequest) {
        if (state.passwordFocusRequest > 0) {
            passwordFocus.requestFocus()
        }
    }

    LabelledField(label = stringResource(R.string.login_username), isEnabled = isEnabled, error = state.usernameError) {
        AikuInput(
            value = state.username,
            onValueChange = actions::onUsernameChange,
            placeholder = stringResource(R.string.login_username),
            isError = state.usernameError != null,
            isEnabled = isEnabled,
            keyboardOptions = KeyboardOptions(
                capitalization = KeyboardCapitalization.None,
                autoCorrectEnabled = false,
                keyboardType = KeyboardType.Text,
                imeAction = ImeAction.Next,
            ),
            modifier = Modifier.semantics { contentType = ContentType.Username },
        )
    }

    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        LabelledField(label = stringResource(R.string.login_password), isEnabled = isEnabled, error = state.passwordError) {
            AikuInput(
                value = state.password,
                onValueChange = actions::onPasswordChange,
                placeholder = stringResource(R.string.login_password_placeholder),
                isError = state.passwordError != null,
                isEnabled = isEnabled,
                visualTransformation = if (state.isPasswordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                keyboardOptions = KeyboardOptions(
                    autoCorrectEnabled = false,
                    keyboardType = KeyboardType.Password,
                    imeAction = ImeAction.Done,
                ),
                keyboardActions = KeyboardActions(onDone = {
                    keyboard?.hide()
                    actions.signIn()
                }),
                trailing = {
                    PasswordVisibilityToggle(
                        isVisible = state.isPasswordVisible,
                        isEnabled = isEnabled,
                        onToggle = actions::onPasswordVisibilityToggle,
                    )
                },
                modifier = Modifier
                    .focusRequester(passwordFocus)
                    .semantics { contentType = ContentType.Password },
            )
        }

        if (isEnabled) {
            Text(
                text = stringResource(R.string.login_forgot_password),
                fontSize = 13.sp,
                fontStyle = FontStyle.Italic,
                color = colors.textTertiary,
                modifier = Modifier
                    .heightIn(min = 32.dp)
                    .clickable(role = Role.Button, onClick = actions::openPasswordReset)
                    .padding(vertical = 7.dp),
            )

            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .heightIn(min = 44.dp)
                    .toggleable(value = state.rememberMe, role = Role.Checkbox, onValueChange = actions::onRememberMeChange),
            ) {
                Checkbox(
                    checked = state.rememberMe,
                    onCheckedChange = null,
                    colors = CheckboxDefaults.colors(
                        checkedColor = colors.primary,
                        uncheckedColor = colors.borderStrong,
                        checkmarkColor = colors.onPrimary,
                    ),
                )
                Spacer(modifier = Modifier.width(10.dp))
                Text(text = stringResource(R.string.login_remember_me), fontSize = 14.sp, color = colors.textSecondary)
            }
        }
    }

    PrimaryButton(
        text = stringResource(
            when {
                state.isSubmitting -> R.string.login_signing_in
                state.alert != null -> R.string.login_try_again
                else -> R.string.login_sign_in
            },
        ),
        isLoading = state.isSubmitting,
        onClick = {
            keyboard?.hide()
            actions.signIn()
        },
    )
}

@Composable
private fun OneTimePasswordStep(state: LoginUiState, actions: LoginActions) {
    val keyboard = LocalSoftwareKeyboardController.current
    val codeFocus = remember { FocusRequester() }
    val isEnabled = !state.isSubmitting

    LaunchedEffect(Unit) { codeFocus.requestFocus() }

    StepHeading(
        iconResource = R.drawable.ic_shield_check,
        title = stringResource(R.string.otp_title),
        description = stringResource(R.string.otp_description, state.username.trim()),
    )

    LabelledField(label = stringResource(R.string.otp_label), isEnabled = isEnabled, error = state.oneTimePasswordError) {
        AikuInput(
            value = state.oneTimePassword,
            onValueChange = actions::onOneTimePasswordChange,
            placeholder = stringResource(R.string.otp_placeholder),
            isError = state.oneTimePasswordError != null,
            isEnabled = isEnabled,
            textStyle = TextStyle(fontFamily = Inter, fontSize = 20.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 0.3.em),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword, imeAction = ImeAction.Done),
            keyboardActions = KeyboardActions(onDone = {
                keyboard?.hide()
                actions.verifyOneTimePassword()
            }),
            modifier = Modifier
                .focusRequester(codeFocus)
                .semantics { contentType = ContentType.SmsOtpCode },
        )
    }

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        PrimaryButton(
            text = stringResource(if (state.isSubmitting) R.string.otp_verifying else R.string.otp_verify),
            isLoading = state.isSubmitting,
            onClick = {
                keyboard?.hide()
                actions.verifyOneTimePassword()
            },
        )
        TertiaryButton(text = stringResource(R.string.back_to_sign_in), onClick = actions::backToCredentials)
    }
}

@Composable
private fun PasswordResetStep(state: LoginUiState, actions: LoginActions) {
    val keyboard = LocalSoftwareKeyboardController.current
    val emailFocus = remember { FocusRequester() }
    val isEnabled = !state.isSubmitting

    LaunchedEffect(Unit) { emailFocus.requestFocus() }

    BackLink(onClick = actions::backToCredentials)

    StepHeading(
        iconResource = null,
        title = stringResource(R.string.reset_title),
        description = stringResource(R.string.reset_description),
    )

    LabelledField(label = stringResource(R.string.reset_email_label), isEnabled = isEnabled, error = state.resetEmailError) {
        AikuInput(
            value = state.resetEmail,
            onValueChange = actions::onResetEmailChange,
            placeholder = stringResource(R.string.reset_email_placeholder),
            isError = state.resetEmailError != null,
            isEnabled = isEnabled,
            keyboardOptions = KeyboardOptions(
                autoCorrectEnabled = false,
                keyboardType = KeyboardType.Email,
                imeAction = ImeAction.Send,
            ),
            keyboardActions = KeyboardActions(onSend = {
                keyboard?.hide()
                actions.sendPasswordResetLink()
            }),
            modifier = Modifier
                .focusRequester(emailFocus)
                .semantics { contentType = ContentType.EmailAddress },
        )
    }

    PrimaryButton(
        text = stringResource(if (state.isSubmitting) R.string.reset_sending else R.string.reset_send),
        isLoading = state.isSubmitting,
        onClick = {
            keyboard?.hide()
            actions.sendPasswordResetLink()
        },
    )

    ResetNote()
}

@Composable
private fun PasswordResetSentStep(actions: LoginActions) {
    val colors = LocalAikuColors.current

    BackLink(onClick = actions::backToCredentials)

    StepHeading(
        iconResource = null,
        title = stringResource(R.string.reset_title),
        description = stringResource(R.string.reset_description),
    )

    Row(
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.successSoft, ControlShape)
            .padding(12.dp)
            .semantics { liveRegion = LiveRegionMode.Polite },
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_circle_check),
            contentDescription = null,
            tint = colors.success,
            modifier = Modifier.size(20.dp),
        )
        Text(text = stringResource(R.string.reset_sent_description), fontSize = 13.sp, lineHeight = 18.sp, color = colors.success)
    }

    TertiaryButton(text = stringResource(R.string.back_to_sign_in), onClick = actions::backToCredentials)

    ResetNote()
}

@Composable
private fun ResetNote() {
    Text(
        text = stringResource(R.string.reset_no_email),
        fontSize = 13.sp,
        color = LocalAikuColors.current.textTertiary,
        textAlign = TextAlign.Center,
        modifier = Modifier.fillMaxWidth(),
    )
}

@Composable
private fun StepHeading(iconResource: Int?, title: String, description: String) {
    val colors = LocalAikuColors.current

    Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        if (iconResource != null) {
            Icon(
                painter = painterResource(iconResource),
                contentDescription = null,
                tint = colors.link,
                modifier = Modifier.size(24.dp),
            )
        }
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, fontSize = 17.sp, lineHeight = 24.sp, fontWeight = FontWeight.Bold, color = colors.text)
            Text(text = description, fontSize = 13.sp, lineHeight = 18.sp, color = colors.textTertiary)
        }
    }
}

@Composable
private fun BackLink(onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .heightIn(min = 44.dp)
            .clip(ControlShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(end = 8.dp),
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_chevron_left),
            contentDescription = null,
            tint = colors.textTertiary,
            modifier = Modifier.size(20.dp),
        )
        Spacer(modifier = Modifier.width(4.dp))
        Text(text = stringResource(R.string.back_to_sign_in), fontSize = 13.sp, fontWeight = FontWeight.Medium, color = colors.textTertiary)
    }
}

@Composable
private fun LabelledField(
    label: String,
    isEnabled: Boolean,
    error: UiMessage?,
    field: @Composable () -> Unit,
) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            text = label,
            fontSize = 14.sp,
            fontWeight = FontWeight.Medium,
            color = if (isEnabled) colors.textSecondary else colors.textQuaternary,
        )
        field()
        if (error != null) {
            FieldError(error)
        }
    }
}

@Composable
private fun FieldError(message: UiMessage) {
    val colors = LocalAikuColors.current

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        modifier = Modifier.semantics { liveRegion = LiveRegionMode.Polite },
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_alert_circle),
            contentDescription = null,
            tint = colors.danger,
            modifier = Modifier.size(16.dp),
        )
        Text(text = message.asText(), fontSize = 13.sp, color = colors.danger)
    }
}

@Composable
private fun AlertBox(alert: LoginAlert) {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.dangerSoft, ControlShape)
            .border(1.dp, colors.dangerBorder, ControlShape)
            .padding(12.dp)
            .semantics { liveRegion = LiveRegionMode.Assertive },
    ) {
        Icon(
            painter = painterResource(if (alert.isOffline) R.drawable.ic_wifi_off else R.drawable.ic_alert_triangle),
            contentDescription = null,
            tint = colors.danger,
            modifier = Modifier.padding(top = 1.dp).size(20.dp),
        )
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = alert.title.asText(), fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = colors.dangerInk)
            alert.body?.let { Text(text = it.asText(), fontSize = 13.sp, lineHeight = 18.sp, color = colors.danger) }
        }
    }
}

@Composable
private fun AikuInput(
    value: String,
    onValueChange: (String) -> Unit,
    placeholder: String,
    isError: Boolean,
    isEnabled: Boolean,
    modifier: Modifier = Modifier,
    textStyle: TextStyle = TextStyle(fontFamily = Inter, fontSize = 16.sp),
    keyboardOptions: KeyboardOptions = KeyboardOptions.Default,
    keyboardActions: KeyboardActions = KeyboardActions.Default,
    visualTransformation: VisualTransformation = VisualTransformation.None,
    trailing: (@Composable () -> Unit)? = null,
) {
    val colors = LocalAikuColors.current
    val interactionSource = remember { MutableInteractionSource() }
    val isFocused by interactionSource.collectIsFocusedAsState()
    val borderColor = when {
        !isEnabled -> colors.border
        isError -> colors.danger
        isFocused -> colors.primary
        else -> colors.borderStrong
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .height(ControlHeight)
            .focusRing(isVisible = isFocused && isEnabled, colors = colors)
            .shadow(elevation = 1.dp, shape = ControlShape, ambientColor = colors.shadow, spotColor = colors.shadow)
            .background(if (isEnabled) colors.surface else colors.surfaceMuted, ControlShape)
            .border(1.dp, borderColor, ControlShape)
            .clip(ControlShape),
    ) {
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            enabled = isEnabled,
            singleLine = true,
            textStyle = textStyle.copy(color = if (isEnabled) colors.text else colors.textQuaternary),
            cursorBrush = SolidColor(colors.primary),
            keyboardOptions = keyboardOptions,
            keyboardActions = keyboardActions,
            visualTransformation = visualTransformation,
            interactionSource = interactionSource,
            modifier = modifier.weight(1f).padding(horizontal = 12.dp),
            decorationBox = { innerTextField ->
                Box(contentAlignment = Alignment.CenterStart) {
                    if (value.isEmpty()) {
                        Text(text = placeholder, style = textStyle.copy(color = colors.faint))
                    }
                    innerTextField()
                }
            },
        )
        trailing?.invoke()
    }
}

private fun Modifier.focusRing(isVisible: Boolean, colors: AikuColors, width: Dp = 3.dp): Modifier =
    if (!isVisible) {
        this
    } else {
        drawBehind {
            val ringWidth = width.toPx()
            drawRoundRect(
                color = colors.primary.copy(alpha = 0.25f),
                topLeft = Offset(-ringWidth, -ringWidth),
                size = Size(size.width + ringWidth * 2, size.height + ringWidth * 2),
                cornerRadius = CornerRadius(6.dp.toPx() + ringWidth),
            )
        }
    }

@Composable
private fun PasswordVisibilityToggle(isVisible: Boolean, isEnabled: Boolean, onToggle: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .width(ControlHeight)
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
private fun PrimaryButton(text: String, isLoading: Boolean, onClick: () -> Unit) {
    val colors = LocalAikuColors.current
    val interactionSource = remember { MutableInteractionSource() }
    val isPressed by interactionSource.collectIsPressedAsState()

    Row(
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .height(ControlHeight)
            .alpha(if (isLoading) 0.85f else 1f)
            .clip(ControlShape)
            .background(if (isPressed) colors.primaryPressed else colors.primary)
            .border(1.dp, colors.primaryBorder, ControlShape)
            .clickable(
                interactionSource = interactionSource,
                indication = ripple(color = colors.onPrimary),
                enabled = !isLoading,
                role = Role.Button,
                onClick = onClick,
            ),
    ) {
        if (isLoading) {
            CircularProgressIndicator(
                color = colors.onPrimary,
                trackColor = colors.onPrimary.copy(alpha = 0.35f),
                strokeWidth = 3.dp,
                modifier = Modifier.size(20.dp),
            )
            Spacer(modifier = Modifier.width(10.dp))
        }
        Text(text = text, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.onPrimary)
    }
}

@Composable
private fun TertiaryButton(text: String, onClick: () -> Unit) {
    val colors = LocalAikuColors.current

    Box(
        contentAlignment = Alignment.Center,
        modifier = Modifier
            .fillMaxWidth()
            .height(ControlHeight)
            .clip(ControlShape)
            .border(1.dp, colors.borderStrong, ControlShape)
            .clickable(role = Role.Button, onClick = onClick),
    ) {
        Text(text = text, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = colors.textSecondary)
    }
}

@Composable
private fun Footer(modifier: Modifier = Modifier) {
    val colors = LocalAikuColors.current
    val uriHandler = LocalUriHandler.current

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(4.dp),
        modifier = modifier,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(
                painter = painterResource(R.drawable.aiku_invader),
                contentDescription = null,
                tint = Color.Unspecified,
                modifier = Modifier.size(16.dp),
            )
            Text(text = stringResource(R.string.footer_open_source), fontSize = 12.sp, color = colors.textTertiary)
        }
        FooterLink(text = stringResource(R.string.footer_email)) { uriHandler.openUri(AIKU_EMAIL_LINK) }
    }
}

@Composable
private fun FooterLink(text: String, onClick: () -> Unit) {
    Text(
        text = text,
        fontSize = 12.sp,
        color = LocalAikuColors.current.textTertiary,
        modifier = Modifier
            .clip(ControlShape)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(horizontal = 4.dp, vertical = 8.dp),
    )
}

private object PreviewLoginActions : LoginActions {
    override fun onUsernameChange(value: String) = Unit
    override fun onPasswordChange(value: String) = Unit
    override fun onPasswordVisibilityToggle() = Unit
    override fun onRememberMeChange(value: Boolean) = Unit
    override fun onOneTimePasswordChange(value: String) = Unit
    override fun onResetEmailChange(value: String) = Unit
    override fun signIn() = Unit
    override fun verifyOneTimePassword() = Unit
    override fun openPasswordReset() = Unit
    override fun sendPasswordResetLink() = Unit
    override fun backToCredentials() = Unit
}

@Preview(showSystemUi = true)
@Composable
private fun CredentialsPreview() {
    AikuDroidTheme {
        LoginScreen(state = LoginUiState(username = "alex.morgan"), actions = PreviewLoginActions)
    }
}

@Preview(showSystemUi = true)
@Composable
private fun RejectedPreview() {
    AikuDroidTheme {
        LoginScreen(
            state = LoginUiState(
                username = "alex.morgan",
                alert = LoginAlert(
                    title = UiMessage.FromResource(R.string.login_failed_title),
                    body = UiMessage.FromServer("These credentials do not match our records."),
                ),
            ),
            actions = PreviewLoginActions,
        )
    }
}

@Preview(showSystemUi = true)
@Composable
private fun OneTimePasswordPreview() {
    AikuDroidTheme {
        LoginScreen(
            state = LoginUiState(step = LoginStep.OneTimePassword, username = "alex.morgan", password = "secret"),
            actions = PreviewLoginActions,
        )
    }
}
