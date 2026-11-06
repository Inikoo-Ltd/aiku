/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:async';

import 'package:flutter/material.dart';

import '../api/dashboard.dart';
import '../ui/app_colours.dart';
import '../ui/dashboard_kit.dart';
import '../ui/form_error_list.dart';
import '../ui/load_problem.dart';
import '../ui/preview_notice.dart';
import 'dashboard_loader.dart';
import 'operations_overview.dart';

/// The group's Home, after aiku's web Group dashboard, with aiku's figures.
///
/// The tabs and the leading figure sit on the theme band. Sales and
/// Operations are live; Stock is not connected yet. Figures load when the
/// Home opens, when the app comes back to the foreground and on
/// pull-to-refresh.
class GroupDashboard extends StatefulWidget {
  const GroupDashboard({
    super.key,
    required this.source,
    required this.onUnauthorised,
  });

  /// Keys for tests.
  static const heroKey = ValueKey('dashboard.hero');
  static const ordersKey = ValueKey('dashboard.orders');
  static const periodsKey = ValueKey('dashboard.periods');
  static const morePeriodsKey = ValueKey('dashboard.periods.more');
  static const shopsKey = ValueKey('dashboard.shops');
  static const allShopsKey = ValueKey('dashboard.allShops');
  static const salesHiddenKey = ValueKey('dashboard.salesHidden');
  static const operationsKey = ValueKey('dashboard.operations');
  static const retryKey = ValueKey('dashboard.retry');

  /// The key of the tab called [name]: `sales`, `stock` or `operations`.
  static ValueKey<String> tabKey(String name) =>
      ValueKey('dashboard.tab.$name');

  /// The key of the period choice for aiku's interval [value], e.g. `tdy`.
  static ValueKey<String> periodKey(String value) =>
      ValueKey('dashboard.period.$value');

  /// The key of the order group aiku keys as [key], e.g. `in_warehouse`.
  static ValueKey<String> orderGroupKey(String key) =>
      ValueKey('dashboard.orders.$key');

  /// The key of the row for the shop with [slug].
  static ValueKey<String> shopRowKey(String slug) =>
      ValueKey('dashboard.shop.$slug');

  /// The key of the warehouse choice for [slug]; null is "All".
  static ValueKey<String> warehouseKey(String? slug) =>
      ValueKey('dashboard.warehouse.${slug ?? 'all'}');

  /// Where the figures come from.
  final DashboardSource source;

  /// Called when aiku answers 401.
  final Future<void> Function() onUnauthorised;

  @override
  State<GroupDashboard> createState() => _GroupDashboardState();
}

enum _DashboardTab {
  sales('Sales'),
  stock('Stock'),
  operations('Operations');

  const _DashboardTab(this.label);

  final String label;
}

/// The periods offered at a tap, as aiku's interval values; the rest are
/// under More.
const _quickPeriods = [
  (value: 'tdy', label: 'Today'),
  (value: 'wtd', label: 'Week'),
  (value: 'mtd', label: 'Month'),
  (value: 'ytd', label: 'Year'),
];

class _GroupDashboardState extends State<GroupDashboard> {
  late final DashboardLoader<GroupDashboardData> _sales;
  final _operations = <String?, DashboardLoader<OperationsData>>{};
  late final AppLifecycleListener _lifecycle;
  _DashboardTab _tab = _DashboardTab.sales;
  String _interval = 'ytd';
  String? _warehouse;

  @override
  void initState() {
    super.initState();
    _sales = DashboardLoader(
      fetch: widget.source.groupDashboard,
      onUnauthorised: widget.onUnauthorised,
    );
    _lifecycle = AppLifecycleListener(onResume: _refreshAll);
    // The Sales tab shows placeholders until this answers.
    unawaited(_sales.load());
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    _sales.dispose();
    for (final loader in _operations.values) {
      loader.dispose();
    }
    super.dispose();
  }

  void _refreshAll() {
    unawaited(_sales.load());
    for (final loader in _operations.values) {
      unawaited(loader.load());
    }
  }

