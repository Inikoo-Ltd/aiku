/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-27m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:async';

import 'package:flutter/material.dart';

import '../api/dashboard.dart';
import '../api/navigation.dart';
import '../ui/aiku_buttons.dart';
import '../ui/app_colours.dart';
import '../ui/dashboard_kit.dart';
import '../ui/menu_icons.dart';
import '../ui/preview_notice.dart';
import 'dashboard_loader.dart';
import 'dashboard_screen.dart';
import 'operations_overview.dart';

/// The Home tab, which follows the area: the group dashboard, a warehouse's
/// picking home, or an organisation's or shop's home.
class AreaHome extends StatelessWidget {
  const AreaHome({
    super.key,
    required this.area,
    required this.source,
    required this.onUnauthorised,
    required this.onOpenUnbuilt,
  });

  /// The area being worked in.
  final Area area;

  /// Where the dashboard figures come from.
  final DashboardSource source;

  /// Called when aiku answers 401.
  final Future<void> Function() onUnauthorised;

  /// Opens the "not on the app yet" screen for a page called [title].
  final ValueChanged<String> onOpenUnbuilt;

  @override
  Widget build(BuildContext context) {
    return switch (area.type) {
      AreaType.group => GroupDashboard(
        key: ValueKey(area.key),
        source: source,
        onUnauthorised: onUnauthorised,
      ),
      AreaType.warehouse => WarehouseHome(
        key: ValueKey(area.key),
        warehouseSlug: area.slug,
        source: source,
        onUnauthorised: onUnauthorised,
        onStartPicking: () => onOpenUnbuilt('Picking'),
      ),
      AreaType.organisation ||
      AreaType.shop => AreaHomePreview(key: ValueKey(area.key), area: area),
    };
  }
}

/// A warehouse's Home: where pickers start.
///
/// The open delivery notes lead on the theme band; the stage counts are
/// aiku's, live. Picking itself and the picking sessions are not on the app
/// yet.
class WarehouseHome extends StatefulWidget {
  const WarehouseHome({
    super.key,
    required this.warehouseSlug,
    required this.source,
    required this.onUnauthorised,
    required this.onStartPicking,
  });

  /// Keys for tests.
  static const noticeKey = ValueKey('warehouseHome.notice');
  static const countsKey = ValueKey('warehouseHome.counts');
  static const retryKey = ValueKey('warehouseHome.retry');
  static const startPickingKey = ValueKey('warehouseHome.startPicking');
  static const sessionsKey = ValueKey('warehouseHome.sessions');

  /// The warehouse, as aiku's navigation names it.
  final String warehouseSlug;

  /// Where the counts come from.
  final DashboardSource source;

  /// Called when aiku answers 401.
  final Future<void> Function() onUnauthorised;

  /// Called by "Start picking".
  final VoidCallback onStartPicking;

  @override
  State<WarehouseHome> createState() => _WarehouseHomeState();
}

class _WarehouseHomeState extends State<WarehouseHome> {
  late final DashboardLoader<OperationsData> _counts;
  late final AppLifecycleListener _lifecycle;

  @override
  void initState() {
    super.initState();
    _counts = DashboardLoader(
      fetch: () =>
          widget.source.operations(warehouseSlug: widget.warehouseSlug),
      onUnauthorised: widget.onUnauthorised,
    );
    _lifecycle = AppLifecycleListener(onResume: _counts.load);
    // The figures show placeholders until this answers.
    unawaited(_counts.load());
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    _counts.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      color: AppColours.of(context).accentText,
      onRefresh: _counts.load,
      child: ListenableBuilder(
        key: WarehouseHome.countsKey,
        listenable: _counts,
        builder: (context, _) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.zero,
          children: [
            ThemeBand(
              children: [
                const SizedBox(height: 12),
                OperationsOverview(
                  state: _counts.state,
                  part: OperationsPart.openTotal,
                  onRetry: _counts.load,
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  OperationsOverview(
                    state: _counts.state,
                    part: OperationsPart.stages,
                    onRetry: _counts.load,
                    retryKey: WarehouseHome.retryKey,
                  ),
                  const SizedBox(height: 20),
                  _StartPicking(onPressed: widget.onStartPicking),
                  const SizedBox(height: 10),
                  const PreviewNotice(
                    key: WarehouseHome.noticeKey,
                    text: 'Picking itself is not on the app yet.',
                  ),
                  const SizedBox(height: 32),
                  const SectionHeader('Your picking sessions'),
                  const NotConnectedCard(
                    key: WarehouseHome.sessionsKey,
                    icon: Icons.view_list_outlined,
                    text: 'Your picking sessions will appear here.',
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StartPicking extends StatelessWidget {
  const _StartPicking({required this.onPressed});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 44,
      child: BorderedIconButton(
        key: WarehouseHome.startPickingKey,
        label: 'Start picking',
        icon: Icons.qr_code_scanner,
        iconColor: AppColours.of(context).accentText,
        onPressed: onPressed,
      ),
    );
  }
}

/// An organisation's or shop's Home, until those are built.
class AreaHomePreview extends StatelessWidget {
  const AreaHomePreview({super.key, required this.area});

  /// Key for tests.
  static const previewNoticeKey = ValueKey('areaHome.previewNotice');

  /// The organisation or shop.
  final Area area;

  @override
  Widget build(BuildContext context) {
    final kind = areaTypeLabel(area.type).toLowerCase();
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        ThemeBand(
          children: [
            const SizedBox(height: 12),
            HeroFigure(label: areaTypeLabel(area.type), value: area.name),
          ],
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              PreviewNotice(
                key: previewNoticeKey,
                text:
                    "This $kind's Home is coming to the app; its pages are in "
                    'the menu.',
              ),
              NotConnectedCard(
                icon: areaIcon(area.type),
                text: "${area.name}'s figures will appear here.",
              ),
            ],
          ),
        ),
      ],
    );
  }
}
