/*
 * Author Louis Perez
 * Created on 06-11-2026-10h-17m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

/// A period aiku's dashboards can show, e.g. `tdy`, "Today".
class const DashboardInterval({
  required final String value,
  required final String label,
  required final String shortLabel,
});

/// One step of the order pipeline, e.g. "Picking".
class const PipelineStep({
  required final String key,
  required final String label,
  required final int count,
  required final String? amount,
});

/// A group of the order pipeline as aiku's web groups it, e.g. "Warehouse",
/// with its total and its [steps].
class const PipelineGroup({
  required final String key,
  required final String label,
  required final int count,
  required final String? amount,
  required final List<PipelineStep> steps,
});

/// Progress towards this year's sales target. Money is formatted by aiku.
class const YearTarget({
  required final String label,
  required final String? salesSoFar,
  required final String? target,
  required final double? progress,
  required final int? remainingDays,
  required final String? gap,
  required final String? neededPerDay,
});

/// Invoices and sales over one period. Money is formatted by aiku.
class const PeriodFigures({
  required final String invoices,
  required final String? sales,
  required final double salesValue,
});

/// One shop's figures for every period.
class const ShopSales({
  required final String slug,
  required final String name,
  required final bool isOpen,
  required final Map<String, PeriodFigures> figures,
});

/// The sales part of the group dashboard.
class const GroupSales({
  required final List<PipelineGroup> pipeline,
  required final YearTarget? yearTarget,
  required final List<ShopSales> shops,
  required final Map<String, PeriodFigures> totals,
});

/// `GET /app/api/mobile/dashboard/group`.
class const GroupDashboardData({
  required final String currencyCode,
  required final List<DashboardInterval> intervals,
  required final GroupSales? sales,
}) {
  /// Reads the endpoint's body. Returns null for anything else.
  static GroupDashboardData? fromJson(Object? json) {
    if (json
        case {
              'currency_code': final String currencyCode,
              'intervals': final List<Object?> intervals,
            } &&
            final Map<String, Object?> body) {
      return GroupDashboardData(
        currencyCode: currencyCode,
        intervals: [
          for (final interval in intervals)
            if (interval case {
              'value': final String value,
              'label': final String label,
            })
              DashboardInterval(
                value: value,
                label: label,
                shortLabel: _string(interval['short_label']) ?? label,
              ),
        ],
        sales: _sales(body['sales']),
      );
    }
    return null;
  }

  static GroupSales? _sales(Object? json) {
    if (json
        case {
              'pipeline': final List<Object?> pipeline,
              'shops': final List<Object?> shops,
            } &&
            final Map<String, Object?> sales) {
      return GroupSales(
        pipeline: [
          for (final group in pipeline)
            if (_step(group) case final step?)
              PipelineGroup(
                key: step.key,
                label: step.label,
                count: step.count,
                amount: step.amount,
                steps: [
                  if (group case {'steps': final List<Object?> steps})
                    for (final json in steps) ?_step(json),
                ],
              ),
        ],
        yearTarget: _yearTarget(sales['year_target']),
        shops: [
          for (final shop in shops)
            if (shop case {
              'slug': final String slug,
              'name': final String name,
            })
              ShopSales(
                slug: slug,
                name: name,
                isOpen: shop['is_open'] != false,
                figures: _figures(shop['figures']),
              ),
        ],
        totals: _figures(sales['totals']),
      );
    }
    return null;
  }

  static PipelineStep? _step(Object? json) {
    if (json case {
      'key': final String key,
      'label': final String label,
      'count': final int count,
    }) {
      return PipelineStep(
        key: key,
        label: label,
        count: count,
        amount: _string(json['amount']),
      );
    }
    return null;
  }

  static YearTarget? _yearTarget(Object? json) {
    if (json
        case {'label': final String label} &&
            final Map<String, Object?> target) {
      return YearTarget(
        label: label,
        salesSoFar: _string(target['sales_so_far']),
        target: _string(target['target']),
        progress: switch (target['progress']) {
          final num progress => progress.toDouble(),
          _ => null,
        },
        remainingDays: switch (target['remaining_days']) {
          final int days => days,
          _ => null,
        },
        gap: _string(target['gap']),
        neededPerDay: _string(target['needed_per_day']),
      );
    }
    return null;
  }

  static Map<String, PeriodFigures> _figures(Object? json) => {
    if (json is Map<String, Object?>)
      for (final MapEntry(:key, :value) in json.entries)
        if (value
            case {'invoices': final String invoices} &&
                final Map<String, Object?> figures)
          key: PeriodFigures(
            invoices: invoices,
            sales: _string(figures['sales']),
            salesValue: switch (figures['sales_value']) {
              final num amount => amount.toDouble(),
              _ => 0,
            },
          ),
  };
}

/// One stage of the warehouse pipeline, e.g. "Picking".
class const OperationsStage({
  required final String key,
  required final String label,
  required final int count,
});

/// A warehouse the operations figures cover.
class const OperationsWarehouse({
  required final String slug,
  required final String name,
});

/// `GET /app/api/mobile/dashboard/operations[/{warehouse}]`: delivery notes
/// per stage, in aiku's order.
class const OperationsData({
  required final List<OperationsWarehouse> warehouses,
  required final List<OperationsStage> stages,
}) {
  /// Reads the endpoint's body. Returns null for anything else.
  static OperationsData? fromJson(Object? json) {
    if (json
        case {'stages': final List<Object?> stages} &&
            final Map<String, Object?> body) {
      return OperationsData(
        warehouses: [
          if (body['warehouses'] case final List<Object?> warehouses)
            for (final warehouse in warehouses)
              if (warehouse case {
                'slug': final String slug,
                'name': final String name,
              })
                OperationsWarehouse(slug: slug, name: name),
        ],
        stages: [
          for (final stage in stages)
            if (stage case {
              'key': final String key,
              'label': final String label,
              'count': final int count,
            })
              OperationsStage(key: key, label: label, count: count),
        ],
      );
    }
    return null;
  }
}

String? _string(Object? value) => value is String ? value : null;
