/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-27m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../api/login_result.dart';
import '../ui/app_colours.dart';

enum _ProfileAction { theme, settings, signOut }

/// The profile picture at the top right, opening Theme, Settings and
/// Log out.
class ProfileMenu extends StatelessWidget {
  const ProfileMenu({
    super.key,
    required this.profile,
    required this.onTheme,
    required this.onSettings,
    required this.onSignOut,
  });

  /// Keys for tests.
  static const buttonKey = ValueKey('profile.button');
  static const themeKey = ValueKey('profile.theme');
  static const settingsKey = ValueKey('profile.settings');
  static const signOutKey = ValueKey('profile.signOut');

  /// The signed-in user.
  final StaffProfile profile;

  /// Opens the Theme screen.
  final VoidCallback onTheme;

  /// Opens the Settings screen.
  final VoidCallback onSettings;

  /// Logs out.
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    final name = profile.contactName ?? profile.username;
    final initial = name.isEmpty ? '?' : name.characters.first.toUpperCase();

    return PopupMenuButton<_ProfileAction>(
      key: buttonKey,
      tooltip: 'Profile',
      position: PopupMenuPosition.under,
      onSelected: (action) => switch (action) {
        _ProfileAction.theme => onTheme(),
        _ProfileAction.settings => onSettings(),
        _ProfileAction.signOut => onSignOut(),
      },
      itemBuilder: (context) => [
        PopupMenuItem<_ProfileAction>(
          enabled: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                name,
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  color: colours.ink,
                ),
              ),
              Text(
                profile.username,
                style: TextStyle(fontSize: 12, color: colours.secondary),
              ),
            ],
          ),
        ),
        const PopupMenuDivider(),
        _item(
          key: themeKey,
          action: _ProfileAction.theme,
          icon: Icons.palette_outlined,
          iconColor: colours.accentText,
          textColor: colours.ink,
          label: 'Theme',
        ),
        _item(
          key: settingsKey,
          action: _ProfileAction.settings,
          icon: Icons.settings_outlined,
          iconColor: colours.accentText,
          textColor: colours.ink,
          label: 'Settings',
        ),
        _item(
          key: signOutKey,
          action: _ProfileAction.signOut,
          icon: Icons.logout,
          iconColor: colours.errorText,
          textColor: colours.ink,
          label: 'Log out',
        ),
      ],
      // The picture sits on the theme band.
      child: Padding(
        padding: const EdgeInsets.all(4),
        child: CircleAvatar(
          radius: 16,
          backgroundColor: colours.onBand.withValues(alpha: 0.2),
          child: Text(
            initial,
            style: TextStyle(
              color: colours.onBand,
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }

  static PopupMenuItem<_ProfileAction> _item({
    required Key key,
    required _ProfileAction action,
    required IconData icon,
    required Color iconColor,
    required Color textColor,
    required String label,
  }) {
    return PopupMenuItem(
      key: key,
      value: action,
      child: Row(
        children: [
          Icon(icon, size: 18, color: iconColor),
          const SizedBox(width: 10),
          Text(label, style: TextStyle(color: textColor)),
        ],
      ),
    );
  }
}
