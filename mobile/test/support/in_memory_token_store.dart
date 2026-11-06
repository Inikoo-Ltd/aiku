/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:aiku_mobile/auth/token_store.dart';

/// Stands in for the Android Keystore, which tests cannot reach.
class InMemoryTokenStore implements TokenStore {
  InMemoryTokenStore([this.session]);

  /// What is saved right now.
  StoredSession? session;

  @override
  Future<StoredSession?> read() async => session;

  @override
  Future<void> save(StoredSession session) async => this.session = session;

  @override
  Future<void> clear() async => session = null;
}