  Future<void> _refreshVisibleTab() async {
    switch (_tab) {
      case _DashboardTab.sales:
        await _sales.load();
      case _DashboardTab.operations:
        await _operations[_warehouse]?.load();
      case _DashboardTab.stock:
        break;
    }
  }

  /// The loader for [warehouse] (null: all), started the first time.
  void _ensureOperations(String? warehouse) {
    if (_operations.containsKey(warehouse)) {
      return;
    }
    final loader = DashboardLoader<OperationsData>(
      fetch: () => widget.source.operations(warehouseSlug: warehouse),
      onUnauthorised: widget.onUnauthorised,
    );
    _operations[warehouse] = loader;
    unawaited(loader.load());
  }

  void _selectTab(_DashboardTab tab) {
    if (tab == _DashboardTab.operations) {
      _ensureOperations(null);
      _ensureOperations(_warehouse);
    }
    setState(() => _tab = tab);
  }

  void _selectWarehouse(String? warehouse) {
    _ensureOperations(warehouse);
    setState(() => _warehouse = warehouse);
  }

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    final tabs = TextTabs<_DashboardTab>(
      tabs: [
        for (final tab in _DashboardTab.values)
          TextTab(
            value: tab,
            label: tab.label,
            key: GroupDashboard.tabKey(tab.name),
          ),
      ],
      selected: _tab,
      onSelected: _selectTab,
      onBand: true,
    );

    return RefreshIndicator(
      color: colours.accentText,
      onRefresh: _refreshVisibleTab,
      child: switch (_tab) {
        _DashboardTab.sales => ListenableBuilder(
          listenable: _sales,
          builder: (context, _) => _SalesTab(
            tabs: tabs,
            state: _sales.state,
            interval: _interval,
            onInterval: (interval) => setState(() => _interval = interval),
            onRetry: _sales.load,
          ),
        ),
        _DashboardTab.stock => _DashboardPage(
          band: [tabs],
          body: const [
            NotConnectedCard(
              icon: Icons.inventory_2_outlined,
              text: 'Stock figures will appear here.',
            ),
          ],
        ),
        _DashboardTab.operations => ListenableBuilder(
          key: GroupDashboard.operationsKey,
          listenable: Listenable.merge([
            _operations[null],
            _operations[_warehouse],
          ]),
          builder: (context, _) => _OperationsTab(
            tabs: tabs,
            warehouses: switch (_operations[null]?.state) {
              DashboardReady(:final data) => data.warehouses,
              _ => const [],
            },
            warehouse: _warehouse,
            state: _operations[_warehouse]!.state,
            onWarehouse: _selectWarehouse,
            onRetry: _operations[_warehouse]!.load,
          ),
        ),
      },
    );
  }
}

/// A dashboard page: the theme band on top, then the page.
class _DashboardPage extends StatelessWidget {
  const _DashboardPage({required this.band, required this.body});

  final List<Widget> band;
  final List<Widget> body;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      children: [
        ThemeBand(children: band),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 20, 16, 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: body,
          ),
        ),
      ],
    );
  }
}

/// The Sales tab: the year against its target on the band, then the orders
/// in the pipeline now and the shops over the chosen period.
class _SalesTab extends StatelessWidget {
  const _SalesTab({
    required this.tabs,
    required this.state,
    required this.interval,
    required this.onInterval,
    required this.onRetry,
  });

