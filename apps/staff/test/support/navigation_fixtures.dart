/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-31m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

/// Bodies of `GET /app/api/mobile/navigation`, shaped as aiku sends them.
library;

Map<String, Object?> _item(
  String key,
  String label, [
  String? icon,
  List<String> children = const [],
]) => {
  'key': key,
  'label': label,
  'icon': icon,
  'children': [
    for (final child in children) {'label': child, 'icon': null},
  ],
};

final _warehouseMenu = [
  _item('dashboard', 'Dashboard', 'fa-tachometer-alt'),
  _item('dispatching', 'Dispatching', 'fa-conveyor-belt-alt', [
    'Delivery notes',
    'Picking sessions',
    'Trolleys',
  ]),
];

Map<String, Object?> _shop(String slug, String name) => {
  'slug': slug,
  'name': name,
  'menu': [_item('dashboard', 'Dashboard', 'fa-tachometer-alt')],
};

/// Ancient Wisdom Ltd, with [shopCount] shops and the Europa Drive warehouse.
Map<String, Object?> ancientWisdom({int shopCount = 4}) => {
  'slug': 'aw',
  'name': 'Ancient Wisdom Ltd',
  'menu': [
    _item('dashboard', 'Dashboard', 'fa-tachometer-alt'),
    _item('procurement', 'Procurement', 'fa-box-usd'),
  ],
  'shops': [
    for (var number = 1; number <= shopCount; number++)
      _shop('shop-$number', 'Shop $number'),
  ],
  'warehouses': [
    {'slug': 'europa-drive', 'name': 'Europa Drive', 'menu': _warehouseMenu},
  ],
};

/// A group user's areas: AW Group, then [organisations].
Map<String, Object?> groupUserNavigation({
  bool tasks = false,
  bool tickets = true,
  bool chat = true,
  List<Map<String, Object?>>? organisations,
}) => {
  'tabs': {'tasks': tasks, 'tickets': tickets, 'chat': chat},
  'group': {
    'slug': 'aw-group',
    'name': 'AW Group',
    'menu': [
      _item('dashboard', 'Dashboard', 'fa-tachometer-alt'),
      _item('goods', 'Goods', 'fa-cloud-rainbow', [
        'Trade units',
        'Ingredients',
      ]),
      _item('sysadmin', 'Sysadmin', 'fa-users-cog'),
    ],
  },
  'organisations': organisations ?? [ancientWisdom()],
};

/// A picker's areas: no group, their organisation (which always has
/// Calendar) and its warehouse; Tickets only.
Map<String, Object?> pickerNavigation() => {
  'tabs': {'tasks': false, 'tickets': true, 'chat': false},
  'group': null,
  'organisations': [
    {
      'slug': 'aw',
      'name': 'Ancient Wisdom Ltd',
      'menu': [_item('calendar', 'Calendar', 'fa-calendar')],
      'shops': <Object?>[],
      'warehouses': [
        {
          'slug': 'europa-drive',
          'name': 'Europa Drive',
          'menu': _warehouseMenu,
        },
      ],
    },
  ],
};
