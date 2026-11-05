/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:convert';

import 'package:aiku_mobile/api/aiku_api_client.dart';
import 'package:aiku_mobile/api/login_result.dart';
import 'package:aiku_mobile/auth/auth_controller.dart';
import 'package:aiku_mobile/auth/token_store.dart';
import 'package:aiku_mobile/config/app_config.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import '../support/in_memory_token_store.dart';

const _profile = StaffProfile(
  username: 'raul',
  contactName: 'Raul',
  appTheme: null,
);

AuthController _controller(
  InMemoryTokenStore store,
  Future<http.Response> Function(http.Request request) handler, {
  DateTime Function()? now,
}) => AuthController(
  client: AikuApiClient(
    config: const AppConfig(apiBaseUrl: 'http://aiku.example', appKey: 'key'),
    httpClient: MockClient(handler),
  ),
  tokenStore: store,
  deviceName: () async => 'Test Phone · Android 15',
  now: now ?? DateTime.now,
  showDiagnostics: false,
);

void main() {
  test('a successful login stores the token and its expiry', () async {
    final store = InMemoryTokenStore();
    final controller = _controller(
      store,
      (_) async => http.Response(
        jsonEncode({
          'token': '1|secret',
          'expires_at': '2026-11-06T03:00:00Z',
          'user': {'username': 'raul', 'contact_name': 'Raul'},
        }),
        200,
      ),
    );
    addTearDown(controller.dispose);

    await controller.signIn(username: 'raul', password: 'pw');

    expect(controller.state, isA<SignedIn>());
    expect(store.session?.token, '1|secret');
    expect(store.session?.expiresAt, DateTime.utc(2026, 11, 6, 3));
  });

  test('a 401 on sign out still deletes the stored token', () async {
    final store = InMemoryTokenStore(
      const StoredSession(token: '1|old', expiresAt: null, profile: _profile),
    );
    final controller = _controller(
      store,
      (_) async => http.Response('{"message":"Unauthenticated."}', 401),
    );
    addTearDown(controller.dispose);
    await controller.restore();

    await controller.signOut();

    expect(store.session, isNull);
    expect(controller.state, isA<SignedOut>());
  });

  test(
    'sign out deletes the stored token even when aiku cannot be reached',
    () async {
      final store = InMemoryTokenStore(
        const StoredSession(token: '1|old', expiresAt: null, profile: _profile),
      );
      final controller = _controller(
        store,
        (request) async => throw http.ClientException('offline', request.url),
      );
      addTearDown(controller.dispose);
      await controller.restore();

      await controller.signOut();

      expect(store.session, isNull);
      expect(controller.state, isA<SignedOut>());
    },
  );

  test('restore opens a saved session that has no expiry', () async {
    final store = InMemoryTokenStore(
      const StoredSession(token: '1|kept', expiresAt: null, profile: _profile),
    );
    final controller = _controller(store, (_) async => http.Response('', 500));
    addTearDown(controller.dispose);

    await controller.restore();

    expect(controller.state, isA<SignedIn>());
  });

  test('restore drops a saved session whose expiry has passed', () async {
    final store = InMemoryTokenStore(
      StoredSession(
        token: '1|old',
        expiresAt: DateTime.utc(2026, 11, 5, 9),
        profile: _profile,
      ),
    );
    final controller = _controller(
      store,
      (_) async => http.Response('', 500),
      now: () => DateTime.utc(2026, 11, 5, 10),
    );
    addTearDown(controller.dispose);

    await controller.restore();

    expect(controller.state, isA<SignedOut>());
    expect(store.session, isNull);
  });

  test('a theme aiku cannot save stays on the phone and says why', () async {
    const theme = [
      '#000000',
      '#f2f2f2',
      '#424242',
      '#fde047',
      '#8e44ad',
      '#ffffff',
      '#fcd34d',
      '#374151',
    ];
    final store = InMemoryTokenStore(
      const StoredSession(token: '1|kept', expiresAt: null, profile: _profile),
    );
    final controller = _controller(
      store,
      (request) async => throw http.ClientException('offline', request.url),
    );
    addTearDown(controller.dispose);
    await controller.restore();

    await controller.chooseMobileTheme(theme);

    expect(store.session?.profile.mobileAppTheme, theme);
    expect(controller.state, isA<SignedIn>());
    expect(controller.themeErrors.messages, isNotEmpty);
    expect(controller.themeBeingSaved, isNull);
  });

  test('a 401 while saving a theme signs out', () async {
    final store = InMemoryTokenStore(
      const StoredSession(
        token: '1|revoked',
        expiresAt: null,
        profile: _profile,
      ),
    );
    final controller = _controller(
      store,
      (_) async => http.Response('{"message":"Unauthenticated."}', 401),
    );
    addTearDown(controller.dispose);
    await controller.restore();

    await controller.chooseMobileTheme(List.filled(8, '#000000'));

    expect(controller.state, isA<SignedOut>());
    expect(store.session, isNull);
  });
}
