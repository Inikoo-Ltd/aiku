/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-31m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:aiku_mobile/api/navigation.dart';
import 'package:aiku_mobile/auth/form_errors.dart';
import 'package:aiku_mobile/auth/login_screen.dart';
import 'package:aiku_mobile/home/app_shell.dart';
import 'package:aiku_mobile/home/area_drawer.dart';
import 'package:aiku_mobile/home/area_homes.dart';
import 'package:aiku_mobile/home/dashboard_screen.dart';
import 'package:aiku_mobile/home/placeholder_screens.dart';
import 'package:aiku_mobile/home/profile_menu.dart';
import 'package:aiku_mobile/home/settings_screen.dart';
import 'package:aiku_mobile/ui/form_error_list.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;

import '../support/navigation_fixtures.dart';
import '../support/signed_in_app.dart';

/// The drawer row for the area with [areaKey] in [navigation].
Finder _areaRow(Map<String, Object?> navigation, String areaKey) => find.byKey(
  AreaDrawer.areaKey(
    NavigationData.fromJson(navigation)!.areaWithKey(areaKey)!,
  ),
);

String? _titleText(WidgetTester tester) => tester
    .widgetList<Text>(
      find.descendant(
        of: find.byKey(AppShell.titleKey),
        matching: find.byType(Text),
      ),
    )
    .first
    .data;

Future<void> _openDrawer(WidgetTester tester) async {
  await tester.tap(find.byKey(AppShell.menuButtonKey));
  await finishAnimations(tester);
}

