/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-27m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../api/navigation.dart';
import '../ui/accent_shades.dart';
import '../ui/aiku_palette.dart';
import '../ui/menu_icons.dart';

/// How many of an organisation's shops are listed before "N more shops".
const _shopsBeforeMore = 3;

/// The menu behind ☰: the current area at the top (tap it to choose another
/// area), then the menu aiku built for that area.
class AreaDrawer extends StatefulWidget {
  const AreaDrawer({
    super.key,
    required this.data,
    required this.area,
    required this.shades,
    required this.onSelectArea,
    required this.onOpenUnbuilt,
  });

  /// Keys for tests.
  static const areaCardKey = ValueKey('drawer.areaCard');
  static const areaListKey = ValueKey('drawer.areaList');
  static const menuKey = ValueKey('drawer.menu');
  static const captionKey = ValueKey('drawer.caption');

  /// The row for [area], in the area list or an organisation's menu.
  static ValueKey<String> areaKey(Area area) =>
      ValueKey('drawer.area.${area.key}');

  /// The menu entry aiku calls [key], e.g. `dispatching`.
  static ValueKey<String> menuItemKey(String key) =>
      ValueKey('drawer.menu.$key');

  /// "N more shops" under [organisation].
  static ValueKey<String> moreShopsKey(Area organisation) =>
      ValueKey('drawer.moreShops.${organisation.key}');

  /// Everything aiku lets the user open.
  final NavigationData data;

  /// The area being worked in.
  final Area area;

  /// The current theme's accent shades.
  final AccentShades shades;

  /// Switches to another area.
  final ValueChanged<Area> onSelectArea;

  /// Opens the "not on the app yet" screen for a page called [title].
  final ValueChanged<String> onOpenUnbuilt;

  @override
  State<AreaDrawer> createState() => _AreaDrawerState();
}

class _AreaDrawerState extends State<AreaDrawer> {
  bool _isChoosingArea = false;
  final _organisationsShowingAllShops = <String>{};

  void _toggleAreaList() => setState(() => _isChoosingArea = !_isChoosingArea);

  void _showAllShops(Area organisation) =>
      setState(() => _organisationsShowingAllShops.add(organisation.key));

