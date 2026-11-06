/*
 * Author Louis Perez
 * Created on 06-11-2026-11h-06m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Light, dark, or whatever the phone is set to.
enum Appearance {
  system('System'),
  light('Light'),
  dark('Dark');

  const Appearance(this.label);

  /// What the Theme screen calls it.
  final String label;

  /// The Material theme mode it stands for.
  ThemeMode get themeMode => switch (this) {
    Appearance.system => ThemeMode.system,
    Appearance.light => ThemeMode.light,
    Appearance.dark => ThemeMode.dark,
  };
}

/// Keeps the chosen [Appearance] on this phone.
abstract interface class AppearanceStore {
  /// The saved choice, if any.
  Future<Appearance?> read();

  /// Saves [appearance].
  Future<void> write(Appearance appearance);
}

/// Keeps the appearance in secure storage next to the session. It is a
/// setting of the phone, not of the aiku account.
class SecureAppearanceStore implements AppearanceStore {
  SecureAppearanceStore([this._storage = const FlutterSecureStorage()]);

  final FlutterSecureStorage _storage;

  static const _key = 'aiku.appearance';

  @override
  Future<Appearance?> read() async {
    try {
      final saved = await _storage.read(key: _key);
      return Appearance.values.asNameMap()[saved];
    } on PlatformException {
      return null;
    }
  }

  @override
  Future<void> write(Appearance appearance) async {
    try {
      await _storage.write(key: _key, value: appearance.name);
    } on PlatformException {
      // The choice still applies until the app closes.
    }
  }
}

/// The signed-in app's appearance: the phone's setting until the user picks
/// Light or Dark on the Theme screen.
class AppearanceController extends ChangeNotifier {
  AppearanceController({required this._store});

  final AppearanceStore _store;

  Appearance _appearance = Appearance.system;

  /// The current choice.
  Appearance get appearance => _appearance;

  /// Reads the saved choice.
  Future<void> restore() async {
    final saved = await _store.read();
    if (saved != null && saved != _appearance) {
      _appearance = saved;
      notifyListeners();
    }
  }

  /// Applies [appearance] at once and saves it on the phone.
  Future<void> choose(Appearance appearance) async {
    if (appearance == _appearance) {
      return;
    }
    _appearance = appearance;
    notifyListeners();
    await _store.write(appearance);
  }
}
