/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../ui/accent_shades.dart';
import '../ui/aiku_palette.dart';
import '../ui/preview_notice.dart';

/// The group's Home, laid out after aiku's web Group dashboard.
///
/// Layout only for now: every figure shows a dash until the dashboard is
/// connected to aiku. Nothing on it is made up.
class GroupDashboard extends StatefulWidget {
  const GroupDashboard({super.key, required this.shades});

  /// Keys for tests.
  static const previewNoticeKey = ValueKey('dashboard.previewNotice');
  static const tabsKey = ValueKey('dashboard.tabs');
  static const targetKey = ValueKey('dashboard.target');
  static const pipelineKey = ValueKey('dashboard.pipeline');
  static const periodsKey = ValueKey('dashboard.periods');
  static const shopsKey = ValueKey('dashboard.shops');

  /// The current theme's accent shades.
  final AccentShades shades;

  @override
  State<GroupDashboard> createState() => _GroupDashboardState();
}

enum _DashboardTab {
  sales('Sales', Icons.show_chart),
  stock('Stock', Icons.inventory_2_outlined),
  operations('Operations', Icons.local_shipping_outlined);

  const _DashboardTab(this.label, this.icon);

  final String label;
  final IconData icon;
}

const _periods = [
  'All',
  'Today',
  'Yesterday',
  'Week to date',
  'Month to date',
  'Year to date',
];

class _GroupDashboardState extends State<GroupDashboard> {
  _DashboardTab _tab = _DashboardTab.sales;
  String _period = _periods.first;

  @override
  Widget build(BuildContext context) {
    final shades = widget.shades;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        PreviewNotice(
          key: GroupDashboard.previewNoticeKey,
          shades: shades,
          text:
              'Layout preview. Figures appear once the dashboard is '
              'connected to aiku.',
        ),
        const SizedBox(height: 16),
        SegmentedButton<_DashboardTab>(
          key: GroupDashboard.tabsKey,
          segments: [
            for (final tab in _DashboardTab.values)
              ButtonSegment(
                value: tab,
                label: Text(tab.label),
                icon: Icon(tab.icon, size: 18),
              ),
          ],
          selected: {_tab},
          showSelectedIcon: false,
          onSelectionChanged: (selection) =>
              setState(() => _tab = selection.first),
          style: SegmentedButton.styleFrom(
            selectedBackgroundColor: shades.soft,
            selectedForegroundColor: shades.deep,
            textStyle: const TextStyle(fontSize: 13),
          ),
        ),
        const SizedBox(height: 16),
        ...switch (_tab) {
          _DashboardTab.sales => [
            const _TargetCard(key: GroupDashboard.targetKey),
            const SizedBox(height: 20),
            const _SectionTitle('Orders'),
            const SizedBox(height: 8),
            const _OrderPipeline(key: GroupDashboard.pipelineKey),
            const SizedBox(height: 20),
            _PeriodChips(
              key: GroupDashboard.periodsKey,
              selected: _period,
              shades: shades,
              onSelected: (period) => setState(() => _period = period),
            ),
            const SizedBox(height: 12),
            const _ShopsCard(key: GroupDashboard.shopsKey),
          ],
          _DashboardTab.stock => const [
            NotConnectedCard(
              icon: Icons.inventory_2_outlined,
              text: 'Stock figures will appear here.',
            ),
          ],
          _DashboardTab.operations => const [
            NotConnectedCard(
              icon: Icons.local_shipping_outlined,
              text: 'Goods in and out will appear here.',
            ),
          ],
        },
      ],
    );
  }
}

const _figurePlaceholder = '—';
const _mutedText = TextStyle(fontSize: 12, color: AikuPalette.placeholder);

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

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

class _TargetCard extends StatelessWidget {
  const _TargetCard({super.key});

  @override
  Widget build(BuildContext context) {
    return const Card(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.track_changes, size: 18, color: AikuPalette.label),
                SizedBox(width: 8),
                Expanded(child: _SectionTitle('Year to date target')),
                Text(_figurePlaceholder, style: _mutedText),
              ],
            ),
            SizedBox(height: 12),
            ClipRRect(
              borderRadius: BorderRadius.all(Radius.circular(4)),
              child: LinearProgressIndicator(
                value: 0,
                minHeight: 8,
                backgroundColor: Color(0xFFE5E7EB),
              ),
            ),
            SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '$_figurePlaceholder of $_figurePlaceholder',
                  style: _mutedText,
                ),
                Text('$_figurePlaceholder days left', style: _mutedText),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// The order pipeline, as on the web: counts never take the theme colour.
