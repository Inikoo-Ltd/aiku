/*
 * Author Louis Perez
 * Created on 06-11-2026-10h-17m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:aiku_mobile/api/dashboard.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/dashboard_fixtures.dart';

void main() {
  test('reads the group dashboard’s periods, target and shops', () {
    final data = GroupDashboardData.fromJson(groupDashboardJson())!;
    final sales = data.sales!;

    expect(data.currencyCode, 'GBP');
    expect(data.intervals.map((interval) => interval.value), [
      'all',
      'tdy',
      'wtd',
      'mtd',
      'ytd',
      'lm',
    ]);
    expect(sales.yearTarget?.progress, 0.7855);
    expect(sales.yearTarget?.remainingDays, 55);
    expect(sales.shops.first.isOpen, isFalse);
    expect(sales.shops[1].figures['tdy']?.sales, '£120.00');
    expect(sales.totals['ytd']?.salesValue, 12673634.51);
  });

  test('reads the pipeline as aiku’s groups, each with its steps', () {
    final pipeline = GroupDashboardData.fromJson(groupDashboardJson())!
        .sales!
        .pipeline;

    expect(pipeline.map((group) => (group.key, group.count)), [
      ('in_basket', 90716),
      ('in_warehouse', 486),
      ('returned', 108),
    ]);
    final warehouse = pipeline[1];
    expect(warehouse.amount, '£117,498.16');
    expect(warehouse.steps.map((step) => step.key), [
      'in_warehouse',
      'handling',
      'handling_blocked',
      'picked',
      'packing',
      'packed',
    ]);
    expect(pipeline.last.amount, isNull);
  });

  test('a user aiku shows no sales to has no sales part', () {
    final data = GroupDashboardData.fromJson(
      groupDashboardJson(canViewSales: false),
    )!;

    expect(data.sales, isNull);
    expect(data.intervals, isEmpty);
  });

  test('a year without a target reads as no target and no progress', () {
    final json = groupDashboardJson();
    final sales = json['sales']! as Map<String, Object?>;
    sales['year_target'] = {
      'label': '2026 year to date',
      'sales_so_far': '£10.00',
      'target': null,
      'progress': null,
      'remaining_days': 55,
      'gap': null,
      'needed_per_day': null,
    };

    final target = GroupDashboardData.fromJson(json)!.sales!.yearTarget!;

    expect(
      (target.target, target.progress, target.neededPerDay),
      (null, null, null),
    );
  });

  test('reads the operations warehouses and stages in aiku’s order', () {
    final data = OperationsData.fromJson(
      operationsJson(warehouses: [('ed', 'Europa Drive'), ('par', 'Parkwood')]),
    )!;

    expect(
      data.warehouses.map((warehouse) => (warehouse.slug, warehouse.name)),
      [('ed', 'Europa Drive'), ('par', 'Parkwood')],
    );
    expect(data.stages.map((stage) => stage.key), [
      'unassigned',
      'queued',
      'picking',
      'blocked',
      'packing',
      'packed',
      'finalised',
      'dispatched',
    ]);
    expect(data.stages.first.label, 'To assign');
  });

  test('bodies of the wrong shape are not dashboards', () {
    expect(GroupDashboardData.fromJson({'sales': null}), isNull);
    expect(OperationsData.fromJson({'pipeline': <Object?>[]}), isNull);
    expect(OperationsData.fromJson(['not', 'a', 'map']), isNull);
  });
}