void main() {
  testWidgets(
    'a group user opens on the group dashboard, titled with the group',
    (tester) async {
      await pumpSignedInApp(tester, fakeAiku(groupUserNavigation()));

      expect(find.byKey(GroupDashboard.heroKey), findsOneWidget);
      expect(_titleText(tester), 'AW Group');
    },
  );

  testWidgets('the bottom bar has Home and only the hubs aiku allows', (
    tester,
  ) async {
    await pumpSignedInApp(
      tester,
      fakeAiku(groupUserNavigation(tickets: true, chat: true)),
    );

    expect(find.byKey(ShellTab.home.key), findsOneWidget);
    expect(find.byKey(ShellTab.tasks.key), findsNothing);
    expect(find.byKey(ShellTab.tickets.key), findsOneWidget);
    expect(find.byKey(ShellTab.chat.key), findsOneWidget);
  });

  testWidgets('with no hub allowed there is no bottom bar', (tester) async {
    await pumpSignedInApp(
      tester,
      fakeAiku(groupUserNavigation(tickets: false, chat: false)),
    );

    expect(find.byKey(AppShell.bottomBarKey), findsNothing);
    expect(find.byKey(GroupDashboard.heroKey), findsOneWidget);
  });

  testWidgets('a hub tab shows its placeholder and Home goes back', (
    tester,
  ) async {
    await pumpSignedInApp(tester, fakeAiku(groupUserNavigation()));

    await tester.tap(find.byKey(ShellTab.tickets.key));
    await finishAnimations(tester);

    expect(find.byType(HubPlaceholder), findsOneWidget);
    expect(find.byKey(GroupDashboard.heroKey), findsNothing);

    await tester.tap(find.byKey(ShellTab.home.key));
    await finishAnimations(tester);

    expect(find.byKey(GroupDashboard.heroKey), findsOneWidget);
  });

  testWidgets('a picker opens on their warehouse home with Home and Tickets', (
    tester,
  ) async {
    await pumpSignedInApp(tester, fakeAiku(pickerNavigation()));

    expect(find.byKey(WarehouseHome.startPickingKey), findsOneWidget);
    expect(_titleText(tester), 'Europa Drive');
    expect(find.byKey(ShellTab.tickets.key), findsOneWidget);
    expect(find.byKey(ShellTab.chat.key), findsNothing);
  });

  testWidgets('a warehouse Home shows aiku’s stage counts for that warehouse', (
    tester,
  ) async {
    final paths = <String>[];
    final aiku = fakeAiku(pickerNavigation());
    await pumpSignedInApp(tester, (request) {
      paths.add(request.url.path);
      return aiku(request);
    });
    await tester.pump();

    expect(
      paths,
      contains('/app/api/mobile/dashboard/operations/europa-drive'),
    );
    final picking = tester
        .widgetList<Text>(
          find.descendant(
            of: find.byKey(const ValueKey('stage.picking')),
            matching: find.byType(Text),
          ),
        )
        .map((text) => text.data);
    expect(picking, containsAll(['Picking', '27']));
  });

  testWidgets('a warehouse the user may not see says so, with Try again', (
    tester,
  ) async {
    final aiku = fakeAiku(pickerNavigation());
    await pumpSignedInApp(
      tester,
      (request) => request.url.path.contains('/dashboard/operations')
          ? Future.value(http.Response('', 403))
          : aiku(request),
    );
    await tester.pump();

    expect(find.byKey(WarehouseHome.retryKey), findsOneWidget);
    expect(tester.widget<FormErrorList>(find.byType(FormErrorList)).messages, [
      FormErrors.forbiddenMessage,
    ]);
  });

  testWidgets('Start picking says picking is not on the app yet', (
    tester,
  ) async {
    await pumpSignedInApp(tester, fakeAiku(pickerNavigation()));

    await tester.tap(find.byKey(WarehouseHome.startPickingKey));
    await finishAnimations(tester);

    expect(find.byKey(NotOnMobileYetScreen.messageKey), findsOneWidget);
  });

  testWidgets('the remembered area opens first', (tester) async {
    await pumpSignedInApp(
      tester,
      fakeAiku(groupUserNavigation()),
      rememberedAreas: {'raul': 'warehouse:europa-drive'},
    );

    expect(find.byKey(WarehouseHome.startPickingKey), findsOneWidget);
  });

  testWidgets(
    'choosing an area in the drawer opens its Home and remembers it',
    (tester) async {
      final navigation = groupUserNavigation();
      final app = await pumpSignedInApp(tester, fakeAiku(navigation));

      await _openDrawer(tester);
      await tester.tap(find.byKey(AreaDrawer.areaCardKey));
      await tester.pump();
      await tester.tap(_areaRow(navigation, 'warehouse:europa-drive'));
      await finishAnimations(tester);

      expect(find.byKey(WarehouseHome.startPickingKey), findsOneWidget);
      expect(_titleText(tester), 'Europa Drive');
      expect(app.areaMemory.areas, {'raul': 'warehouse:europa-drive'});
    },
  );

  testWidgets('switching area goes back to the Home tab', (tester) async {
    final navigation = groupUserNavigation();
    await pumpSignedInApp(tester, fakeAiku(navigation));
    await tester.tap(find.byKey(ShellTab.chat.key));
    await finishAnimations(tester);

    await _openDrawer(tester);
    await tester.tap(find.byKey(AreaDrawer.areaCardKey));
    await tester.pump();
    await tester.tap(_areaRow(navigation, 'organisation:aw'));
    await finishAnimations(tester);

    expect(find.byType(HubPlaceholder), findsNothing);
    expect(find.byKey(AreaHomePreview.previewNoticeKey), findsOneWidget);
  });

  testWidgets('a menu entry the app has no screen for says so', (tester) async {
    await pumpSignedInApp(tester, fakeAiku(groupUserNavigation()));

    await _openDrawer(tester);
    await tester.tap(find.byKey(AreaDrawer.menuItemKey('sysadmin')));
    await finishAnimations(tester);

    expect(find.byKey(NotOnMobileYetScreen.messageKey), findsOneWidget);
    expect(
      tester
          .widget<NotOnMobileYetScreen>(find.byType(NotOnMobileYetScreen))
          .title,
      'Sysadmin',
    );
  });

  testWidgets('a menu entry with sub-sections opens to them', (tester) async {
    await pumpSignedInApp(tester, fakeAiku(groupUserNavigation()));

    await _openDrawer(tester);
    await tester.tap(find.byKey(AreaDrawer.menuItemKey('goods')));
    await finishAnimations(tester);
    await tester.tap(find.text('Trade units'));
    await finishAnimations(tester);

    expect(
      tester
          .widget<NotOnMobileYetScreen>(find.byType(NotOnMobileYetScreen))
          .title,
      'Trade units',
    );
  });

  testWidgets(
    'an organisation menu lists three shops, then the rest on request',
    (tester) async {
      final navigation = groupUserNavigation();
      await pumpSignedInApp(
        tester,
        fakeAiku(navigation),
        rememberedAreas: {'raul': 'organisation:aw'},
      );
      await _openDrawer(tester);

      expect(_areaRow(navigation, 'shop:shop-3'), findsOneWidget);
      expect(_areaRow(navigation, 'shop:shop-4'), findsNothing);
      expect(_areaRow(navigation, 'warehouse:europa-drive'), findsOneWidget);

      final organisation = NavigationData.fromJson(navigation)!
          .areaWithKey('organisation:aw')!;
      await tester.tap(find.byKey(AreaDrawer.moreShopsKey(organisation)));
      await tester.pump();
      await tester.tap(_areaRow(navigation, 'shop:shop-4'));
      await finishAnimations(tester);

      expect(_titleText(tester), 'Shop 4');
      expect(find.byKey(AreaHomePreview.previewNoticeKey), findsOneWidget);
    },
  );

  testWidgets('with only one area there is nothing to choose, and it says so', (
    tester,
  ) async {
    await pumpSignedInApp(
      tester,
      fakeAiku(groupUserNavigation(organisations: [])),
    );
    await _openDrawer(tester);

    await tester.tap(find.byKey(AreaDrawer.areaCardKey));
    await tester.pump();

    expect(find.byKey(AreaDrawer.areaListKey), findsNothing);
    final caption = tester.widget<Text>(
      find.descendant(
        of: find.byKey(AreaDrawer.captionKey),
        matching: find.byType(Text),
      ),
    );
    expect(caption.data, contains('Tickets and Chat are on the bottom bar.'));
    expect(caption.data, contains('so there is no area to choose.'));
  });

  testWidgets('the profile menu offers Theme, Settings and Log out', (
    tester,
  ) async {
    await pumpSignedInApp(tester, fakeAiku(groupUserNavigation()));

    await tester.tap(find.byKey(ProfileMenu.buttonKey));
    await finishAnimations(tester);

    expect(find.byKey(ProfileMenu.themeKey), findsOneWidget);
    expect(find.byKey(ProfileMenu.settingsKey), findsOneWidget);
    expect(find.byKey(ProfileMenu.signOutKey), findsOneWidget);
  });

  testWidgets('Settings shows the app version and the server', (tester) async {
    await pumpSignedInApp(tester, fakeAiku(groupUserNavigation()));

    await tester.tap(find.byKey(ProfileMenu.buttonKey));
    await finishAnimations(tester);
    await tester.tap(find.byKey(ProfileMenu.settingsKey));
    await finishAnimations(tester);

    expect(find.byKey(SettingsScreen.versionKey), findsOneWidget);
    expect(
      find.descendant(
        of: find.byKey(SettingsScreen.serverKey),
        matching: find.text(testServer),
      ),
      findsOneWidget,
    );
  });

  testWidgets('Log out signs out on aiku and on the phone', (tester) async {
    final requests = <http.Request>[];
    final app = await pumpSignedInApp(
      tester,
      fakeAiku(
        groupUserNavigation(),
        otherwise: (request) async {
          requests.add(request);
          return http.Response('', 204);
        },
      ),
    );

    await tester.tap(find.byKey(ProfileMenu.buttonKey));
    await finishAnimations(tester);
    await tester.tap(find.byKey(ProfileMenu.signOutKey));
    await finishAnimations(tester);

    expect(requests.single.url.path, '/app/api/mobile/logout');
    expect(app.store.session, isNull);
    expect(find.byKey(LoginScreen.usernameFieldKey), findsOneWidget);
  });

  testWidgets('a 401 while loading the menus signs the user out', (
    tester,
  ) async {
    final app = await pumpSignedInApp(
      tester,
      (_) async => http.Response('', 401),
    );
    await finishAnimations(tester);

    expect(app.store.session, isNull);
    expect(find.byKey(LoginScreen.usernameFieldKey), findsOneWidget);
  });

  testWidgets('a failed load says why, and Try again loads the menus', (
    tester,
  ) async {
    var isAikuUp = false;
    await pumpSignedInApp(
      tester,
      (request) async => isAikuUp
          ? fakeAiku(groupUserNavigation())(request)
          : http.Response('', 500),
    );

    expect(
      tester.widget<FormErrorList>(find.byType(FormErrorList)).messages,
      isNotEmpty,
    );

    isAikuUp = true;
    await tester.tap(find.byKey(AppShell.retryKey));
    await tester.pump();
    await tester.pump();

    expect(find.byKey(GroupDashboard.heroKey), findsOneWidget);
  });

  testWidgets(
    'coming back to the app picks up menus and theme changed in aiku',
    (tester) async {
      const amoledAccent = Color(0xFF8E44AD);
      final amoled = [
        '#000000',
        '#f2f2f2',
        '#424242',
        '#fde047',
        '#8e44ad',
        '#ffffff',
        '#fcd34d',
        '#374151',
      ];
      var navigation = groupUserNavigation(chat: false);
      var profile = profileJson();
      final app = await pumpSignedInApp(
        tester,
        (request) => fakeAiku(navigation, profile: profile)(request),
      );
      expect(find.byKey(ShellTab.chat.key), findsNothing);

      navigation = groupUserNavigation(chat: true);
      profile = profileJson(mobileAppTheme: amoled);
      for (final state in [
        AppLifecycleState.inactive,
        AppLifecycleState.hidden,
        AppLifecycleState.paused,
        AppLifecycleState.hidden,
        AppLifecycleState.inactive,
        AppLifecycleState.resumed,
      ]) {
        tester.binding.handleAppLifecycleStateChanged(state);
      }
      await tester.pump();
      await tester.pump();
      // MaterialApp fades between themes.
      await tester.pump(const Duration(milliseconds: 400));

      expect(find.byKey(ShellTab.chat.key), findsOneWidget);
      expect(find.byKey(GroupDashboard.heroKey), findsOneWidget);
      expect(app.store.session?.profile.mobileAppTheme, amoled);
      expect(
        Theme.of(tester.element(find.byKey(AppShell.titleKey)))
            .colorScheme
            .primary,
        amoledAccent,
      );
    },
  );

  testWidgets('opening the app refreshes the saved profile', (tester) async {
    final app = await pumpSignedInApp(
      tester,
      fakeAiku(
        groupUserNavigation(),
        profile: profileJson(contactName: 'Raul P'),
      ),
    );
    await tester.pump();

    expect(app.store.session?.profile.contactName, 'Raul P');
  });
}
