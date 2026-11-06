/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-13m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

/// The kinds of area a user can work in.
enum AreaType { group, organisation, shop, warehouse }

/// One entry of an area's menu, as aiku's web sidebar would show it.
class const MenuItem({
  required final String key,
  required final String label,
  required final String? icon,
  required final List<MenuChild> children,
});

/// A sub-section of a [MenuItem] (the web's top-menu tabs).
class const MenuChild({
  required final String label,
  required final String? icon,
});

/// A place the user can switch to: the group, an organisation, a shop or a
/// warehouse, with the menu aiku built for it.
class const Area({
  required final AreaType type,
  required final String slug,
  required final String name,
  required final List<MenuItem> menu,
  final String? organisationName,
  final List<Area> shops = const [],
  final List<Area> warehouses = const [],
}) {
  /// Identifies the area across app launches, e.g. `warehouse:europa-drive`.
  String get key => '${type.name}:$slug';
}

/// Which bottom-bar hubs aiku lets the user open.
class const BottomTabs({
  required final bool tasks,
  required final bool tickets,
  required final bool chat,
});

/// The areas and menus aiku built for the signed-in user.
class const NavigationData({
  required final BottomTabs tabs,
  required final Area? group,
  required final List<Area> organisations,
}) {
  /// Reads `GET /app/api/mobile/navigation`. Returns null for anything else.
  static NavigationData? fromJson(Object? json) {
    if (json
        case {
              'tabs': final Map<String, Object?> tabs,
              'organisations': final List<Object?> organisations,
            } &&
            final Map<String, Object?> body) {
      return NavigationData(
        tabs: BottomTabs(
          tasks: tabs['tasks'] == true,
          tickets: tabs['tickets'] == true,
          chat: tabs['chat'] == true,
        ),
        group: _area(body['group'], AreaType.group),
        organisations: [
          for (final organisation in organisations)
            ?_organisation(organisation),
        ],
      );
    }
    return null;
  }

  /// Every area, in the order the area selector lists them.
  List<Area> get allAreas => [
    ?group,
    for (final organisation in organisations) ...[
      organisation,
      ...organisation.shops,
      ...organisation.warehouses,
    ],
  ];

  /// Whether there is more than one area to choose from.
  bool get hasChoice => allAreas.length > 1;

  /// The area with [key], if the user still has it.
  Area? areaWithKey(String? key) {
    for (final area in allAreas) {
      if (area.key == key) {
        return area;
      }
    }
    return null;
  }

  /// Where to start when nothing is remembered: the group, otherwise the
  /// first warehouse (pickers), otherwise the first area there is.
  Area? get defaultArea {
    if (group case final group?) {
      return group;
    }
    for (final organisation in organisations) {
      if (organisation.warehouses.isNotEmpty) {
        return organisation.warehouses.first;
      }
    }
    final areas = allAreas;
    return areas.isEmpty ? null : areas.first;
  }

  static Area? _organisation(Object? json) {
    final organisation = _area(json, AreaType.organisation);
    if (organisation == null || json is! Map<String, Object?>) {
      return null;
    }
    List<Area> children(Object? list, AreaType type) => [
      if (list is List<Object?>)
        for (final child in list)
          ?_area(child, type, organisationName: organisation.name),
    ];
    return Area(
      type: AreaType.organisation,
      slug: organisation.slug,
      name: organisation.name,
      menu: organisation.menu,
      shops: children(json['shops'], AreaType.shop),
      warehouses: children(json['warehouses'], AreaType.warehouse),
    );
  }

  static Area? _area(Object? json, AreaType type, {String? organisationName}) {
    if (json case {
      'slug': final String slug,
      'name': final String name,
      'menu': final List<Object?> menu,
    }) {
      return Area(
        type: type,
        slug: slug,
        name: name,
        organisationName: organisationName,
        menu: [for (final item in menu) ?_menuItem(item)],
      );
    }
    return null;
  }

  static MenuItem? _menuItem(Object? json) {
    if (json
        case {'key': final String key, 'label': final String label} &&
            final Map<String, Object?> item) {
      return MenuItem(
        key: key,
        label: label,
        icon: switch (item['icon']) {
          final String icon => icon,
          _ => null,
        },
        children: [
          if (item['children'] case final List<Object?> children)
            for (final child in children)
              if (child case {'label': final String childLabel})
                MenuChild(
                  label: childLabel,
                  icon: switch (child['icon']) {
                    final String icon => icon,
                    _ => null,
                  },
                ),
        ],
      );
    }
    return null;
  }
}
