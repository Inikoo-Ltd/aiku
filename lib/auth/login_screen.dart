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

/// Username and password, as on aiku's web login.
class LoginScreen extends StatefulWidget {
  const LoginScreen({
    super.key,
    required this.controller,
    required this.onForgotPassword,
  });

  /// Keys for tests.
  static const usernameFieldKey = ValueKey('login.username');
  static const passwordFieldKey = ValueKey('login.password');
  static const passwordToggleKey = ValueKey('login.passwordToggle');
  static const forgotPasswordKey = ValueKey('login.forgotPassword');
  static const signInButtonKey = ValueKey('login.signIn');

  /// Runs the sign-in.
  final AuthController controller;

  /// Opens the forgot-password screen.
  final VoidCallback onForgotPassword;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _username = TextEditingController();
  final _password = TextEditingController();

  @override
  void dispose() {
    _username.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _signIn() => widget.controller.signIn(
    username: _username.text,
    password: _password.text,
  );

  @override
  Widget build(BuildContext context) {
    return AuthPage(
      child: ListenableBuilder(
        listenable: widget.controller,
        builder: (context, _) {
          final errors = widget.controller.errors;
          return AutofillGroup(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AikuTextField(
                  fieldKey: LoginScreen.usernameFieldKey,
                  label: 'Username',
                  hint: 'Username',
                  controller: _username,
                  autofocus: true,
                  autofillHints: const [AutofillHints.username],
                  textInputAction: TextInputAction.next,
                  errorText: errors.forField('username'),
                  onSubmitted: (_) => _signIn(),
                ),
                const SizedBox(height: 24),
                AikuTextField(
                  fieldKey: LoginScreen.passwordFieldKey,
                  toggleKey: LoginScreen.passwordToggleKey,
                  label: 'Password',
                  hint: '********',
                  controller: _password,
                  canBeHidden: true,
                  keyboardType: TextInputType.visiblePassword,
                  autofillHints: const [AutofillHints.password],
                  textInputAction: TextInputAction.go,
                  errorText: errors.forField('password'),
                  onSubmitted: (_) => _signIn(),
                ),
                const SizedBox(height: 8),
                Align(
                  alignment: Alignment.centerLeft,
                  child: _ForgotPasswordLink(
                    onPressed: widget.onForgotPassword,
                  ),
                ),
                const SizedBox(height: 24),
                SignInButton(
                  key: LoginScreen.signInButtonKey,
                  label: 'Sign in',
                  isLoading: widget.controller.isSubmitting,
                  onPressed: _signIn,
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

class _ForgotPasswordLink extends StatelessWidget {
  const _ForgotPasswordLink({required this.onPressed});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      link: true,
      child: GestureDetector(
        key: LoginScreen.forgotPasswordKey,
        onTap: onPressed,
        behavior: HitTestBehavior.opaque,
        child: const Padding(
          padding: EdgeInsets.symmetric(vertical: 4),
          child: Text(
            'Forgot password?',
            style: TextStyle(
              fontSize: 12,
              fontStyle: FontStyle.italic,
              color: AikuPalette.quietLink,
            ),
          ),
        ),
      ),
    );
  }
}