  final Widget tabs;
  final DashboardState<GroupDashboardData> state;
  final String interval;
  final ValueChanged<String> onInterval;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return switch (state) {
      DashboardLoading() => _DashboardPage(
        band: [
          tabs,
          const SizedBox(height: 16),
          const _SalesHero(
            key: GroupDashboard.heroKey,
            target: null,
            loadedAt: null,
          ),
        ],
        body: [
          _SalesFigures(
            sales: null,
            intervals: const [],
            interval: interval,
            onInterval: onInterval,
          ),
        ],
      ),
      DashboardFailed(:final messages) => _DashboardPage(
        band: [tabs],
        body: [
          LoadProblem(
            messages: messages,
            onRetry: onRetry,
            retryKey: GroupDashboard.retryKey,
          ),
        ],
      ),
      DashboardReady(:final data, :final loadedAt, :final refreshMessages) =>
        switch (data.sales) {
          final GroupSales sales => _DashboardPage(
            band: [
              tabs,
              const SizedBox(height: 16),
              _SalesHero(
                key: GroupDashboard.heroKey,
                target: sales.yearTarget,
                loadedAt: loadedAt,
              ),
            ],
            body: [
              if (refreshMessages.isNotEmpty) ...[
                FormErrorList(messages: refreshMessages),
                const SizedBox(height: 12),
              ],
              _SalesFigures(
                sales: sales,
                intervals: data.intervals,
                interval: _available(data.intervals),
                onInterval: onInterval,
              ),
            ],
          ),
          null => _DashboardPage(
            band: [tabs],
            body: const [
              NotConnectedCard(
                key: GroupDashboard.salesHiddenKey,
                icon: Icons.lock_outline,
                text: "aiku doesn't show group sales to this account.",
              ),
            ],
          ),
        },
    };
  }

  /// The chosen period if aiku offers it, otherwise aiku's first (All).
  String _available(List<DashboardInterval> intervals) {
    for (final option in intervals) {
      if (option.value == interval) {
        return interval;
      }
    }
    return intervals.isEmpty ? interval : intervals.first.value;
  }
}

class _SalesFigures extends StatelessWidget {
  const _SalesFigures({
    required this.sales,
    required this.intervals,
    required this.interval,
    required this.onInterval,
  });

  /// Null while loading: every figure shows a dash.
  final GroupSales? sales;
  final List<DashboardInterval> intervals;
  final String interval;
  final ValueChanged<String> onInterval;

  @override
  Widget build(BuildContext context) {
    final sales = this.sales;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SectionHeader('Orders now'),
        const SizedBox(height: 8),
        _OrdersGrid(key: GroupDashboard.ordersKey, groups: sales?.pipeline),
        const SizedBox(height: 32),
        const SectionHeader('Shops'),
        _PeriodTabs(
          key: GroupDashboard.periodsKey,
          intervals: intervals,
          interval: interval,
          onSelected: onInterval,
        ),
        _ShopsSummary(
          key: GroupDashboard.shopsKey,
          shops: sales?.shops,
          totals: sales?.totals,
          interval: interval,
          intervalLabel: _labelOf(intervals, interval),
        ),
      ],
    );
  }
}

String _labelOf(List<DashboardInterval> intervals, String value) {
  for (final option in intervals) {
    if (option.value == value) {
      return option.label;
    }
  }
  return value;
}

/// Sales so far this year against aiku's target for the year, on the band.
class _SalesHero extends StatelessWidget {
  const _SalesHero({super.key, required this.target, required this.loadedAt});

  final YearTarget? target;
  final DateTime? loadedAt;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    final detail = TextStyle(fontSize: 13, color: colours.onBandMuted);
    final target = this.target;
    final progress = target?.progress;
    final loadedAt = this.loadedAt;
    final days = target?.remainingDays;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        HeroFigure(
          label: 'Sales, year to date',
          value: target?.salesSoFar,
          trailing: loadedAt == null
              ? null
              : Caption(updatedAt(loadedAt), onBand: true),
        ),
        const SizedBox(height: 10),
        ClipRRect(
          borderRadius: const BorderRadius.all(Radius.circular(3)),
          child: LinearProgressIndicator(
            value: (progress ?? 0).clamp(0, 1).toDouble(),
            minHeight: 6,
            color: colours.bandHighlight,
            backgroundColor: colours.onBand.withValues(alpha: 0.22),
          ),
        ),
        const SizedBox(height: 8),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Text(switch (target) {
                null => placeholderFigure,
                YearTarget(target: null) => 'No target set in aiku',
                YearTarget(target: final String amount) =>
                  '${progress == null ? placeholderFigure : '${(progress * 100).round()}%'} '
                      'of $amount target',
              }, style: detail),
            ),
            const SizedBox(width: 8),
            Text('${days ?? placeholderFigure} days left', style: detail),
          ],
        ),
        if (target?.neededPerDay case final String perDay) ...[
          const SizedBox(height: 2),
          Text('$perDay a day needed to reach it', style: detail),
        ],
      ],
    );
  }
}

