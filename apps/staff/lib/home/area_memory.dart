/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-13m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Remembers, per user, the last area they worked in on this phone.
abstract interface class AreaMemory {
  /// The key of [username]'s last area, if any.
  Future<String?> read(String username);

  /// Remembers [areaKey] as [username]'s last area.
  Future<void> write(String username, String areaKey);
}

/// Keeps the last area next to the session in the Android Keystore.
class SecureAreaMemory implements AreaMemory {
  SecureAreaMemory([this._storage = const FlutterSecureStorage()]);

  final FlutterSecureStorage _storage;

  static String _key(String username) => 'aiku.area.$username';

  @override
  Future<String?> read(String username) async {
    try {
      return await _storage.read(key: _key(username));
    } on PlatformException {
      return null;
    }
  }

  @override
  Future<void> write(String username, String areaKey) async {
    try {
      await _storage.write(key: _key(username), value: areaKey);
    } on PlatformException {
      // Not remembering the area only costs the user one tap next time.
    }
  }
}
