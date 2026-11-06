/*
 * Author Louis Perez
 * Created on 06-11-2026-10h-17m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

/// Bodies of the mobile dashboard endpoints, shaped as aiku sends them
/// (figures taken from a local aiku on 6 Nov 2026).
library;

Map<String, Object?> _figures(String invoices, String sales, num value) => {
  'invoices': invoices,
  'sales': sales,
  'sales_value': value,
};

/// A shop with its year (also used as All) and today figures.
Map<String, Object?> _shop(
  String slug,
  String name, {
  required (String, String, num) year,
  (String, String, num) today = ('0', '£0.00', 0),
  bool isOpen = true,
}) => {
  'slug': slug,
  'name': name,
  'is_open': isOpen,
  'figures': {
    'all': _figures(year.$1, year.$2, year.$3),
    'ytd': _figures(year.$1, year.$2, year.$3),
    'tdy': _figures(today.$1, today.$2, today.$3),
  },
};

Map<String, Object?> _step(
  String key,
  String label,
  int count,
  String? amount,
) => {'key': key, 'label': label, 'count': count, 'amount': amount};

/// `GET /app/api/mobile/dashboard/group`; without [canViewSales] aiku sends
/// no sales.
Map<String, Object?> groupDashboardJson({bool canViewSales = true}) => {
  'currency_code': 'GBP',
  'intervals': canViewSales
      ? [
          {'value': 'all', 'label': 'All', 'short_label': 'All'},
          {'value': 'tdy', 'label': 'Today', 'short_label': 'Today'},
          {'value': 'wtd', 'label': 'Week to Date', 'short_label': 'WtD'},
          {'value': 'mtd', 'label': 'Month to Date', 'short_label': 'MtD'},
          {'value': 'ytd', 'label': 'Year to Date', 'short_label': 'YtD'},
          {'value': 'lm', 'label': 'Last Month', 'short_label': 'LM'},
        ]
      : <Object?>[],
  'sales': canViewSales
      ? {
          'pipeline': [
            {
              ..._step('in_basket', 'In Basket', 90716, '£14,940,316.56'),
              'steps': [
                _step('in_basket', 'In basket', 90716, '£14,940,316.56'),
              ],
            },
            {
              ..._step('in_warehouse', 'Warehouse', 486, '£117,498.16'),
              'steps': [
                _step('in_warehouse', 'Ready to be picked', 330, '£67,440.81'),
                _step('handling', 'Picking', 26, '£19,609.52'),
                _step('handling_blocked', 'Waiting', 41, '£12,343.05'),
                _step('picked', 'Picked', 61, '£11,149.11'),
                _step('packing', 'Packing', 7, '£1,924.70'),
                _step('packed', 'Packed', 21, '£5,030.97'),
              ],
            },
            {
              ..._step('returned', 'Returns', 108, null),
              'steps': [_step('returned', 'Returns', 108, null)],
            },
          ],
          'year_target': {
            'label': '2026 year to date',
            'sales_so_far': '£14,388,657.82',
            'target': '£18,318,083.60',
            'progress': 0.7855,
            'remaining_days': 55,
            'gap': '£3,677,603.28',
            'needed_per_day': '£66,865.51',
          },
          'shops': [
            _shop(
              'awa',
              'AW Advantage',
              year: ('0', '£0.00', 0),
              isOpen: false,
            ),
            _shop(
              'uk',
              'Ancient Wisdom',
              year: ('16,635', '£3,447,516.79', 3447516.79),
              today: ('3', '£120.00', 120),
            ),
            _shop(
              'eu',
              'AW Gifts Europe',
              year: ('3,485', '£1,424,570.61', 1424570.61),
              today: ('9', '£450.00', 450),
            ),
            _shop(
              'pl',
              'AW Gifts Polska',
              year: ('2,000', '£900,000.00', 900000),
            ),
            _shop(
              'de',
              'AW Dropship Germany',
              year: ('1,900', '£800,000.00', 800000),
            ),
            _shop('fr', 'AW France', year: ('1,800', '£700,000.00', 700000)),
            _shop('es', 'AW Spain', year: ('1,700', '£600,000.00', 600000)),
          ],
          'totals': {
            'all': _figures('90,797', '£12,673,634.51', 12673634.51),
            'ytd': _figures('90,797', '£12,673,634.51', 12673634.51),
            'tdy': _figures('12', '£570.00', 570),
          },
        }
      : null,
};

/// `GET /app/api/mobile/dashboard/operations[/{warehouse}]`, for the
/// [warehouses] given as (slug, name).
Map<String, Object?> operationsJson({
  List<(String, String)> warehouses = const [('europa-drive', 'Europa Drive')],
  int toAssign = 333,
}) => {
  'generated_at': '2026-11-06T10:00:00+00:00',
  'warehouses': [
    for (final (slug, name) in warehouses)
      {'slug': slug, 'name': name, 'organisation': 'Ancient Wisdom Ltd'},
  ],
  'stages': [
    {'key': 'unassigned', 'label': 'To assign', 'count': toAssign},
    {'key': 'queued', 'label': 'Queued', 'count': 0},
    {'key': 'picking', 'label': 'Picking', 'count': 27},
    {'key': 'blocked', 'label': 'Blocked', 'count': 43},
    {'key': 'packing', 'label': 'Packing', 'count': 76},
    {'key': 'packed', 'label': 'Packed', 'count': 71},
    {'key': 'finalised', 'label': 'Waiting for dispatch', 'count': 16},
    {'key': 'dispatched', 'label': 'Dispatched today', 'count': 1450},
  ],
};
