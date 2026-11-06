/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-13m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../api/navigation.dart';

/// Material stand-ins for the Font Awesome icons aiku's web menus name.
const _menuIcons = <String, IconData>{
  'fa-city': Icons.location_city_outlined,
  'fa-tasks': Icons.checklist,
  'fa-lightbulb-on': Icons.lightbulb_outline,
  'fa-atom': Icons.science_outlined,
  'fa-atom-alt': Icons.science_outlined,
  'fa-octopus-deploy': Icons.hub_outlined,
  'fa-cloud-rainbow': Icons.inventory_2_outlined,
  'fa-rainbow': Icons.category_outlined,
  'fa-box-usd': Icons.local_shipping_outlined,
  'fa-building': Icons.apartment,
  'fa-store': Icons.storefront_outlined,
  'fa-store-alt': Icons.storefront_outlined,
  'fa-globe': Icons.public,
  'fa-mountains': Icons.landscape_outlined,
  'fa-bullhorn': Icons.campaign_outlined,
  'fa-life-ring': Icons.support,
  'fa-project-diagram': Icons.account_tree_outlined,
  'fa-comment-alt': Icons.chat_bubble_outline,
  'fa-comments': Icons.forum_outlined,
  'fa-inbox': Icons.inbox_outlined,
  'fa-robot': Icons.smart_toy_outlined,
  'fa-server': Icons.dns_outlined,
  'fa-users-cog': Icons.manage_accounts_outlined,
  'fa-user-circle': Icons.account_circle_outlined,
  'fa-warehouse': Icons.warehouse_outlined,
  'fa-warehouse-alt': Icons.warehouse_outlined,
  'fa-abacus': Icons.calculate_outlined,
  'fa-calendar': Icons.calendar_today_outlined,
  'fa-calendar-alt': Icons.calendar_month_outlined,
  'fa-chart-line': Icons.show_chart,
  'fa-chart-network': Icons.insights_outlined,
  'fa-tachometer-alt': Icons.speed,
  'fa-tachometer-alt-fast': Icons.speed,
  'fa-list': Icons.list,
  'fa-columns': Icons.view_kanban_outlined,
  'fa-books': Icons.menu_book_outlined,
  'fa-code-branch': Icons.call_split,
  'fa-truck': Icons.local_shipping_outlined,
  'fa-dolly': Icons.move_to_inbox_outlined,
  'fa-pallet': Icons.pallet,
  'fa-inventory': Icons.inventory_outlined,
  'fa-map-signs': Icons.signpost_outlined,
  'fa-person-dolly': Icons.person_outline,
  'fa-people-arrows': Icons.people_outline,
  'fa-user-hard-hat': Icons.engineering_outlined,
  'fa-users': Icons.groups_outlined,
  'fa-industry': Icons.factory_outlined,
  'fa-shopping-cart': Icons.shopping_cart_outlined,
  'fa-tags': Icons.sell_outlined,
  'fa-barcode': Icons.qr_code_scanner,
};

/// The icon for a menu entry, falling back to a plain folder.
IconData menuIcon(String? faName) =>
    _menuIcons[faName] ?? Icons.folder_open_outlined;

/// The icon for an area of [type].
IconData areaIcon(AreaType type) => switch (type) {
  AreaType.group => Icons.location_city_outlined,
  AreaType.organisation => Icons.apartment,
  AreaType.shop => Icons.storefront_outlined,
  AreaType.warehouse => Icons.warehouse_outlined,
};

/// What an area of [type] is called on screen.
String areaTypeLabel(AreaType type) => switch (type) {
  AreaType.group => 'Group',
  AreaType.organisation => 'Organisation',
  AreaType.shop => 'Shop',
  AreaType.warehouse => 'Warehouse',
};

/// The line under an area's name, e.g. "Warehouse · Ancient Wisdom Ltd".
String areaSubtitle(Area area) => switch (area.organisationName) {
  final String organisation => '${areaTypeLabel(area.type)} · $organisation',
  null => areaTypeLabel(area.type),
};
