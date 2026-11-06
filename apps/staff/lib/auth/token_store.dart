/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../api/login_result.dart';

/// A signed-in session as it is kept on the device.
class const StoredSession({
  required final String token,
  required final DateTime? expiresAt,
  required final StaffProfile profile,
}) {
  /// Reads what [toJson] wrote. Returns null for anything else.
  static StoredSession? fromJson(Object? json) => switch (json) {
    {
      'token': final String token,
      'profile': final Map<String, Object?> profile,
    } =>
      StoredSession(
        token: token,
        expiresAt: switch (json['expires_at']) {
          final String value => DateTime.tryParse(value),
          _ => null,
        },
        profile: StaffProfile.fromJson(profile),
      ),
    _ => null,
  };

  /// The shape [fromJson] reads.
  Map<String, Object?> toJson() => {
    'token': token,
    'expires_at': expiresAt?.toIso8601String(),
    'profile': profile.toJson(),
  };
}

/// Where the signed-in session lives between app launches.
abstract interface class TokenStore {
  /// The saved session, or null when nobody is signed in.
  Future<StoredSession?> read();

  /// Replaces the saved session.
  Future<void> save(StoredSession session);

  /// Forgets the saved session.
  Future<void> clear();
}

/// Keeps the session in the Android Keystore through flutter_secure_storage.
class SecureTokenStore implements TokenStore {
  SecureTokenStore([this._storage = const FlutterSecureStorage()]);

  static const _sessionKey = 'aiku.session';

  final FlutterSecureStorage _storage;

  @override
  Future<StoredSession?> read() async {
    try {
      final saved = await _storage.read(key: _sessionKey);
      return saved == null ? null : StoredSession.fromJson(jsonDecode(saved));
    } on PlatformException {
      return null;
    } on FormatException {
      return null;
    }
  }

  @override
  Future<void> save(StoredSession session) =>
      _storage.write(key: _sessionKey, value: jsonEncode(session.toJson()));

  @override
  Future<void> clear() => _storage.delete(key: _sessionKey);
}
