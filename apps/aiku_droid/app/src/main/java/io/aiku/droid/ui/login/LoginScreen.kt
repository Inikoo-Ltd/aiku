package io.aiku.droid.ui.login

import android.content.ActivityNotFoundException
import android.content.ClipData
import android.content.ClipDescription
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.PersistableBundle
import androidx.activity.compose.BackHandler
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
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.ContentType
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.lerp
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.ClipEntry
import androidx.compose.ui.platform.LocalClipboard
import androidx.compose.ui.platform.LocalContext
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
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import androidx.core.net.toUri
import io.aiku.droid.R
import io.aiku.droid.data.api.TwoFactorSetup
import io.aiku.droid.ui.UiMessage
import io.aiku.droid.ui.asText
import io.aiku.droid.ui.components.AikuFocusIndication
import io.aiku.droid.ui.components.QrCode
import io.aiku.droid.ui.components.animateFocusProgress
import io.aiku.droid.ui.components.focusHalo
import io.aiku.droid.ui.components.qrMatrixOf
import io.aiku.droid.ui.theme.AikuDroidTheme
import io.aiku.droid.ui.theme.Bitter
import io.aiku.droid.ui.theme.Caveat
import io.aiku.droid.ui.theme.DeepSeaLight
import io.aiku.droid.ui.theme.Inter
import io.aiku.droid.ui.theme.LocalAikuColors
import kotlinx.coroutines.launch

private val CardShape = RoundedCornerShape(12.dp)
private val ControlShape = RoundedCornerShape(6.dp)
private val ControlHeight = 48.dp
private val LinkHeight = 44.dp
private val CodeTextStyle = TextStyle(fontFamily = Inter, fontSize = 20.sp, fontWeight = FontWeight.SemiBold, letterSpacing = 0.3.em)

private const val AIKU_EMAIL_LINK = "mailto:hello@aiku.io"
private const val SETUP_KEY_GROUP = 4
private const val SECONDS_PER_MINUTE = 60