/// aiku's order groups, three to a row, split by hairlines. Tapping one
/// shows its steps.
class _OrdersGrid extends StatelessWidget {
  const _OrdersGrid({super.key, required this.groups});

  /// Null while loading.
  final List<PipelineGroup>? groups;

  static const _columns = 3;
  static const _placeholderCells = 6;

  @override
  Widget build(BuildContext context) {
    final hairline = AppColours.of(context).hairline;
    final groups = this.groups;
    final cells = [
      if (groups == null)
        for (var cell = 0; cell < _placeholderCells; cell++)
          const _OrderCell(label: '', count: null, amount: null)
      else
        for (final group in groups)
          _OrderCell(
            key: GroupDashboard.orderGroupKey(group.key),
            label: group.label,
            count: formatCount(group.count),
            amount: group.amount,
            onTap: group.steps.length > 1
                ? () => _showSteps(context, group)
                : null,
          ),
    ];

    return Table(
      border: TableBorder(
        horizontalInside: BorderSide(color: hairline),
        verticalInside: BorderSide(color: hairline),
      ),
      children: [
        for (var row = 0; row < cells.length; row += _columns)
          TableRow(
            children: [
              for (var column = 0; column < _columns; column++)
                row + column < cells.length
                    ? cells[row + column]
                    : const SizedBox.shrink(),
            ],
          ),
      ],
    );
  }

  static void _showSteps(BuildContext context, PipelineGroup group) {
    unawaited(
      showModalBottomSheet<void>(
        context: context,
        builder: (context) => _GroupSteps(group: group),
      ),
    );
  }
}

class _OrderCell extends StatelessWidget {
  const _OrderCell({
    super.key,
    required this.label,
    required this.count,
    required this.amount,
    this.onTap,
  });

  final String label;
  final String? count;
  final String? amount;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(10, 12, 10, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              height: 32,
              child: Text(
                label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 12,
                  height: 1.3,
                  color: colours.secondary,
                ),
              ),
            ),
            const SizedBox(height: 4),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                count ?? placeholderFigure,
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w600,
                  color: colours.ink,
                  fontFeatures: tabularFigures,
                ),
              ),
            ),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                amount ?? (count == null ? placeholderFigure : ' '),
                style: TextStyle(
                  fontSize: 11,
                  color: colours.muted,
                  fontFeatures: tabularFigures,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// The steps of one order group, in a sheet.
class _GroupSteps extends StatelessWidget {
  const _GroupSteps({required this.group});

  final PipelineGroup group;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 8, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    group.label,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: colours.secondary,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: 'Close',
                  iconSize: 18,
                  icon: Icon(Icons.close, color: colours.secondary),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
            for (final step in group.steps)
              _FigureLine(
                key: ValueKey('dashboard.step.${step.key}'),
                title: step.label,
                subtitle: step.amount,
                figure: formatCount(step.count),
              ),
            _FigureLine(
              title: 'Total',
              subtitle: group.amount,
              figure: formatCount(group.count),
              isTotal: true,
            ),
          ],
        ),
      ),
    );
  }
}

/// Today / Week / Month / Year, and More for aiku's other periods.
class _PeriodTabs extends StatelessWidget {
  const _PeriodTabs({
    super.key,
    required this.intervals,
    required this.interval,
    required this.onSelected,
  });

  final List<DashboardInterval> intervals;
  final String interval;
  final ValueChanged<String> onSelected;

  static const _more = '';

