/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:async';
import 'dart:convert';

import 'package:aiku_mobile/home/dashboard_screen.dart';
import 'package:aiku_mobile/home/operations_overview.dart';
import 'package:aiku_mobile/home/profile_menu.dart';
import 'package:aiku_mobile/home/theme_screen.dart';
import 'package:aiku_mobile/ui/aiku_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;

import '../support/dashboard_fixtures.dart';
import '../support/navigation_fixtures.dart';
import '../support/signed_in_app.dart';

/// The texts shown inside what [finder] finds.
List<String?> _textsIn(WidgetTester tester, Finder finder) => tester
    .widgetList<Text>(find.descendant(of: finder, matching: find.byType(Text)))
    .map((text) => text.data)
    .toList();

List<String?> _textsInKey(WidgetTester tester, Key key) =>
    _textsIn(tester, find.byKey(key));

Future<void> _tapVisible(WidgetTester tester, Finder finder) async {
  await tester.ensureVisible(finder);
  await tester.pump();
  await tester.tap(finder);
  await finishAnimations(tester);
}

Future<void> _openSignedInDashboard(
  WidgetTester tester, {
  Map<String, Object?>? groupDashboard,
  Map<String, Object?>? operations,
}) async {
  await pumpSignedInApp(
    tester,
    fakeAiku(
      groupUserNavigation(),
      groupDashboard: groupDashboard,
      operations: operations,
    ),
  );
  await tester.pump();
}

