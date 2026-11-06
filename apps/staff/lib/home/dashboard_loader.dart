/*
 * Author Louis Perez
 * Created on 06-11-2026-10h-17m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/foundation.dart';

import '../api/aiku_api_client.dart';
import '../api/api_failure.dart';
import '../api/dashboard.dart';
import '../auth/form_errors.dart';

/// The dashboard calls for the signed-in user.
class DashboardSource {
  DashboardSource({required this._client, required this._token});

  final AikuApiClient _client;
  final String _token;

  /// The group dashboard.
  Future<GroupDashboardData> groupDashboard() => _client.groupDashboard(_token);

  /// Delivery notes per stage in [warehouseSlug], or in every warehouse the
  /// user works in.
  Future<OperationsData> operations({String? warehouseSlug}) =>
      _client.operationsDashboard(_token, warehouseSlug: warehouseSlug);
}

/// Where loading a dashboard's figures stands.
sealed class DashboardState<T> {
  const DashboardState();
}

/// The first answer hasn't arrived yet.
final class DashboardLoading<T> extends DashboardState<T> {
  const DashboardLoading();
}

/// The figures arrived at [loadedAt]. [refreshMessages] says why the last
/// refresh failed, if it did; the figures shown are then the previous ones.
final class const DashboardReady<T>(
  final T data, {
  required final DateTime loadedAt,
  final List<String> refreshMessages = const [],
}) extends DashboardState<T>;

/// The first load failed.
final class const DashboardFailed<T>(final List<String> messages)
    extends DashboardState<T>;

/// Loads one dashboard's figures from aiku and refreshes them on request.
class DashboardLoader<T> extends ChangeNotifier {
  DashboardLoader({
    required this._fetch,
    required this._onUnauthorised,
    this._showDiagnostics = kDebugMode,
    this._now = DateTime.now,
  });

  final Future<T> Function() _fetch;
  final Future<void> Function() _onUnauthorised;
  final bool _showDiagnostics;
  final DateTime Function() _now;

  DashboardState<T> _state = DashboardLoading<T>();
  bool _isFetching = false;
  bool _isDisposed = false;

  /// What to show.
  DashboardState<T> get state => _state;

  /// Asks aiku for the figures. Until the first answer the loading state
  /// shows; after that the figures already shown stay while aiku answers,
  /// and stay if it can't be reached. A 401 signs the user out.
  Future<void> load() async {
    if (_isFetching) {
      return;
    }
    _isFetching = true;
    final previous = _state;
    if (previous is DashboardFailed<T>) {
      _show(DashboardLoading<T>());
    }
    try {
      final data = await _fetch();
      _show(DashboardReady<T>(data, loadedAt: _now()));
    } on Unauthorised {
      await _onUnauthorised();
    } on ApiFailure catch (failure) {
      final messages = FormErrors.from(
        failure,
        fieldsOnScreen: const {},
        showDiagnostics: _showDiagnostics,
      ).messages;
      _show(switch (previous) {
        DashboardReady(:final data, :final loadedAt) => DashboardReady<T>(
          data,
          loadedAt: loadedAt,
          refreshMessages: messages,
        ),
        DashboardLoading() || DashboardFailed() => DashboardFailed<T>(messages),
      });
    } finally {
      _isFetching = false;
    }
  }

  @override
  void dispose() {
    _isDisposed = true;
    super.dispose();
  }

  // The Home can close (another area, sign-out) while aiku is answering.
  void _show(DashboardState<T> state) {
    if (_isDisposed) {
      return;
    }
    _state = state;
    notifyListeners();
  }
}