  @override
  Widget build(BuildContext context) {
    final offered = {for (final option in intervals) option.value};
    final quick = [
      for (final period in _quickPeriods)
        if (offered.contains(period.value)) period,
    ];
    final isQuick = quick.any((period) => period.value == interval);

    return TextTabs<String>(
      tabs: [
        for (final period in quick)
          TextTab(
            value: period.value,
            label: period.label,
            key: GroupDashboard.periodKey(period.value),
          ),
        if (intervals.isNotEmpty)
          TextTab(
            value: _more,
            label: isQuick ? 'More' : _labelOf(intervals, interval),
            key: GroupDashboard.morePeriodsKey,
          ),
      ],
      selected: isQuick ? interval : _more,
      onSelected: (value) =>
          value == _more ? _choosePeriod(context) : onSelected(value),
    );
  }

  void _choosePeriod(BuildContext context) {
    final colours = AppColours.of(context);
    unawaited(
      showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        builder: (sheet) => SafeArea(
          child: ListView(
            shrinkWrap: true,
            padding: const EdgeInsets.symmetric(vertical: 8),
            children: [
              for (final option in intervals)
                ListTile(
                  key: GroupDashboard.periodKey(option.value),
                  title: Text(
                    option.label,
                    style: TextStyle(color: colours.ink),
                  ),
                  trailing: option.value == interval
                      ? Icon(Icons.check, color: colours.accentText)
                      : null,
                  onTap: () {
                    Navigator.of(sheet).pop();
                    onSelected(option.value);
                  },
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// All shops together, the five that sold most over the period, and a way
/// to the full list.
class _ShopsSummary extends StatelessWidget {
  const _ShopsSummary({
    super.key,
    required this.shops,
    required this.totals,
    required this.interval,
    required this.intervalLabel,
  });

  /// Null while loading.
  final List<ShopSales>? shops;
  final Map<String, PeriodFigures>? totals;
  final String interval;
  final String intervalLabel;

  static const _topCount = 5;

  @override
  Widget build(BuildContext context) {
    final accentText = AppColours.of(context).accentText;
    final shops = this.shops;
    final ranked = shops == null ? null : rankShops(shops, interval);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _ShopLine(name: 'All shops', figures: totals?[interval], isTotal: true),
        if (ranked == null)
          for (var row = 0; row < _topCount; row++)
            const _ShopLine(name: placeholderFigure, figures: null)
        else ...[
          for (final shop in ranked.take(_topCount))
            _ShopLine(
              key: GroupDashboard.shopRowKey(shop.slug),
              name: shop.name,
              figures: shop.figures[interval],
              isMuted: !shop.isOpen,
            ),
          InkWell(
            key: GroupDashboard.allShopsKey,
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => AllShopsScreen(
                  shops: ranked,
                  totals: totals?[interval],
                  interval: interval,
                  intervalLabel: intervalLabel,
                ),
              ),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 14),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'All ${ranked.length} shops',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                        color: accentText,
                      ),
                    ),
                  ),
                  Icon(Icons.chevron_right, color: accentText),
                ],
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// [shops] by sales over [interval], most first; equal sales keep aiku's
/// order.
List<ShopSales> rankShops(List<ShopSales> shops, String interval) {
  final indexed = shops.indexed.toList()
    ..sort((a, b) {
      final bySales = (b.$2.figures[interval]?.salesValue ?? 0).compareTo(
        a.$2.figures[interval]?.salesValue ?? 0,
      );
      return bySales != 0 ? bySales : a.$1.compareTo(b.$1);
    });
  return [for (final (_, shop) in indexed) shop];
}

/// Every shop's invoices and sales over one period, most sales first.
class AllShopsScreen extends StatelessWidget {
  const AllShopsScreen({
    super.key,
    required this.shops,
    required this.totals,
    required this.interval,
    required this.intervalLabel,
  });

  /// The shops, already ranked.
  final List<ShopSales> shops;

  /// All shops together.
  final PeriodFigures? totals;

  /// aiku's interval value, e.g. `ytd`.
  final String interval;

  /// What the period is called, e.g. "Year to Date".
  final String intervalLabel;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Shops'),
            Text(
              intervalLabel,
              style: TextStyle(
                fontSize: 12,
                color: AppColours.of(context).onBandMuted,
              ),
            ),
          ],
        ),
      ),
      body: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 32),
        itemCount: shops.length + 1,
        itemBuilder: (context, index) => index == 0
            ? _ShopLine(name: 'All shops', figures: totals, isTotal: true)
            : _ShopLine(
                key: GroupDashboard.shopRowKey(shops[index - 1].slug),
                name: shops[index - 1].name,
                figures: shops[index - 1].figures[interval],
                isMuted: !shops[index - 1].isOpen,
              ),
      ),
    );
  }
}

