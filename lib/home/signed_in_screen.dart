/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../auth/auth_controller.dart';
import '../auth/token_store.dart';
import '../ui/accent_shades.dart';
import '../ui/aiku_buttons.dart';
import '../ui/aiku_palette.dart';
import '../ui/auth_page.dart';

/// A placeholder until picking arrives: who is signed in, and sign out.
class SignedInScreen extends StatelessWidget {
  const SignedInScreen({
    super.key,
    required this.controller,
    required this.session,
  });

  /// Keys for tests.
  static const nameKey = ValueKey('signedIn.name');
  static const usernameKey = ValueKey('signedIn.username');
  static const signOutButtonKey = ValueKey('signedIn.signOut');

  /// Runs the sign-out.
  final AuthController controller;

  /// The signed-in user.
  final StoredSession session;

  @override
  Widget build(BuildContext context) {
    final profile = session.profile;
    final accent = AccentShades.fromAppTheme(profile.appTheme);

    return AuthPage(
      child: ListenableBuilder(
        listenable: controller,
        builder: (context, _) => Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              profile.contactName ?? profile.username,
              key: nameKey,
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w600,
                color: AikuPalette.ink,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              profile.username,
              key: usernameKey,
              style: const TextStyle(fontSize: 14, color: AikuPalette.label),
            ),
            const SizedBox(height: 24),
            BorderedIconButton(
              key: signOutButtonKey,
              label: 'Sign out',
              icon: Icons.logout,
              iconColor: accent.accent,
              isLoading: controller.isSubmitting,
              onPressed: controller.signOut,
            ),
          ],
        ),
      ),
    );
  }
}
