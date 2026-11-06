/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:convert';

import 'package:aiku_mobile/home/dashboard_screen.dart';
import 'package:aiku_mobile/home/profile_menu.dart';
import 'package:aiku_mobile/home/theme_screen.dart';
import 'package:aiku_mobile/ui/aiku_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;

import '../support/navigation_fixtures.dart';
import '../support/signed_in_app.dart';

void main() {
  testWidgets(
    'the dashboard lays out the target, order pipeline, periods and shops',
    (tester) async {
      await pumpSignedInApp(tester, fakeAiku(groupUserNavigation()));

      for (final key in [
        GroupDashboard.previewNoticeKey,
        GroupDashboard.tabsKey,
        GroupDashboard.targetKey,
        GroupDashboard.pipelineKey,
        GroupDashboard.periodsKey,
        GroupDashboard.shopsKey,
      ]) {
        expect(find.byKey(key), findsOneWidget, reason: '$key');
      }
    },
  );

  testWidgets('the preview notice says the figures are not connected yet', (
    tester,
  ) async {
    await pumpSignedInApp(tester, fakeAiku(groupUserNavigation()));

    final text = tester.widget<Text>(
      find.descendant(
        of: find.byKey(GroupDashboard.previewNoticeKey),
        matching: find.byType(Text),
      ),
    );
    final box = tester.getSize(find.byKey(GroupDashboard.previewNoticeKey));

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
      final app = await pumpSignedInApp(
        tester,
        fakeAiku(
          groupUserNavigation(),
          otherwise: (request) async {
            requests.add(request);
            return jsonResponse({
              'data': {
                'username': 'raul',
                'contact_name': 'Raul',
                'app_theme': webTheme,
                'mobile_app_theme': amoled.colours,
              },
            }, 200);
          },
        ),
      );

      await tester.tap(find.byKey(ProfileMenu.buttonKey));
      await finishAnimations(tester);
      await tester.tap(find.byKey(ProfileMenu.themeKey));
      await finishAnimations(tester);
      await tester.tap(find.byKey(ThemeScreen.presetKey('Amoled')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));

      expect(requests.single.method, 'PATCH');
      expect(requests.single.url.path, '/app/api/mobile/profile/theme');
      expect(requests.single.headers['Authorization'], 'Bearer 1|secret');
      expect(jsonDecode(requests.single.body), {
        'mobile_app_theme': amoled.colours,
      });
      expect(app.store.session?.profile.mobileAppTheme, amoled.colours);
      expect(
        Theme.of(tester.element(find.byKey(ThemeScreen.presetKey('Amoled'))))
            .colorScheme
            .primary,
        const Color(0xFF8E44AD),
      );
    },
  );
}
