/*
 * Author Louis Perez
 * Created on 06-11-2026-11h-06m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:aiku_mobile/api/aiku_api_client.dart';
import 'package:aiku_mobile/app.dart';
import 'package:aiku_mobile/auth/auth_controller.dart';
import 'package:aiku_mobile/auth/login_screen.dart';
import 'package:aiku_mobile/config/app_config.dart';
import 'package:aiku_mobile/home/app_shell.dart';
import 'package:aiku_mobile/home/appearance.dart';
import 'package:aiku_mobile/home/profile_menu.dart';
import 'package:aiku_mobile/home/theme_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import '../support/in_memory_appearance_store.dart';
import '../support/in_memory_area_memory.dart';
import '../support/in_memory_token_store.dart';
import '../support/navigation_fixtures.dart';
import '../support/signed_in_app.dart';

Brightness _brightnessAt(WidgetTester tester, Finder finder) =>
    Theme.of(tester.element(finder)).brightness;

void _phoneIsDark(WidgetTester tester) {
  tester.platformDispatcher.platformBrightnessTestValue = Brightness.dark;
  addTearDown(tester.platformDispatcher.clearPlatformBrightnessTestValue);
}

void main() {
  group('AppearanceController', () {
    test('follows the phone until something was chosen', () async {
      final controller = AppearanceController(store: InMemoryAppearanceStore());
      addTearDown(controller.dispose);

      await controller.restore();

      expect(controller.appearance, Appearance.system);
      expect(controller.appearance.themeMode, ThemeMode.system);
    });

    test('opens with the choice kept on the phone', () async {
      final controller = AppearanceController(
        store: InMemoryAppearanceStore(Appearance.dark),
      );
      addTearDown(controller.dispose);

      await controller.restore();

      expect(controller.appearance, Appearance.dark);
    });

    test('a choice applies at once and is kept on the phone', () async {
      final store = InMemoryAppearanceStore();
      final controller = AppearanceController(store: store);
      addTearDown(controller.dispose);
      var notifications = 0;
      controller.addListener(() => notifications++);

      await controller.choose(Appearance.light);

      expect(controller.appearance, Appearance.light);
      expect(store.saved, Appearance.light);
      expect(notifications, 1);
    });
  });

  testWidgets('the app bar is the theme band, in the user’s accent', (
    tester,
  ) async {
    await pumpSignedInApp(tester, fakeAiku(groupUserNavigation()));

    final appBar = tester.widget<AppBar>(find.byType(AppBar));
    final theme = Theme.of(tester.element(find.byType(AppBar)));

    expect(theme.appBarTheme.backgroundColor, const Color(0xFF4F46E5));
    expect(
      appBar.backgroundColor ?? theme.appBarTheme.backgroundColor,
      const Color(0xFF4F46E5),
    );
  });

  testWidgets('choosing Dark on the Theme screen darkens the app', (
    tester,
  ) async {
    final app = await pumpSignedInApp(tester, fakeAiku(groupUserNavigation()));
    expect(
      _brightnessAt(tester, find.byKey(AppShell.titleKey)),
      Brightness.light,
    );

    await tester.tap(find.byKey(ProfileMenu.buttonKey));
    await finishAnimations(tester);
    await tester.tap(find.byKey(ProfileMenu.themeKey));
    await finishAnimations(tester);
    await tester.tap(find.byKey(ThemeScreen.appearanceKey(Appearance.dark)));
    await finishAnimations(tester);

    expect(
      _brightnessAt(tester, find.byKey(ThemeScreen.presetKey('Amoled'))),
      Brightness.dark,
    );
    expect(
      tester
          .widget<RadioGroup<Appearance>>(find.byType(RadioGroup<Appearance>))
          .groupValue,
      Appearance.dark,
    );
    expect(app.appearanceStore.saved, Appearance.dark);
  });

  testWidgets('System follows the phone’s dark mode', (tester) async {
    _phoneIsDark(tester);

    await pumpSignedInApp(tester, fakeAiku(groupUserNavigation()));

    expect(
      _brightnessAt(tester, find.byKey(AppShell.titleKey)),
      Brightness.dark,
    );
  });

  testWidgets('a kept Light choice stays light on a dark phone', (
    tester,
  ) async {
    _phoneIsDark(tester);

    await pumpSignedInApp(
      tester,
      fakeAiku(groupUserNavigation()),
      savedAppearance: Appearance.light,
    );

    expect(
      _brightnessAt(tester, find.byKey(AppShell.titleKey)),
      Brightness.light,
    );
  });

  testWidgets('the sign-in screen stays light on a dark phone', (tester) async {
    _phoneIsDark(tester);
    const config = AppConfig(apiBaseUrl: testServer, appKey: 'key');
    final client = AikuApiClient(
      config: config,
      httpClient: MockClient((_) async => http.Response('', 500)),
    );
    final controller = AuthController(
      client: client,
      tokenStore: InMemoryTokenStore(),
      deviceName: () async => 'Test Phone',
      showDiagnostics: false,
    );
    addTearDown(controller.dispose);
    await controller.restore();
    final appearance = AppearanceController(
      store: InMemoryAppearanceStore(Appearance.dark),
    );
    addTearDown(appearance.dispose);
    await appearance.restore();

    await tester.pumpWidget(
      AikuApp(
        controller: controller,
        client: client,
        config: config,
        areaMemory: InMemoryAreaMemory(),
        appearance: appearance,
      ),
    );
    await tester.pump();

    expect(
      _brightnessAt(tester, find.byKey(LoginScreen.usernameFieldKey)),
      Brightness.light,
    );
  });
}
