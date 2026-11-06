/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-31m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:convert';

import 'package:aiku_mobile/api/aiku_api_client.dart';
import 'package:aiku_mobile/api/login_result.dart';
import 'package:aiku_mobile/app.dart';
import 'package:aiku_mobile/auth/auth_controller.dart';
import 'package:aiku_mobile/auth/token_store.dart';
import 'package:aiku_mobile/config/app_config.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import 'in_memory_area_memory.dart';
import 'in_memory_token_store.dart';

/// Answers one request to the fake aiku.
typedef AikuHandler = Future<http.Response> Function(http.Request request);

/// aiku's default theme, as the signed-in user's web theme.
const webTheme = [
  '#4f46e5',
  '#f3f4f6',
  '#362cdb',
  '#fde047',
  '#4f46e5',
  '#f3f4f6',
  '#fcd34d',
  '#374151',
];

/// The address the fake aiku is reached at.
const testServer = 'http://aiku.example';

/// A JSON response.
http.Response jsonResponse(Object? body, int status) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

/// Raul's profile as aiku sends it, the same as the one saved on the phone
/// unless something is changed.
Map<String, Object?> profileJson({
  String contactName = 'Raul',
  List<String>? mobileAppTheme,
}) => {
  'username': 'raul',
  'contact_name': contactName,
  'app_theme': webTheme,
  'mobile_app_theme': mobileAppTheme,
};

/// A fake aiku: `GET navigation` answers [navigation], `GET profile`
/// answers [profile] (Raul's saved profile when not given), and every other
/// request goes to [otherwise] (a 500 when there is none).
AikuHandler fakeAiku(
  Map<String, Object?> navigation, {
  Map<String, Object?>? profile,
  AikuHandler? otherwise,
}) => (request) async {
  switch ((request.method, request.url.path)) {
    case ('GET', '/app/api/mobile/navigation'):
      return jsonResponse(navigation, 200);
    case ('GET', '/app/api/mobile/profile'):
      return jsonResponse({'data': profile ?? profileJson()}, 200);
  }
  return otherwise == null ? http.Response('', 500) : otherwise(request);
};

/// What a test can look at after [pumpSignedInApp].
class const SignedInApp({
  required final InMemoryTokenStore store,
  required final InMemoryAreaMemory areaMemory,
});

/// Pumps the whole app signed in as "raul" (Raul), on a phone-sized screen,
/// and lets the first requests answer.
Future<SignedInApp> pumpSignedInApp(
  WidgetTester tester,
  AikuHandler handler, {
  Map<String, String>? rememberedAreas,
}) async {
  tester.view.physicalSize = const Size(1080, 2340);
  tester.view.devicePixelRatio = 2.75;
  addTearDown(tester.view.reset);

  final store = InMemoryTokenStore(
    const StoredSession(
      token: '1|secret',
      expiresAt: null,
      profile: StaffProfile(
        username: 'raul',
        contactName: 'Raul',
        appTheme: webTheme,
      ),
    ),
  );
  final areaMemory = InMemoryAreaMemory(rememberedAreas);
  const config = AppConfig(apiBaseUrl: testServer, appKey: 'key');
  final client = AikuApiClient(config: config, httpClient: MockClient(handler));
  final controller = AuthController(
    client: client,
    tokenStore: store,
    deviceName: () async => 'Test Phone · Android 15',
    showDiagnostics: false,
  );
  addTearDown(controller.dispose);
  await controller.restore();

  await tester.pumpWidget(
    AikuApp(
      controller: controller,
      client: client,
      config: config,
      areaMemory: areaMemory,
    ),
  );
  await tester.pump();
  await tester.pump();
  return SignedInApp(store: store, areaMemory: areaMemory);
}

/// Lets a drawer, popup menu or page transition finish.
Future<void> finishAnimations(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 600));
}
