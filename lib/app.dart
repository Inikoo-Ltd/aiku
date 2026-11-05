/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import 'api/aiku_api_client.dart';
import 'auth/auth_controller.dart';
import 'auth/forgot_password_screen.dart';
import 'auth/login_screen.dart';
import 'auth/two_factor_screen.dart';
import 'home/signed_in_screen.dart';
import 'ui/aiku_palette.dart';
import 'ui/loading_indicator.dart';

/// The aiku staff app: shows the screen that matches [controller]'s state.
class AikuApp extends StatelessWidget {
  const AikuApp({super.key, required this.controller, required this.client});

  /// The sign-in session.
  final AuthController controller;

  /// The aiku API, for screens that call it directly.
  final AikuApiClient client;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'aiku',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        scaffoldBackgroundColor: AikuPalette.paper,
        textSelectionTheme: const TextSelectionThemeData(
          cursorColor: AikuPalette.focus,
          selectionHandleColor: AikuPalette.focus,
        ),
      ),
      home: ListenableBuilder(
        listenable: controller,
        builder: (context, _) => switch (controller.state) {
          Restoring() => const _RestoringScreen(),
          SignedOut() => LoginScreen(
            controller: controller,
            onForgotPassword: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => ForgotPasswordScreen(client: client),
              ),
            ),
          ),
          AwaitingOneTimePassword() => TwoFactorScreen(controller: controller),
          SignedIn(:final session) => SignedInScreen(
            controller: controller,
            session: session,
          ),
        },
      ),
    );
  }
}

class _RestoringScreen extends StatelessWidget {
  const _RestoringScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: AikuPalette.paper,
      body: Center(
        child: LoadingIndicator(color: AikuPalette.defaultAccent, size: 24),
      ),
    );
  }
}
