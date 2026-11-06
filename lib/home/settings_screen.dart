/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-27m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../config/app_config.dart';
import '../ui/aiku_palette.dart';

/// Which build of the app this is and which aiku server it talks to.
class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key, required this.config});

  /// Keys for tests.
  static const versionKey = ValueKey('settings.version');
  static const serverKey = ValueKey('settings.server');

  /// The build settings; only the server address is shown, never the key.
  final AppConfig config;

  /// The version from the pubspec (or `--build-name`/`--build-number`).
  static String get appVersion => switch ((appBuildName, appBuildNumber)) {
    (final String name, final String number) => '$name ($number)',
    (final String name, null) => name,
    (null, _) => 'Not set in this build',
  };

  static const _valueStyle = TextStyle(fontSize: 14, color: AikuPalette.label);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        children: [
          ListTile(
            key: versionKey,
            leading: const Icon(Icons.info_outline),
            title: const Text('App version'),
            subtitle: Text(appVersion, style: _valueStyle),
          ),
          ListTile(
            key: serverKey,
            leading: const Icon(Icons.dns_outlined),
            title: const Text('Server'),
            subtitle: Text(config.apiBaseUrl, style: _valueStyle),
          ),
        ],
      ),
    );
  }
}
