/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../api/aiku_api_client.dart';
import '../ui/aiku_buttons.dart';
import '../ui/aiku_palette.dart';
import '../ui/aiku_text_field.dart';
import '../ui/auth_page.dart';
import '../ui/form_error_list.dart';
import 'password_reset_controller.dart';

/// Asks aiku to email a link for setting a new password.
///
/// The new password itself is set on the aiku web page the link opens.
class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key, required this.client});

  /// Keys for tests.
  static const emailFieldKey = ValueKey('forgotPassword.email');
  static const sendButtonKey = ValueKey('forgotPassword.send');
  static const cancelButtonKey = ValueKey('forgotPassword.cancel');
  static const doneButtonKey = ValueKey('forgotPassword.done');

  /// Sends the request.
  final AikuApiClient client;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  static const _bodyStyle = TextStyle(fontSize: 14, color: AikuPalette.label);

  late final PasswordResetController _controller = PasswordResetController(
    client: widget.client,
  );
  final _email = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    _email.dispose();
    super.dispose();
  }

  Future<void> _send() => _controller.requestLink(_email.text);

  void _close() => Navigator.of(context).pop();

  @override
  Widget build(BuildContext context) {
    return AuthPage(
      child: ListenableBuilder(
        listenable: _controller,
        builder: (context, _) {
          if (_controller.linkRequested) {
            return Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'If that email belongs to an aiku account, a link to set a '
                  'new password is on its way. It opens aiku in the browser, '
                  'on this phone or any computer.',
                  style: _bodyStyle,
                ),
                const SizedBox(height: 24),
                BorderedIconButton(
                  key: ForgotPasswordScreen.doneButtonKey,
                  label: 'Back to sign in',
                  icon: Icons.check,
                  iconColor: AikuPalette.confirm,
                  onPressed: _close,
                ),
              ],
            );
          }

          final errors = _controller.errors;
          return AutofillGroup(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  "Enter your aiku account's email address and we'll send "
                  'you a link to set a new password.',
                  style: _bodyStyle,
                ),
                const SizedBox(height: 24),
                AikuTextField(
                  fieldKey: ForgotPasswordScreen.emailFieldKey,
                  label: 'Email',
                  hint: 'Email',
                  controller: _email,
                  autofocus: true,
                  keyboardType: TextInputType.emailAddress,
                  autofillHints: const [AutofillHints.email],
                  textInputAction: TextInputAction.send,
                  errorText: errors.forField('email'),
                  onSubmitted: (_) => _send(),
                ),
                const SizedBox(height: 24),
                BorderedIconButton(
                  key: ForgotPasswordScreen.sendButtonKey,
                  label: 'Send reset link',
                  icon: Icons.check,
                  iconColor: AikuPalette.confirm,
                  isLoading: _controller.isSubmitting,
                  onPressed: _send,
                ),
                const SizedBox(height: 12),
                BorderedIconButton(
                  key: ForgotPasswordScreen.cancelButtonKey,
                  label: 'Cancel',
                  icon: Icons.close,
                  iconColor: AikuPalette.cancel,
                  onPressed: _close,
                ),
                FormErrorList(messages: errors.messages),
              ],
            ),
          );
        },
      ),
    );
  }
}
