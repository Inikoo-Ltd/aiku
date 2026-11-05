/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
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
import 'package:aiku_mobile/home/dashboard_screen.dart';
import 'package:aiku_mobile/home/theme_screen.dart';
import 'package:aiku_mobile/ui/aiku_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import '../support/in_memory_token_store.dart';

const _webTheme = [
  '#4f46e5',
  '#f3f4f6',
  '#362cdb',
  '#fde047',
  '#4f46e5',
  '#f3f4f6',
  '#fcd34d',
  '#374151',
];

/// Pumps the app signed in as "raul", on a phone-sized screen.
Future<InMemoryTokenStore> _pumpSignedInApp(
  WidgetTester tester,
  Future<http.Response> Function(http.Request request) handler,
) async {
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
        appTheme: _webTheme,
      ),
    ),
  );
  final client = AikuApiClient(
    config: const AppConfig(apiBaseUrl: 'http://aiku.example', appKey: 'key'),
    httpClient: MockClient(handler),
  );
  final controller = AuthController(
    client: client,
    tokenStore: store,
    deviceName: () async => 'Test Phone Â· Android 15',
    showDiagnostics: false,
  );
  addTearDown(controller.dispose);
  await controller.restore();

  await tester.pumpWidget(AikuApp(controller: controller, client: client));
  await tester.pump();
  return store;
}

void main() {
  testWidgets(
    'the dashboard lays out the target, order pipeline, periods and shops',
    (tester) async {
      await _pumpSignedInApp(tester, (_) async => http.Response('', 500));

      for (final key in [
        DashboardScreen.previewNoticeKey,
        DashboardScreen.tabsKey,
        DashboardScreen.targetKey,
        DashboardScreen.pipelineKey,
        DashboardScreen.periodsKey,
        DashboardScreen.shopsKey,
      ]) {
        expect(find.byKey(key), findsOneWidget, reason: '$key');
      }
    },
  );

  testWidgets('the preview notice says the figures are not connected yet', (
    tester,
  ) async {
    await _pumpSignedInApp(tester, (_) async => http.Response('', 500));

    final text = tester.widget<Text>(
      find.descendant(
        of: find.byKey(DashboardScreen.previewNoticeKey),
        matching: find.byType(Text),
      ),
    );
    final box = tester.getSize(find.byKey(DashboardScreen.previewNoticeKey));

    expect(text.data, contains('Layout preview'));
    expect(text.style?.color?.a, 1);
    expect(box.height, greaterThan(30));
  });

  testWidgets(
    'picking a theme recolours the app and saves it on the phone and in aiku',
    (tester) async {
      final amoled = aikuThemePresets.firstWhere(
        (preset) => preset.name == 'Amoled',
      );
      final requests = <http.Request>[];
      final store = await _pumpSignedInApp(tester, (request) async {
        requests.add(request);
        return http.Response(
          jsonEncode({
            'data': {
              'username': 'raul',
              'contact_name': 'Raul',
              'app_theme': _webTheme,
              'mobile_app_theme': amoled.colours,
            },
          }),
          200,
        );
      });

      await tester.tap(find.byKey(DashboardScreen.themeButtonKey));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(ThemeScreen.presetKey('Amoled')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));

      expect(requests.single.method, 'PATCH');
      expect(requests.single.url.path, '/app/api/mobile/profile/theme');
      expect(requests.single.headers['Authorization'], 'Bearer 1|secret');
      expect(jsonDecode(requests.single.body), {
        'mobile_app_theme': amoled.colours,
      });
      expect(store.session?.profile.mobileAppTheme, amoled.colours);
      expect(
        Theme.of(tester.element(find.byKey(ThemeScreen.presetKey('Amoled'))))
            .colorScheme
            .primary,
        const Color(0xFF8E44AD),
      );
    },
  );
}