/// A shop's sales over the period, with its invoices underneath.
class _ShopLine extends StatelessWidget {
  const _ShopLine({
    super.key,
    required this.name,
    required this.figures,
    this.isMuted = false,
    this.isTotal = false,
  });

  final String name;
  final PeriodFigures? figures;
  final bool isMuted;
  final bool isTotal;

  @override
  Widget build(BuildContext context) {
    final figures = this.figures;
    return _FigureLine(
      title: name,
      subtitle: figures == null ? null : '${figures.invoices} invoices',
      figure: figures?.sales ?? placeholderFigure,
      isMuted: isMuted,
      isTotal: isTotal,
    );
  }
}

/// A row: a name with a smaller line under it, and a figure on the right.
class _FigureLine extends StatelessWidget {
  const _FigureLine({
    super.key,
    required this.title,
    required this.subtitle,
    required this.figure,
    this.isMuted = false,
    this.isTotal = false,
  });

  final String title;
  final String? subtitle;
  final String figure;
  final bool isMuted;
  final bool isTotal;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    final colour = isMuted ? colours.muted : colours.ink;
    final weight = isTotal ? FontWeight.w600 : FontWeight.w400;
    final subtitle = this.subtitle;

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: colours.hairline)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: weight,
                    color: colour,
                  ),
                ),
                if (subtitle != null)
                  Text(
                    subtitle,
                    style: TextStyle(
                      fontSize: 12,
                      color: colours.muted,
                      fontFeatures: tabularFigures,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Text(
            figure,
            softWrap: false,
            style: TextStyle(
              fontSize: 14,
              fontWeight: isTotal ? FontWeight.w600 : FontWeight.w500,
              color: colour,
              fontFeatures: tabularFigures,
            ),
          ),
        ],
      ),
    );
  }
}

/// The Operations tab: the warehouse to look at and its open delivery notes
/// on the band, then the stages on the page.
class _OperationsTab extends StatelessWidget {
  const _OperationsTab({
    required this.tabs,
    required this.warehouses,
    required this.warehouse,
    required this.state,
    required this.onWarehouse,
    required this.onRetry,
  });

  final Widget tabs;

  /// Every warehouse the user works in, once known.
  final List<OperationsWarehouse> warehouses;

  /// The chosen warehouse; null is all of them.
  final String? warehouse;

  final DashboardState<OperationsData> state;
  final ValueChanged<String?> onWarehouse;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return _DashboardPage(
      band: [
        tabs,
        // With one warehouse there is nothing to choose between.
        if (warehouses.length > 1)
          TextTabs<String?>(
            tabs: [
              TextTab(
                value: null,
                label: 'All',
                key: GroupDashboard.warehouseKey(null),
              ),
              for (final option in warehouses)
                TextTab(
                  value: option.slug,
                  label: option.name,
                  key: GroupDashboard.warehouseKey(option.slug),
                ),
            ],
            selected: warehouse,
            onSelected: onWarehouse,
            onBand: true,
            fontSize: 13,
          ),
        const SizedBox(height: 16),
        OperationsOverview(
          state: state,
          part: OperationsPart.openTotal,
          onRetry: onRetry,
        ),
      ],
      body: [
        OperationsOverview(
          state: state,
          part: OperationsPart.stages,
          onRetry: onRetry,
          retryKey: GroupDashboard.retryKey,
        ),
      ],
    );
  }
}