@Composable
fun LoginScreen(
    state: LoginUiState,
    actions: LoginActions,
    modifier: Modifier = Modifier,
) {
    val colors = LocalAikuColors.current

    BackHandler(enabled = state.step != LoginStep.Credentials, onBack = actions::backToCredentials)

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
        state.notice?.let { notice ->
            NoticeBox(
                message = notice,
                hint = stringResource(R.string.reset_no_email_hint).takeIf { state.step == LoginStep.PasswordResetCode },
            )
        }
        when (state.step) {
            LoginStep.Credentials -> CredentialsStep(state, actions)
            LoginStep.OneTimePassword -> OneTimePasswordStep(state, actions)
            LoginStep.TwoFactorSetup -> TwoFactorSetupStep(state, actions)
            LoginStep.PasswordReset -> PasswordResetStep(state, actions)
            LoginStep.PasswordResetCode -> PasswordResetCodeStep(state, actions)
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
        OneTimeCodeInput(
            value = state.oneTimePassword,
            onValueChange = actions::onOneTimePasswordChange,
            isError = state.oneTimePasswordError != null,
            isEnabled = isEnabled,
            imeAction = ImeAction.Done,
            keyboardActions = KeyboardActions(onDone = {
                keyboard?.hide()
                actions.verifyOneTimePassword()
            }),
            modifier = Modifier.focusRequester(codeFocus),
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
private fun TwoFactorSetupStep(state: LoginUiState, actions: LoginActions) {
    val setup = state.twoFactorSetup ?: return
    val keyboard = LocalSoftwareKeyboardController.current
    val context = LocalContext.current
    val isEnabled = !state.isSubmitting
    var isAuthenticatorMissing by remember(setup.otpauthUrl) { mutableStateOf(false) }

    StepHeading(
        iconResource = R.drawable.ic_shield_check,
        title = stringResource(R.string.two_factor_title),
        description = stringResource(R.string.two_factor_description),
    )

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        TertiaryButton(
            text = stringResource(R.string.two_factor_open_app),
            iconResource = R.drawable.ic_shield_check,
            onClick = { isAuthenticatorMissing = !openAuthenticator(context, setup.otpauthUrl) },
        )
        if (isAuthenticatorMissing) {
            HintBox(text = stringResource(R.string.two_factor_no_app))
        }
    }

    SetupKeyPanel(setup = setup)

    LabelledField(label = stringResource(R.string.otp_label), isEnabled = isEnabled, error = state.oneTimePasswordError) {
        OneTimeCodeInput(
            value = state.oneTimePassword,
            onValueChange = actions::onOneTimePasswordChange,
            isError = state.oneTimePasswordError != null,
            isEnabled = isEnabled,
            imeAction = ImeAction.Done,
            keyboardActions = KeyboardActions(onDone = {
                keyboard?.hide()
                actions.verifyOneTimePassword()
            }),
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
private fun SetupKeyPanel(setup: TwoFactorSetup) {
    val colors = LocalAikuColors.current
    val matrix = remember(setup.otpauthUrl) { qrMatrixOf(setup.otpauthUrl) }

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.surfaceMuted, ControlShape)
            .border(1.dp, colors.border, ControlShape)
            .padding(14.dp),
    ) {
        Text(
            text = stringResource(R.string.two_factor_other_phone),
            fontSize = 13.sp,
            lineHeight = 18.sp,
            color = colors.textTertiary,
            textAlign = TextAlign.Center,
        )
        if (matrix != null) {
            QrCode(
                matrix = matrix,
                description = stringResource(R.string.two_factor_qr_description),
                ink = DeepSeaLight.text,
                paper = DeepSeaLight.surface,
                modifier = Modifier
                    .widthIn(max = 184.dp)
                    .fillMaxWidth(),
            )
        }
        Column(horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = stringResource(R.string.two_factor_key_label), fontSize = 12.sp, fontWeight = FontWeight.Medium, color = colors.textTertiary)
            Text(
                text = groupedSetupKey(setup.secret),
                fontFamily = Inter,
                fontSize = 16.sp,
                lineHeight = 24.sp,
                fontWeight = FontWeight.SemiBold,
                letterSpacing = 0.08.em,
                color = colors.text,
                textAlign = TextAlign.Center,
            )
        }
        CopyKeyButton(secret = setup.secret)
    }
}

@Composable
private fun CopyKeyButton(secret: String) {
    val colors = LocalAikuColors.current
    val clipboard = LocalClipboard.current
    val scope = rememberCoroutineScope()
    val clipLabel = stringResource(R.string.two_factor_key_clip_label)
    var isCopied by remember(secret) { mutableStateOf(false) }

    Row(
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .heightIn(min = LinkHeight)
            .clip(ControlShape)
            .background(colors.surface)
            .border(1.dp, colors.borderStrong, ControlShape)
            .clickable(role = Role.Button) {
                scope.launch {
                    clipboard.setClipEntry(ClipEntry(sensitiveClip(clipLabel, secret)))
                    isCopied = true
                }
            }
            .padding(horizontal = 14.dp),
    ) {
        Icon(
            painter = painterResource(if (isCopied) R.drawable.ic_ui_check else R.drawable.ic_ui_clipboard),
            contentDescription = null,
            tint = if (isCopied) colors.success else colors.textSecondary,
            modifier = Modifier.size(18.dp),
        )
        Text(
            text = stringResource(if (isCopied) R.string.two_factor_key_copied else R.string.two_factor_copy_key),
            fontSize = 14.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.textSecondary,
        )
    }
}

private fun groupedSetupKey(secret: String): String = secret.filterNot(Char::isWhitespace).chunked(SETUP_KEY_GROUP).joinToString(" ")

private fun sensitiveClip(label: String, text: String): ClipData = ClipData.newPlainText(label, text).apply {
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
        description.extras = PersistableBundle().apply { putBoolean(ClipDescription.EXTRA_IS_SENSITIVE, true) }
    }
}

private fun openAuthenticator(context: Context, otpauthUrl: String): Boolean = try {
    context.startActivity(Intent(Intent.ACTION_VIEW, otpauthUrl.toUri()))
    true
} catch (exception: ActivityNotFoundException) {
    false
}

@Composable
private fun PasswordResetStep(state: LoginUiState, actions: LoginActions) {
    val keyboard = LocalSoftwareKeyboardController.current
    val loginFocus = remember { FocusRequester() }
    val isEnabled = !state.isSubmitting

    LaunchedEffect(Unit) { loginFocus.requestFocus() }

    ResetHeading(title = stringResource(R.string.reset_title), description = stringResource(R.string.reset_description))

    LabelledField(label = stringResource(R.string.reset_login_label), isEnabled = isEnabled, error = state.resetLoginError) {
        AikuInput(
            value = state.resetLogin,
            onValueChange = actions::onResetLoginChange,
            placeholder = stringResource(R.string.reset_login_placeholder),
            isError = state.resetLoginError != null,
            isEnabled = isEnabled,
            keyboardOptions = KeyboardOptions(
                capitalization = KeyboardCapitalization.None,
                autoCorrectEnabled = false,
                keyboardType = KeyboardType.Email,
                imeAction = ImeAction.Send,
            ),
            keyboardActions = KeyboardActions(onSend = {
                keyboard?.hide()
                actions.sendPasswordResetCode()
            }),
            modifier = Modifier
                .focusRequester(loginFocus)
                .semantics { contentType = ContentType.Username + ContentType.EmailAddress },
        )
    }

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        PrimaryButton(
            text = stringResource(if (state.isSubmitting) R.string.reset_sending else R.string.reset_send),
            isLoading = state.isSubmitting,
            onClick = {
                keyboard?.hide()
                actions.sendPasswordResetCode()
            },
        )
        if (isEnabled) {
            RememberedItLink(onClick = actions::backToCredentials)
        }
    }
}

