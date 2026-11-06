/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-27m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../api/navigation.dart';
import '../ui/accent_shades.dart';
import '../ui/aiku_buttons.dart';
import '../ui/aiku_palette.dart';
import '../ui/menu_icons.dart';
import '../ui/preview_notice.dart';
import 'dashboard_screen.dart';

/// The Home tab, which follows the area: the group dashboard, a warehouse's
/// picking home, or an organisation's or shop's home.
class AreaHome extends StatelessWidget {
  const AreaHome({
    super.key,
    required this.area,
    required this.shades,
    required this.onOpenUnbuilt,
  });

  /// The area being worked in.
  final Area area;

  /// The current theme's accent shades.
  final AccentShades shades;

  /// Opens the "not on the app yet" screen for a page called [title].
  final ValueChanged<String> onOpenUnbuilt;

  @override
  Widget build(BuildContext context) {
    return switch (area.type) {
      AreaType.group => GroupDashboard(shades: shades),
      AreaType.warehouse => WarehouseHome(
        shades: shades,
        onStartPicking: () => onOpenUnbuilt('Picking'),
      ),
      AreaType.organisation || AreaType.shop => AreaHomePreview(
        key: ValueKey(area.key),
        area: area,
        shades: shades,
      ),
    };
  }
}

/// A warehouse's Home: where pickers start.
///
/// Layout only for now: the counts show a dash and picking itself is not on
/// the app yet.
class WarehouseHome extends StatelessWidget {
  const WarehouseHome({
    super.key,
    required this.shades,
    required this.onStartPicking,
  });

  /// Keys for tests.
  static const previewNoticeKey = ValueKey('warehouseHome.previewNotice');
  static const countsKey = ValueKey('warehouseHome.counts');
  static const startPickingKey = ValueKey('warehouseHome.startPicking');
  static const sessionsKey = ValueKey('warehouseHome.sessions');

  /// The current theme's accent shades.
  final AccentShades shades;

  /// Called by "Start picking".
  final VoidCallback onStartPicking;

  static const _counts = [
    (label: 'To pick', icon: Icons.inventory_outlined),
    (label: 'Being picked', icon: Icons.shopping_basket_outlined),
    (label: 'To pack', icon: Icons.inventory_2_outlined),
    (label: 'Dispatched today', icon: Icons.send_outlined),
  ];

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        PreviewNotice(
          key: previewNoticeKey,
          shades: shades,
          text:
              'Layout preview. Counts and picking sessions appear once '
              'picking is connected to aiku.',
        ),
        const SizedBox(height: 20),
        const _HomeSectionTitle('Dispatching'),
        const SizedBox(height: 8),
        SizedBox(
          key: countsKey,
          height: 104,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: _counts.length,
            separatorBuilder: (_, _) => const SizedBox(width: 10),
            itemBuilder: (context, index) {
              final (:label, :icon) = _counts[index];
              return StatTile(label: label, icon: icon);
            },
          ),
        ),
        const SizedBox(height: 20),
        SizedBox(
          height: 44,
          child: BorderedIconButton(
            key: startPickingKey,
            label: 'Start picking',
            icon: Icons.qr_code_scanner,
            iconColor: shades.accent,
            onPressed: onStartPicking,
          ),
        ),
        const SizedBox(height: 24),
        const _HomeSectionTitle('Your picking sessions'),
        const SizedBox(height: 8),
        const NotConnectedCard(
          key: sessionsKey,
          icon: Icons.view_list_outlined,
          text: 'Your picking sessions will appear here.',
        ),
      ],
    );
  }
}

/// An organisation's or shop's Home, until those are built.
class AreaHomePreview extends StatelessWidget {
  const AreaHomePreview({super.key, required this.area, required this.shades});

  /// Key for tests.
  static const previewNoticeKey = ValueKey('areaHome.previewNotice');

  /// The organisation or shop.
  final Area area;

  /// The current theme's accent shades.
  final AccentShades shades;

  @override
  Widget build(BuildContext context) {
    final kind = areaTypeLabel(area.type).toLowerCase();
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        PreviewNotice(
          key: previewNoticeKey,
          shades: shades,
          text:
              "Layout preview. This $kind's Home is coming to the app; "
              'its pages are in the menu.',
        ),
        const SizedBox(height: 16),
        NotConnectedCard(
          icon: areaIcon(area.type),
          text: "${area.name}'s figures will appear here.",
        ),
      ],
    );
  }
}

class _HomeSectionTitle extends StatelessWidget {
  const _HomeSectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w600,
        color: AikuPalette.ink,
      ),
    );
  }
}
