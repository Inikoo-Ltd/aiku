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
import 'config/app_config.dart';
import 'home/app_shell.dart';
import 'home/area_memory.dart';
import 'ui/accent_shades.dart';
import 'ui/aiku_palette.dart';
import 'ui/aiku_theme.dart';
import 'ui/loading_indicator.dart';

/// The aiku staff app: shows the screen that matches [controller]'s state,
/// in the signed-in user's theme once there is one.
class AikuApp extends StatefulWidget {
  const AikuApp({
    super.key,
    required this.controller,
    required this.client,
    required this.config,
    required this.areaMemory,
  });

  /// The sign-in session.
  final AuthController controller;

  /// The aiku API, for screens that call it directly.
  final AikuApiClient client;

  /// The build settings, shown on the Settings screen.
  final AppConfig config;

  /// Remembers each user's last area on the phone.
  final AreaMemory areaMemory;

  @override
  State<AikuApp> createState() => _AikuAppState();
}

class _AikuAppState extends State<AikuApp> {
  static final _signedOutTheme = ThemeData(
    scaffoldBackgroundColor: AikuPalette.paper,
    textSelectionTheme: const TextSelectionThemeData(
      cursorColor: AikuPalette.focus,
      selectionHandleColor: AikuPalette.focus,
    ),
  );

  final _navigatorKey = GlobalKey<NavigatorState>();
  bool _wasSignedIn = false;

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_closePagesOnSignOut);
  }

  @override
  void didUpdateWidget(AikuApp oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_closePagesOnSignOut);
      widget.controller.addListener(_closePagesOnSignOut);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_closePagesOnSignOut);
    super.dispose();
  }

  /// Pages opened while signed in (such as Theme) must not outlive the
  /// session, e.g. after a 401.
  void _closePagesOnSignOut() {
    final isSignedIn = widget.controller.state is SignedIn;
    if (_wasSignedIn && !isSignedIn) {
      _navigatorKey.currentState?.popUntil((route) => route.isFirst);
    }
    _wasSignedIn = isSignedIn;
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: widget.controller,
      builder: (context, _) {
        final state = widget.controller.state;
        return MaterialApp(
          title: 'aiku',
          debugShowCheckedModeBanner: false,
          navigatorKey: _navigatorKey,
          theme: switch (state) {
            SignedIn(:final session) => signedInThemeData(
              AccentShades.fromAppTheme(effectiveTheme(session.profile)),
            ),
            _ => _signedOutTheme,
          },
          home: switch (state) {
            Restoring() => const _RestoringScreen(),
            SignedOut() => LoginScreen(
              controller: widget.controller,
              onForgotPassword: () => _navigatorKey.currentState?.push(
                MaterialPageRoute<void>(
                  builder: (_) => ForgotPasswordScreen(client: widget.client),
                ),
              ),
            ),
            AwaitingOneTimePassword() => TwoFactorScreen(
              controller: widget.controller,
            ),
            SignedIn(:final session) => AppShell(
              authController: widget.controller,
              session: session,
              client: widget.client,
              config: widget.config,
              areaMemory: widget.areaMemory,
            ),
          },
        );
      },
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