@Composable
private fun PasswordResetCodeStep(state: LoginUiState, actions: LoginActions) {
    val keyboard = LocalSoftwareKeyboardController.current
    val isEnabled = !state.isSubmitting
    val submit = {
        keyboard?.hide()
        actions.resetPassword()
    }

    ResetHeading(title = stringResource(R.string.reset_code_title), description = stringResource(R.string.reset_code_description))

    Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
        LabelledField(label = stringResource(R.string.reset_code_label), isEnabled = isEnabled, error = state.resetCodeError) {
            OneTimeCodeInput(
                value = state.resetCode,
                onValueChange = actions::onResetCodeChange,
                isError = state.resetCodeError != null,
                isEnabled = isEnabled,
                imeAction = ImeAction.Next,
            )
        }
        ResendCode(state = state, actions = actions)
    }

    LabelledField(label = stringResource(R.string.password_new), isEnabled = isEnabled, error = state.newPasswordError) {
        NewPasswordInput(
            value = state.newPassword,
            onValueChange = actions::onNewPasswordChange,
            isVisible = state.isNewPasswordVisible,
            onToggleVisibility = actions::onNewPasswordVisibilityToggle,
            isError = state.newPasswordError != null,
            isEnabled = isEnabled,
            imeAction = ImeAction.Next,
        )
    }

    LabelledField(label = stringResource(R.string.password_confirm), isEnabled = isEnabled, error = state.newPasswordConfirmationError) {
        NewPasswordInput(
            value = state.newPasswordConfirmation,
            onValueChange = actions::onNewPasswordConfirmationChange,
            isVisible = state.isNewPasswordConfirmationVisible,
            onToggleVisibility = actions::onNewPasswordConfirmationVisibilityToggle,
            isError = state.newPasswordConfirmationError != null,
            isEnabled = isEnabled,
            imeAction = ImeAction.Done,
            keyboardActions = KeyboardActions(onDone = { submit() }),
        )
    }

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        PrimaryButton(
            text = stringResource(if (state.isSubmitting) R.string.reset_saving else R.string.reset_save),
            isLoading = state.isSubmitting,
            onClick = submit,
        )
        if (isEnabled) {
            RememberedItLink(onClick = actions::backToCredentials)
        }
    }
}