void main() {
  testWidgets('Sales leads with the year’s sales against aiku’s target', (
    tester,
  ) async {
    await _openSignedInDashboard(tester);

    expect(
      _textsInKey(tester, GroupDashboard.heroKey),
      containsAll([
        'Sales, year to date',
        '£14,388,657.82',
        '79% of £18,318,083.60 target',
        '55 days left',
        '£66,865.51 a day needed to reach it',
      ]),
    );
  });

  testWidgets('orders show aiku’s groups, and a group opens to its steps', (
    tester,
  ) async {
    await _openSignedInDashboard(tester);

    expect(
      _textsInKey(tester, GroupDashboard.orderGroupKey('in_warehouse')),
      containsAll(['Warehouse', '486', '£117,498.16']),
    );

    await _tapVisible(
      tester,
      find.byKey(GroupDashboard.orderGroupKey('in_warehouse')),
    );

    expect(
      _textsInKey(tester, const ValueKey('dashboard.step.handling')),
      containsAll(['Picking', '26', '£19,609.52']),
    );
  });

  testWidgets('Shops lists the year’s five best sellers, most first', (
    tester,
  ) async {
    await _openSignedInDashboard(tester);

    for (final slug in ['uk', 'eu', 'pl', 'de', 'fr']) {
      expect(find.byKey(GroupDashboard.shopRowKey(slug)), findsOneWidget);
    }
    expect(find.byKey(GroupDashboard.shopRowKey('es')), findsNothing);
    expect(
      tester.getTopLeft(find.byKey(GroupDashboard.shopRowKey('uk'))).dy,
      lessThan(
        tester.getTopLeft(find.byKey(GroupDashboard.shopRowKey('eu'))).dy,
      ),
    );
    expect(
      _textsInKey(tester, GroupDashboard.shopsKey),
      containsAll(['All shops', '£12,673,634.51', '90,797 invoices']),
    );
  });

  testWidgets('choosing Today ranks the shops by today’s sales', (
    tester,
  ) async {
    await _openSignedInDashboard(tester);

    await _tapVisible(tester, find.byKey(GroupDashboard.periodKey('tdy')));

    expect(
      _textsInKey(tester, GroupDashboard.shopRowKey('eu')),
      containsAll(['AW Gifts Europe', '£450.00', '9 invoices']),
    );
    expect(
      tester.getTopLeft(find.byKey(GroupDashboard.shopRowKey('eu'))).dy,
      lessThan(
        tester.getTopLeft(find.byKey(GroupDashboard.shopRowKey('uk'))).dy,
      ),
    );
  });

  testWidgets('More offers aiku’s other periods', (tester) async {
    await _openSignedInDashboard(tester);

    await _tapVisible(tester, find.byKey(GroupDashboard.morePeriodsKey));
    await tester.tap(find.byKey(GroupDashboard.periodKey('lm')));
    await finishAnimations(tester);

    expect(
      _textsInKey(tester, GroupDashboard.morePeriodsKey),
      contains('Last Month'),
    );
  });

  testWidgets('All shops lists every shop for the period', (tester) async {
    await _openSignedInDashboard(tester);

    await _tapVisible(tester, find.byKey(GroupDashboard.allShopsKey));

    expect(find.byType(AllShopsScreen), findsOneWidget);
    expect(find.byKey(GroupDashboard.shopRowKey('es')), findsOneWidget);
    expect(find.byKey(GroupDashboard.shopRowKey('awa')), findsOneWidget);
  });

  testWidgets('figures show dashes until aiku answers', (tester) async {
    final answer = Completer<http.Response>();
    final aiku = fakeAiku(groupUserNavigation());
    await pumpSignedInApp(
      tester,
      (request) => request.url.path.endsWith('/dashboard/group')
          ? answer.future
          : aiku(request),
    );

    expect(_textsInKey(tester, GroupDashboard.heroKey), contains('—'));

    answer.complete(jsonResponse(groupDashboardJson(), 200));
    await tester.pump();
    await tester.pump();

    expect(
      _textsInKey(tester, GroupDashboard.heroKey),
      contains('£14,388,657.82'),
    );
  });

  testWidgets('an account aiku shows no group sales to sees why', (
    tester,
  ) async {
    await _openSignedInDashboard(
      tester,
      groupDashboard: groupDashboardJson(canViewSales: false),
    );

    expect(find.byKey(GroupDashboard.salesHiddenKey), findsOneWidget);
    expect(find.byKey(GroupDashboard.heroKey), findsNothing);
  });

  testWidgets('a failed load says why, and Try again loads the figures', (
    tester,
  ) async {
    var isAikuUp = false;
    final aiku = fakeAiku(groupUserNavigation());
    await pumpSignedInApp(
      tester,
      (request) => !isAikuUp && request.url.path.endsWith('/dashboard/group')
          ? Future.value(http.Response('', 500))
          : aiku(request),
    );
    await tester.pump();
    expect(find.byKey(GroupDashboard.heroKey), findsNothing);

    isAikuUp = true;
    await tester.tap(find.byKey(GroupDashboard.retryKey));
    await tester.pump();
    await tester.pump();

    expect(
      _textsInKey(tester, GroupDashboard.heroKey),
      contains('55 days left'),
    );
  });

  testWidgets(
    'Operations shows the open delivery notes by stage, per warehouse',
    (tester) async {
      final paths = <String>[];
      final aiku = fakeAiku(
        groupUserNavigation(),
        operations: operationsJson(
          warehouses: [('ed', 'Europa Drive'), ('par', 'Parkwood')],
        ),
      );
      await pumpSignedInApp(tester, (request) {
        paths.add(request.url.path);
        return aiku(request);
      });
      await tester.pump();

      await tester.tap(find.byKey(GroupDashboard.tabKey('operations')));
      await tester.pump();
      await tester.pump();

      expect(
        _textsInKey(tester, OperationsOverview.openTotalKey),
        containsAll(['Open delivery notes', '566']),
      );
      expect(
        _textsInKey(tester, const ValueKey('stage.dispatched')),
        containsAll(['Dispatched today', '1,450']),
      );

      await tester.tap(find.byKey(GroupDashboard.warehouseKey('par')));
      await tester.pump();
      await tester.pump();

      expect(paths, contains('/app/api/mobile/dashboard/operations/par'));
    },
  );

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
