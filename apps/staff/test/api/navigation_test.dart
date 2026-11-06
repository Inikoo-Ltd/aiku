/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-31m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:aiku_mobile/api/navigation.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/navigation_fixtures.dart';

void main() {
  test('reads the areas in selector order, with menus and sub-sections', () {
    final data = NavigationData.fromJson(groupUserNavigation())!;

    expect(data.allAreas.map((area) => area.key), [
      'group:aw-group',
      'organisation:aw',
      'shop:shop-1',
      'shop:shop-2',
      'shop:shop-3',
      'shop:shop-4',
      'warehouse:europa-drive',
    ]);
    final goods = data.group!.menu.singleWhere((item) => item.key == 'goods');
    expect((goods.label, goods.icon), ('Goods', 'fa-cloud-rainbow'));
    expect(goods.children.map((child) => child.label), [
      'Trade units',
      'Ingredients',
    ]);
  });

  test('shops and warehouses carry their organisation name', () {
    final data = NavigationData.fromJson(groupUserNavigation())!;

    final warehouse = data.areaWithKey('warehouse:europa-drive')!;

    expect(warehouse.type, AreaType.warehouse);
    expect(warehouse.organisationName, 'Ancient Wisdom Ltd');
  });

  test('reads which bottom-bar hubs aiku allows', () {
    final data = NavigationData.fromJson(
      groupUserNavigation(tasks: true, tickets: false, chat: true),
    )!;

    expect(
      (data.tabs.tasks, data.tabs.tickets, data.tabs.chat),
      (true, false, true),
    );
  });

  test('skips menu entries and areas it cannot read', () {
    final data = NavigationData.fromJson({
      'tabs': <String, Object?>{},
      'group': null,
      'organisations': [
        {
          'slug': 'aw',
          'name': 'Ancient Wisdom Ltd',
          'menu': [
            {'key': 'dashboard', 'label': 'Dashboard'},
            {'label': 'No key'},
            'not a map',
          ],
          'shops': [
            {'slug': 'no-name', 'menu': <Object?>[]},
          ],
        },
        {'name': 'No slug', 'menu': <Object?>[]},
      ],
    })!;

    expect(data.allAreas.map((area) => area.key), ['organisation:aw']);
    expect(data.organisations.single.menu.map((item) => item.key), [
      'dashboard',
    ]);
  });

  test('a body without tabs or organisations is not navigation', () {
    expect(NavigationData.fromJson({'organisations': <Object?>[]}), isNull);
    expect(NavigationData.fromJson({'tabs': <String, Object?>{}}), isNull);
    expect(NavigationData.fromJson(['not', 'a', 'map']), isNull);
  });

  test('a group user starts in the group', () {
    final data = NavigationData.fromJson(groupUserNavigation())!;

    expect(data.defaultArea?.key, 'group:aw-group');
  });

  test('a picker without the group starts in their warehouse', () {
    final data = NavigationData.fromJson(pickerNavigation())!;

    expect(data.defaultArea?.key, 'warehouse:europa-drive');
  });

  test('a user with no area has no default and no choice', () {
    final data = NavigationData.fromJson({
      'tabs': <String, Object?>{},
      'group': null,
      'organisations': <Object?>[],
    })!;

    expect(data.defaultArea, isNull);
    expect(data.hasChoice, isFalse);
  });

  test('an area that is gone is not found by its key', () {
    final data = NavigationData.fromJson(pickerNavigation())!;

    expect(data.areaWithKey('shop:closed-shop'), isNull);
    expect(data.areaWithKey(null), isNull);
  });
}