@Composable
private fun ResendCode(state: LoginUiState, actions: LoginActions) {
    val colors = LocalAikuColors.current
    val cooldown = state.resendCooldownSeconds

    when {
        cooldown > 0 -> Text(
            text = stringResource(R.string.reset_resend_wait, cooldown / SECONDS_PER_MINUTE, cooldown % SECONDS_PER_MINUTE),
            fontSize = 13.sp,
            color = colors.textQuaternary,
            modifier = Modifier
                .heightIn(min = LinkHeight)
                .padding(vertical = 13.dp),
        )
        state.isResetCodeUsedUp -> Box(modifier = Modifier.padding(top = 8.dp)) {
            TertiaryButton(
                text = stringResource(if (state.isResending) R.string.reset_sending else R.string.reset_new_code),
                iconResource = R.drawable.ic_ui_mail,
                onClick = actions::resendPasswordResetCode,
            )
        }
        else -> Text(
            text = stringResource(if (state.isResending) R.string.reset_sending else R.string.reset_resend),
            fontSize = 13.sp,
            fontWeight = FontWeight.SemiBold,
            color = colors.link,
            modifier = Modifier
                .heightIn(min = LinkHeight)
                .clip(ControlShape)
                .clickable(enabled = !state.isResending, role = Role.Button, onClick = actions::resendPasswordResetCode)
                .padding(vertical = 13.dp),
        )
    }
}

@Composable
private fun ResetHeading(title: String, description: String) {
    val colors = LocalAikuColors.current

    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(
            text = title,
            fontSize = 20.sp,
            lineHeight = 28.sp,
            fontWeight = FontWeight.Bold,
            color = colors.text,
        )
        Text(
            text = description,
            fontSize = 14.sp,
            lineHeight = 20.sp,
            fontWeight = FontWeight.Normal,
            color = colors.textTertiary,
        )
    }
}

@Composable
private fun RememberedItLink(onClick: () -> Unit) {
    Text(
        text = stringResource(R.string.reset_remembered),
        fontSize = 13.sp,
        fontStyle = FontStyle.Italic,
        color = LocalAikuColors.current.textTertiary,
        modifier = Modifier
            .heightIn(min = LinkHeight)
            .clickable(role = Role.Button, onClick = onClick)
            .padding(vertical = 13.dp),
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
private fun NoticeBox(message: UiMessage, hint: String?) {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.successSoft, ControlShape)
            .padding(12.dp)
            .semantics { liveRegion = LiveRegionMode.Polite },
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_feedback_check),
            contentDescription = null,
            tint = colors.success,
            modifier = Modifier.size(20.dp),
        )
        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(text = message.asText(), fontSize = 14.sp, lineHeight = 20.sp, color = colors.textSecondary)
            if (hint != null) {
                Text(text = hint, fontSize = 13.sp, lineHeight = 18.sp, color = colors.textTertiary)
            }
        }
    }
}

@Composable
private fun HintBox(text: String) {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier
            .fillMaxWidth()
            .background(colors.warnSoft, ControlShape)
            .border(1.dp, colors.warnBorder, ControlShape)
            .padding(12.dp)
            .semantics { liveRegion = LiveRegionMode.Polite },
    ) {
        Icon(
            painter = painterResource(R.drawable.ic_alert_triangle),
            contentDescription = null,
            tint = colors.warn,
            modifier = Modifier.padding(top = 1.dp).size(18.dp),
        )
        Text(text = text, fontSize = 13.sp, lineHeight = 18.sp, color = colors.warnInk)
    }
}

@Composable
private fun OneTimeCodeInput(
    value: String,
    onValueChange: (String) -> Unit,
    isError: Boolean,
    isEnabled: Boolean,
    imeAction: ImeAction,
    modifier: Modifier = Modifier,
    keyboardActions: KeyboardActions = KeyboardActions.Default,
) {
    AikuInput(
        value = value,
        onValueChange = onValueChange,
        placeholder = stringResource(R.string.otp_placeholder),
        isError = isError,
        isEnabled = isEnabled,
        textStyle = CodeTextStyle,
        keyboardOptions = KeyboardOptions(autoCorrectEnabled = false, keyboardType = KeyboardType.NumberPassword, imeAction = imeAction),
        keyboardActions = keyboardActions,
        modifier = modifier.semantics { contentType = ContentType.SmsOtpCode },
    )
}

