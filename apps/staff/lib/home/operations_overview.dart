/*
 * Author Louis Perez
 * Created on 06-11-2026-10h-17m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../api/dashboard.dart';
import '../ui/dashboard_kit.dart';
import '../ui/form_error_list.dart';
import '../ui/load_problem.dart';
import 'dashboard_loader.dart';

/// Which part of the operations figures to show: the open total on the
/// theme band, or the stages on the page.
enum OperationsPart { openTotal, stages }

/// Open delivery notes and the stages they are in, for one warehouse or all
/// of the user's: the group's Operations tab and a warehouse's Home.
class OperationsOverview extends StatelessWidget {
  const OperationsOverview({
    super.key,
    required this.state,
    required this.part,
    required this.onRetry,
    this.retryKey,
  });

  /// Key of the open-total figure, for tests.
  static const openTotalKey = ValueKey('operations.openTotal');

  /// Where loading the counts stands.
  final DashboardState<OperationsData> state;

  /// The part to show.
  final OperationsPart part;

  /// Loads the counts again.
  final VoidCallback onRetry;

  /// The Try again button's key, for tests.
  final Key? retryKey;

  static const _openLabel = 'Open delivery notes';

  @override
  Widget build(BuildContext context) {
    return switch (part) {
      OperationsPart.openTotal => switch (state) {
        DashboardReady(:final data, :final loadedAt) => HeroFigure(
          key: openTotalKey,
          label: _openLabel,
          value: formatCount(_openCount(data.stages)),
          trailing: Caption(updatedAt(loadedAt), onBand: true),
        ),
        DashboardLoading() || DashboardFailed() => const HeroFigure(
          key: openTotalKey,
          label: _openLabel,
          value: null,
        ),
      },
      OperationsPart.stages => switch (state) {
        DashboardLoading() => const StageList(stages: null),
        DashboardFailed(:final messages) => LoadProblem(
          messages: messages,
          onRetry: onRetry,
          retryKey: retryKey,
        ),
        DashboardReady(:final data, :final refreshMessages) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (refreshMessages.isNotEmpty) ...[
              FormErrorList(messages: refreshMessages),
              const SizedBox(height: 12),
            ],
            StageList(stages: data.stages),
          ],
        ),
      },
    };
  }

  /// Every stage before dispatch.
  static int _openCount(List<OperationsStage> stages) => stages
      .where((stage) => stage.key != 'dispatched')
      .fold(0, (total, stage) => total + stage.count);
}
