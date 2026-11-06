/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../ui/aiku_buttons.dart';
import '../ui/aiku_palette.dart';
import '../ui/aiku_text_field.dart';
import '../ui/auth_page.dart';
import '../ui/form_error_list.dart';
import 'auth_controller.dart';

/// The one-time password step, shown after aiku answers `otp_required`.
class TwoFactorScreen extends StatefulWidget {
  const TwoFactorScreen({super.key, required this.controller});

  /// Keys for tests.
  static const codeFieldKey = ValueKey('twoFactor.code');
  static const enterButtonKey = ValueKey('twoFactor.enter');
  static const signOutButtonKey = ValueKey('twoFactor.signOut');

  /// Runs the two-factor step.
  final AuthController controller;

  @override
  State<TwoFactorScreen> createState() => _TwoFactorScreenState();
}

class _TwoFactorScreenState extends State<TwoFactorScreen> {
  final _code = TextEditingController();

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit() =>
      widget.controller.submitOneTimePassword(_code.text.trim());

  @override
  Widget build(BuildContext context) {
    return AuthPage(
      child: ListenableBuilder(
        listenable: widget.controller,
        builder: (context, _) {
          final errors = widget.controller.errors;
          return Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AikuTextField(
                fieldKey: TwoFactorScreen.codeFieldKey,
                label: 'Enter OTP from your Authenticator App',
                hint: '',
                controller: _code,
                autofocus: true,
                keyboardType: TextInputType.number,
                autofillHints: const [AutofillHints.oneTimeCode],
                textInputAction: TextInputAction.go,
                errorText: errors.forField('one_time_password'),
                onSubmitted: (_) => _submit(),
              ),
              const SizedBox(height: 24),
              Row(
                children: [
                  Expanded(
                    child: SignInButton(
                      key: TwoFactorScreen.enterButtonKey,
                      label: 'Enter',
                      isLoading: widget.controller.isSubmitting,
                      onPressed: _submit,
                    ),
                  ),
                  const SizedBox(width: 8),
                  SquareIconButton(
                    key: TwoFactorScreen.signOutButtonKey,
                    icon: Icons.logout,
                    color: AikuPalette.errorIcon,
                    tooltip: 'Back to sign in',
                    onPressed: widget.controller.cancelOneTimePassword,
                  ),
                ],
              ),
              FormErrorList(messages: errors.messages),
            ],
          );
        },
      ),
    );
  }
}