@Composable
private fun NewPasswordInput(
    value: String,
    onValueChange: (String) -> Unit,
    isVisible: Boolean,
    onToggleVisibility: () -> Unit,
    isError: Boolean,
    isEnabled: Boolean,
    imeAction: ImeAction,
    keyboardActions: KeyboardActions = KeyboardActions.Default,
) {
    AikuInput(
        value = value,
        onValueChange = onValueChange,
        placeholder = stringResource(R.string.login_password_placeholder),
        isError = isError,
        isEnabled = isEnabled,
        visualTransformation = if (isVisible) VisualTransformation.None else PasswordVisualTransformation(),
        keyboardOptions = KeyboardOptions(autoCorrectEnabled = false, keyboardType = KeyboardType.Password, imeAction = imeAction),
        keyboardActions = keyboardActions,
        trailing = { PasswordVisibilityToggle(isVisible = isVisible, isEnabled = isEnabled, onToggle = onToggleVisibility) },
        modifier = Modifier.semantics { contentType = ContentType.NewPassword },
    )
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
    val focusProgress = animateFocusProgress(isFocused && isEnabled)
    val borderColor = when {
        !isEnabled -> colors.border
        isError -> colors.danger
        else -> lerp(colors.borderStrong, colors.focus, focusProgress)
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .height(ControlHeight)
            .focusHalo(progress = focusProgress, color = colors.focus, cornerRadius = 6.dp)
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
                indication = AikuFocusIndication(ringColor = colors.onPrimary, gapColor = colors.background, ripple = ripple(color = colors.onPrimary)),
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
private fun TertiaryButton(text: String, onClick: () -> Unit, iconResource: Int? = null) {
    val colors = LocalAikuColors.current

    Row(
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
        modifier = Modifier
            .fillMaxWidth()
            .height(ControlHeight)
            .clip(ControlShape)
            .border(1.dp, colors.borderStrong, ControlShape)
            .clickable(role = Role.Button, onClick = onClick),
    ) {
        if (iconResource != null) {
            Icon(painter = painterResource(iconResource), contentDescription = null, tint = colors.link, modifier = Modifier.size(20.dp))
            Spacer(modifier = Modifier.width(8.dp))
        }
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
    override fun onResetLoginChange(value: String) = Unit
    override fun onResetCodeChange(value: String) = Unit
    override fun onNewPasswordChange(value: String) = Unit
    override fun onNewPasswordConfirmationChange(value: String) = Unit
    override fun onNewPasswordVisibilityToggle() = Unit
    override fun onNewPasswordConfirmationVisibilityToggle() = Unit
    override fun signIn() = Unit
    override fun verifyOneTimePassword() = Unit
    override fun openPasswordReset() = Unit
    override fun sendPasswordResetCode() = Unit
    override fun resendPasswordResetCode() = Unit
    override fun resetPassword() = Unit
    override fun backToCredentials() = Unit
}

private val PreviewTwoFactorSetup = TwoFactorSetup(
    setupToken = "preview",
    secret = "JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP",
    otpauthUrl = "otpauth://totp/Aiku:alex.morgan?secret=JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP&issuer=Aiku",
    issuer = "Aiku",
    account = "alex.morgan",
    expiresInSeconds = 600,
)

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
private fun PasswordChangedPreview() {
    AikuDroidTheme {
        LoginScreen(
            state = LoginUiState(username = "alex.morgan", notice = UiMessage.FromResource(R.string.reset_done)),
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

@Preview(showSystemUi = true, heightDp = 1200)
@Composable
private fun TwoFactorSetupPreview() {
    AikuDroidTheme {
        LoginScreen(
            state = LoginUiState(step = LoginStep.TwoFactorSetup, username = "alex.morgan", password = "secret", twoFactorSetup = PreviewTwoFactorSetup),
            actions = PreviewLoginActions,
        )
    }
}

@Preview(showSystemUi = true)
@Composable
private fun PasswordResetPreview() {
    AikuDroidTheme {
        LoginScreen(state = LoginUiState(step = LoginStep.PasswordReset), actions = PreviewLoginActions)
    }
}

@Preview(showSystemUi = true, heightDp = 1100)
@Composable
private fun PasswordResetCodePreview() {
    AikuDroidTheme {
        LoginScreen(
            state = LoginUiState(
                step = LoginStep.PasswordResetCode,
                resetLogin = "alex.morgan",
                resendCooldownSeconds = RESEND_COOLDOWN_SECONDS,
                notice = UiMessage.FromResource(R.string.reset_code_sent),
            ),
            actions = PreviewLoginActions,
        )
    }
}