  @override
  Widget build(BuildContext context) {
    final data = widget.data;
    final shopRows = _ShopRowBuilder(
      currentArea: widget.area,
      shades: widget.shades,
      showsAllShopsOf: (organisation) =>
          _organisationsShowingAllShops.contains(organisation.key),
      onShowAllShops: _showAllShops,
      onSelectArea: widget.onSelectArea,
    );

    return Drawer(
      backgroundColor: Colors.white,
      child: SafeArea(
        child: ListTileTheme(
          dense: true,
          iconColor: AikuPalette.label,
          textColor: AikuPalette.ink,
          selectedColor: widget.shades.deep,
          selectedTileColor: widget.shades.soft,
          contentPadding: const EdgeInsets.symmetric(horizontal: 12),
          shape: const RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(8)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const _DrawerBrand(),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: _AreaCard(
                  key: AreaDrawer.areaCardKey,
                  area: widget.area,
                  shades: widget.shades,
                  isOpen: _isChoosingArea,
                  onTap: data.hasChoice ? _toggleAreaList : null,
                ),
              ),
              const SizedBox(height: 12),
              const Divider(height: 1, indent: 12, endIndent: 12),
              const SizedBox(height: 4),
              Expanded(
                child: _isChoosingArea
                    ? _AreaList(
                        key: AreaDrawer.areaListKey,
                        data: data,
                        currentArea: widget.area,
                        shades: widget.shades,
                        shopRows: shopRows,
                        onSelectArea: widget.onSelectArea,
                      )
                    : _AreaMenu(
                        key: AreaDrawer.menuKey,
                        data: data,
                        area: widget.area,
                        shopRows: shopRows,
                        onOpenUnbuilt: widget.onOpenUnbuilt,
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Every area the user can open, to switch between.
class _AreaList extends StatelessWidget {
  const _AreaList({
    super.key,
    required this.data,
    required this.currentArea,
    required this.shades,
    required this.shopRows,
    required this.onSelectArea,
  });

  final NavigationData data;
  final Area currentArea;
  final AccentShades shades;
  final _ShopRowBuilder shopRows;
  final ValueChanged<Area> onSelectArea;

  @override
  Widget build(BuildContext context) {
    Widget row(Area area, {bool isIndented = false}) => _AreaRow(
      area: area,
      isCurrent: area.key == currentArea.key,
      isIndented: isIndented,
      shades: shades,
      onTap: () => onSelectArea(area),
    );

    return ListView(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 16),
      children: [
        const _SectionLabel('Choose an area'),
        if (data.group case final group?) row(group),
        for (final organisation in data.organisations) ...[
          row(organisation),
          ...shopRows.rowsFor(organisation),
          for (final warehouse in organisation.warehouses)
            row(warehouse, isIndented: true),
        ],
        const _Caption('Only areas you can access are listed.'),
      ],
    );
  }
}

/// The current area's menu, as aiku built it for this user.
class _AreaMenu extends StatelessWidget {
  const _AreaMenu({
    super.key,
    required this.data,
    required this.area,
    required this.shopRows,
    required this.onOpenUnbuilt,
  });

  final NavigationData data;
  final Area area;
  final _ShopRowBuilder shopRows;
  final ValueChanged<String> onOpenUnbuilt;

  @override
  Widget build(BuildContext context) {
    final menu = area.menu;
    // As on the web, an organisation's shops and warehouses come straight
    // after its dashboard.
    final splitAt = menu.isNotEmpty && menu.first.key == 'dashboard' ? 1 : 0;
    Widget entry(MenuItem item) =>
        _MenuEntry(item: item, onOpenUnbuilt: onOpenUnbuilt);

    return ListView(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 16),
      children: [
        for (final item in menu.take(splitAt)) entry(item),
        if (area.shops.isNotEmpty) ...[
          const _SectionLabel('Shops'),
          ...shopRows.rowsFor(area),
        ],
        if (area.warehouses.isNotEmpty) ...[
          const _SectionLabel('Warehouses'),
          for (final warehouse in area.warehouses)
            shopRows.areaRow(warehouse, isIndented: false),
        ],
        if (splitAt > 0 &&
            (area.shops.isNotEmpty || area.warehouses.isNotEmpty))
          const Divider(height: 24),
        for (final item in menu.skip(splitAt)) entry(item),
        _Caption(
          key: AreaDrawer.captionKey,
          [
            'Only what your permissions allow.',
            ?_bottomBarSentence(data.tabs),
            if (!data.hasChoice)
              'This account can only work in ${area.name}, so there is no '
                  'area to choose.',
          ].join(' '),
        ),
      ],
    );
  }

  static String? _bottomBarSentence(BottomTabs tabs) {
    final names = [
      if (tabs.tasks) 'Tasks',
      if (tabs.tickets) 'Tickets',
      if (tabs.chat) 'Chat',
    ];
    return switch (names) {
      [] => null,
      [final only] => '$only is on the bottom bar.',
      [...final others, final last] =>
        '${others.join(', ')} and $last are on the bottom bar.',
    };
  }
}

/// Builds the area rows that need the drawer's "more shops" state.
class const _ShopRowBuilder({
  required final Area currentArea,
  required final AccentShades shades,
  required final bool Function(Area organisation) showsAllShopsOf,
  required final ValueChanged<Area> onShowAllShops,
  required final ValueChanged<Area> onSelectArea,
}) {
  /// [organisation]'s shops, indented, with "N more shops" after the first
  /// few until it is tapped.
  List<Widget> rowsFor(Area organisation) {
    final shops = organisation.shops;
    final hidden = showsAllShopsOf(organisation)
        ? 0
        : shops.length - _shopsBeforeMore;
    return [
      for (final shop in hidden > 0 ? shops.take(_shopsBeforeMore) : shops)
        areaRow(shop, isIndented: true),
      if (hidden > 0)
        ListTile(
          key: AreaDrawer.moreShopsKey(organisation),
          contentPadding: const EdgeInsets.only(left: 36, right: 12),
          leading: Icon(Icons.expand_more, size: 20, color: shades.deep),
          title: Text(
            hidden == 1 ? '1 more shop' : '$hidden more shops',
            style: TextStyle(color: shades.deep),
          ),
          onTap: () => onShowAllShops(organisation),
        ),
    ];
  }

  /// The row for [area], ticked when it is the current one.
  Widget areaRow(Area area, {required bool isIndented}) => _AreaRow(
    area: area,
    isCurrent: area.key == currentArea.key,
    isIndented: isIndented,
    shades: shades,
    onTap: () => onSelectArea(area),
  );
}

class _AreaRow extends StatelessWidget {
  const _AreaRow({
    required this.area,
    required this.isCurrent,
    required this.isIndented,
    required this.shades,
    required this.onTap,
  });

  final Area area;
  final bool isCurrent;
  final bool isIndented;
  final AccentShades shades;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      key: AreaDrawer.areaKey(area),
      contentPadding: EdgeInsets.only(left: isIndented ? 36 : 12, right: 12),
      leading: Icon(areaIcon(area.type), size: 20),
      title: Text(area.name, maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: isIndented ? null : Text(areaTypeLabel(area.type)),
      selected: isCurrent,
      trailing: isCurrent
          ? Icon(Icons.check, size: 18, color: shades.accent)
          : null,
      onTap: onTap,
    );
  }
}

/// A menu entry; one with sub-sections expands to show them.
class _MenuEntry extends StatelessWidget {
  const _MenuEntry({required this.item, required this.onOpenUnbuilt});

  final MenuItem item;
  final ValueChanged<String> onOpenUnbuilt;

  @override
  Widget build(BuildContext context) {
    final key = AreaDrawer.menuItemKey(item.key);
    final icon = Icon(menuIcon(item.icon), size: 20);
    final title = Text(item.label);

    if (item.children.isEmpty) {
      return ListTile(
        key: key,
        leading: icon,
        title: title,
        onTap: () => onOpenUnbuilt(item.label),
      );
    }
    return ExpansionTile(
      key: key,
      leading: icon,
      title: title,
      dense: true,
      tilePadding: const EdgeInsets.symmetric(horizontal: 12),
      iconColor: AikuPalette.label,
      collapsedIconColor: AikuPalette.label,
      textColor: AikuPalette.ink,
      collapsedTextColor: AikuPalette.ink,
      shape: const Border(),
      collapsedShape: const Border(),
      childrenPadding: const EdgeInsets.only(left: 24),
      children: [
        for (final child in item.children)
          ListTile(
            leading: Icon(menuIcon(child.icon), size: 18),
            title: Text(child.label),
            onTap: () => onOpenUnbuilt(child.label),
          ),
      ],
    );
  }
}

/// The current area, tinted with the accent. Tapping it lists the other
/// areas when there are any.
class _AreaCard extends StatelessWidget {
  const _AreaCard({
    super.key,
    required this.area,
    required this.shades,
    required this.isOpen,
    required this.onTap,
  });

  final Area area;
  final AccentShades shades;
  final bool isOpen;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    const radius = BorderRadius.all(Radius.circular(10));
    return Material(
      color: shades.soft,
      borderRadius: radius,
      child: InkWell(
        borderRadius: radius,
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Icon(areaIcon(area.type), color: shades.deep),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      area.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                        color: AikuPalette.ink,
                      ),
                    ),
                    Text(
                      areaSubtitle(area),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AikuPalette.label,
                      ),
                    ),
                  ],
                ),
              ),
              if (onTap != null)
                Icon(
                  isOpen ? Icons.expand_less : Icons.unfold_more,
                  color: shades.deep,
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DrawerBrand extends StatelessWidget {
  const _DrawerBrand();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      child: Row(
        children: [
          Image.asset(
            'assets/images/invader.png',
            height: 28,
            semanticLabel: 'aiku logo',
          ),
          const SizedBox(width: 8),
          const Text(
            'aiku',
            style: TextStyle(
              fontFamily: AikuPalette.brandFontFamily,
              fontSize: 22,
              fontWeight: FontWeight.w600,
              fontVariations: [FontVariation.weight(600)],
              color: AikuPalette.ink,
              letterSpacing: -0.5,
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 16, 12, 6),
      child: Text(
        text,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: AikuPalette.label,
        ),
      ),
    );
  }
}

class _Caption extends StatelessWidget {
  const _Caption(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 20, 12, 0),
      child: Text(
        text,
        style: const TextStyle(fontSize: 12, color: AikuPalette.label),
      ),
    );
  }
}