class _OrderPipeline extends StatelessWidget {
  const _OrderPipeline({super.key});

  static const _stages = [
    (label: 'In basket', icon: Icons.shopping_basket_outlined),
    (label: 'Submitted', icon: Icons.radio_button_unchecked),
    (label: 'In warehouse', icon: Icons.warehouse_outlined),
    (label: 'Waiting for dispatch', icon: Icons.inventory_outlined),
    (label: 'Dispatched today', icon: Icons.send_outlined),
    (label: 'Returns', icon: Icons.swap_horiz),
  ];

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 104,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _stages.length,
        separatorBuilder: (_, _) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final (:label, :icon) = _stages[index];
          return StatTile(label: label, icon: icon);
        },
      ),
    );
  }
}

/// A count with a label, in the fixed (never themed) stat colours.
class StatTile extends StatelessWidget {
  const StatTile({super.key, required this.label, required this.icon});

  /// What is counted.
  final String label;

  /// Shown next to the count.
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 136,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  color: AikuPalette.label,
                ),
              ),
              const Spacer(),
              Row(
                children: [
                  Icon(icon, size: 20, color: AikuPalette.label),
                  const SizedBox(width: 6),
                  const Text(
                    _figurePlaceholder,
                    style: TextStyle(fontSize: 22, color: AikuPalette.ink),
                  ),
                ],
              ),
              const SizedBox(height: 2),
              const Text(_figurePlaceholder, style: _mutedText),
            ],
          ),
        ),
      ),
    );
  }
}

class _PeriodChips extends StatelessWidget {
  const _PeriodChips({
    super.key,
    required this.selected,
    required this.shades,
    required this.onSelected,
  });

  final String selected;
  final AccentShades shades;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 36,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _periods.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final period = _periods[index];
          final isSelected = period == selected;
          return ChoiceChip(
            label: Text(period),
            selected: isSelected,
            showCheckmark: false,
            onSelected: (_) => onSelected(period),
            selectedColor: shades.accent,
            backgroundColor: Colors.white,
            side: BorderSide(
              color: isSelected ? shades.accent : AikuPalette.inputBorder,
            ),
            labelStyle: TextStyle(
              fontSize: 13,
              color: isSelected ? shades.onAccent : AikuPalette.label,
            ),
          );
        },
      ),
    );
  }
}

class _ShopsCard extends StatelessWidget {
  const _ShopsCard({super.key});

  static const _headerStyle = TextStyle(
    fontSize: 12,
    fontWeight: FontWeight.w600,
    color: AikuPalette.label,
  );

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const _SectionTitle('Shops'),
            const SizedBox(height: 10),
            const Row(
              children: [
                Expanded(
                  child: Text('Shop', style: _headerStyle, softWrap: false),
                ),
                SizedBox(
                  width: 72,
                  child: Text(
                    'Invoices',
                    style: _headerStyle,
                    textAlign: TextAlign.end,
                    softWrap: false,
                  ),
                ),
                SizedBox(
                  width: 96,
                  child: Text(
                    'Sales',
                    style: _headerStyle,
                    textAlign: TextAlign.end,
                    softWrap: false,
                  ),
                ),
              ],
            ),
            const Divider(height: 16),
            for (var row = 0; row < 5; row++) const _PlaceholderShopRow(),
          ],
        ),
      ),
    );
  }
}

class _PlaceholderShopRow extends StatelessWidget {
  const _PlaceholderShopRow();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Expanded(child: Text(_figurePlaceholder, style: _mutedText)),
          SizedBox(
            width: 72,
            child: Text(
              _figurePlaceholder,
              style: _mutedText,
              textAlign: TextAlign.end,
            ),
          ),
          SizedBox(
            width: 96,
            child: Text(
              _figurePlaceholder,
              style: _mutedText,
              textAlign: TextAlign.end,
            ),
          ),
        ],
      ),
    );
  }
}
