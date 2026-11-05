/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../auth/auth_controller.dart';
import '../auth/token_store.dart';
import '../ui/accent_shades.dart';
import '../ui/aiku_palette.dart';
import '../ui/aiku_theme.dart';
import 'theme_screen.dart';

/// The screen after sign-in, laid out after aiku's web Group dashboard.
///
/// Layout only for now: every figure shows a dash until the dashboard is
/// connected to aiku. Nothing on it is made up.
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({
    super.key,
    required this.controller,
    required this.session,
  });

  /// Keys for tests.
  static const previewNoticeKey = ValueKey('dashboard.previewNotice');
  static const tabsKey = ValueKey('dashboard.tabs');
  static const targetKey = ValueKey('dashboard.target');
  static const pipelineKey = ValueKey('dashboard.pipeline');
  static const periodsKey = ValueKey('dashboard.periods');
  static const shopsKey = ValueKey('dashboard.shops');
  static const themeButtonKey = ValueKey('dashboard.theme');
  static const accountMenuKey = ValueKey('dashboard.account');
  static const signOutKey = ValueKey('dashboard.signOut');

  /// Runs sign-out and theme changes.
  final AuthController controller;

  /// The signed-in user.
  final StoredSession session;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
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

class _DashboardScreenState extends State<DashboardScreen> {
  _DashboardTab _tab = _DashboardTab.sales;
  String _period = _periods.first;

  void _openTheme() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ThemeScreen(controller: widget.controller),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final profile = widget.session.profile;
    final shades = AccentShades.fromAppTheme(effectiveTheme(profile));

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: Row(
          children: [
            Image.asset('assets/images/invader.png', height: 28),
            const SizedBox(width: 10),
            const Text(
              'Dashboard',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
            ),
          ],
        ),
        actions: [
          IconButton(
            key: DashboardScreen.themeButtonKey,
            tooltip: 'Theme',
            icon: Icon(Icons.palette_outlined, color: shades.accent),
            onPressed: _openTheme,
          ),
          _AccountMenu(
            name: profile.contactName ?? profile.username,
            username: profile.username,
            shades: shades,
            onTheme: _openTheme,
            onSignOut: widget.controller.signOut,
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          _PreviewNotice(key: DashboardScreen.previewNoticeKey, shades: shades),
          const SizedBox(height: 16),
          SegmentedButton<_DashboardTab>(
            key: DashboardScreen.tabsKey,
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
              const _TargetCard(key: DashboardScreen.targetKey),
              const SizedBox(height: 20),
              const _SectionTitle('Orders'),
              const SizedBox(height: 8),
              const _OrderPipeline(key: DashboardScreen.pipelineKey),
              const SizedBox(height: 20),
              _PeriodChips(
                key: DashboardScreen.periodsKey,
                selected: _period,
                shades: shades,
                onSelected: (period) => setState(() => _period = period),
              ),
              const SizedBox(height: 12),
              const _ShopsCard(key: DashboardScreen.shopsKey),
            ],
            _DashboardTab.stock => const [
              _NotConnectedCard(
                icon: Icons.inventory_2_outlined,
                text: 'Stock figures will appear here.',
              ),
            ],
            _DashboardTab.operations => const [
              _NotConnectedCard(
                icon: Icons.local_shipping_outlined,
                text: 'Goods in and out will appear here.',
              ),
            ],
          },
        ],
      ),
    );
  }
}

const _figurePlaceholder = '—';
const _mutedText = TextStyle(fontSize: 12, color: AikuPalette.placeholder);

class _PreviewNotice extends StatelessWidget {
  const _PreviewNotice({super.key, required this.shades});

  final AccentShades shades;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: shades.soft,
        borderRadius: const BorderRadius.all(Radius.circular(8)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Icon(Icons.info_outline, size: 18, color: shades.deep),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Layout preview. Figures appear once the dashboard is '
                'connected to aiku.',
                style: TextStyle(fontSize: 13, color: shades.deep),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

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
          return _StatTile(label: label, icon: icon);
        },
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.label, required this.icon});

  final String label;
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

class _NotConnectedCard extends StatelessWidget {
  const _NotConnectedCard({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            Icon(icon, size: 28, color: AikuPalette.placeholder),
            const SizedBox(height: 8),
            Text(
              text,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 14, color: AikuPalette.label),
            ),
          ],
        ),
      ),
    );
  }
}

enum _AccountAction { theme, signOut }

class _AccountMenu extends StatelessWidget {
  const _AccountMenu({
    required this.name,
    required this.username,
    required this.shades,
    required this.onTheme,
    required this.onSignOut,
  });

  final String name;
  final String username;
  final AccentShades shades;
  final VoidCallback onTheme;
  final VoidCallback onSignOut;

  @override
  Widget build(BuildContext context) {
    final initial = name.isEmpty ? '?' : name.characters.first.toUpperCase();

    return PopupMenuButton<_AccountAction>(
      key: DashboardScreen.accountMenuKey,
      tooltip: 'Account',
      position: PopupMenuPosition.under,
      onSelected: (action) => switch (action) {
        _AccountAction.theme => onTheme(),
        _AccountAction.signOut => onSignOut(),
      },
      itemBuilder: (context) => [
        PopupMenuItem<_AccountAction>(
          enabled: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                name,
                style: const TextStyle(
                  fontWeight: FontWeight.w600,
                  color: AikuPalette.ink,
                ),
              ),
              Text(username, style: const TextStyle(fontSize: 12)),
            ],
          ),
        ),
        const PopupMenuDivider(),
        PopupMenuItem(
          value: _AccountAction.theme,
          child: Row(
            children: [
              Icon(Icons.palette_outlined, size: 18, color: shades.accent),
              const SizedBox(width: 10),
              const Text('Theme'),
            ],
          ),
        ),
        PopupMenuItem(
          key: DashboardScreen.signOutKey,
          value: _AccountAction.signOut,
          child: const Row(
            children: [
              Icon(Icons.logout, size: 18, color: AikuPalette.cancel),
              SizedBox(width: 10),
              Text('Sign out'),
            ],
          ),
        ),
      ],
      child: CircleAvatar(
        radius: 16,
        backgroundColor: shades.accent,
        child: Text(
          initial,
          style: TextStyle(
            color: shades.onAccent,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }
}
