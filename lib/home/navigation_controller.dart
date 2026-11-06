/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-13m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/foundation.dart';

import '../api/aiku_api_client.dart';
import '../api/api_failure.dart';
import '../api/navigation.dart';
import '../auth/form_errors.dart';
import 'area_memory.dart';

/// Where loading the user's areas and menus stands.
sealed class NavigationState {
  const NavigationState();
}

/// Asking aiku for the areas and menus.
final class NavigationLoading extends NavigationState {
  const NavigationLoading();
}

/// Ready, working in [area].
final class const NavigationReady(final NavigationData data, final Area area)
    extends NavigationState;

/// aiku could not be asked, or gave the user nothing to open.
final class const NavigationFailed(final List<String> messages)
    extends NavigationState;

/// Loads what aiku lets the user open and keeps track of the current area.
///
/// The menus come from aiku, built with the same permission checks as the
/// web sidebar; this class never decides what a user may see.
class NavigationController extends ChangeNotifier {
  NavigationController({
    required this._client,
    required this._token,
    required this._username,
    required this._memory,
    required this._onUnauthorised,
    this._showDiagnostics = kDebugMode,
  });

  /// Shown when aiku gives the user no area at all.
  static const nothingToOpenMessage =
      "There's nothing in aiku this account can open on the app yet.";

  final AikuApiClient _client;
  final String _token;
  final String _username;
  final AreaMemory _memory;
  final Future<void> Function() _onUnauthorised;
  final bool _showDiagnostics;

  NavigationState _state = const NavigationLoading();
  bool _isDisposed = false;
  bool _isRefreshing = false;

  /// What to show.
  NavigationState get state => _state;

  /// Asks aiku for the areas and opens the remembered one, or the default.
  Future<void> load() async {
    _show(const NavigationLoading());
    try {
      final data = await _client.navigation(_token);
      final area =
          data.areaWithKey(await _memory.read(_username)) ?? data.defaultArea;
      _show(
        area == null
            ? const NavigationFailed([nothingToOpenMessage])
            : NavigationReady(data, area),
      );
    } on Unauthorised {
      await _onUnauthorised();
    } on ApiFailure catch (failure) {
      _show(
        NavigationFailed(
          FormErrors.from(
            failure,
            fieldsOnScreen: const {},
            showDiagnostics: _showDiagnostics,
          ).messages,
        ),
      );
    }
  }

  /// Asks aiku for the menus again without a loading screen, so permission
  /// changes made while the app was open show up.
  ///
  /// The current area stays if the user still has it, otherwise the default
  /// opens. If aiku can't be reached, the menus already shown stay. After a
  /// failed load this is a plain [load].
  Future<void> refresh() async {
    switch (_state) {
      case NavigationLoading():
        return;
      case NavigationFailed():
        return load();
      case NavigationReady() when _isRefreshing:
        return;
      case NavigationReady():
        break;
    }
    _isRefreshing = true;
    try {
      final data = await _client.navigation(_token);
      // The user may have switched area while aiku was answering.
      if (_state case NavigationReady(area: final current)) {
        final area = data.areaWithKey(current.key) ?? data.defaultArea;
        _show(
          area == null
              ? const NavigationFailed([nothingToOpenMessage])
              : NavigationReady(data, area),
        );
      }
    } on Unauthorised {
      await _onUnauthorised();
    } on ApiFailure {
      // The menus already shown stay; the next refresh tries again.
    } finally {
      _isRefreshing = false;
    }
  }

  /// Switches to [area] and remembers it on the phone.
  Future<void> selectArea(Area area) async {
    final current = _state;
    if (current is! NavigationReady) {
      return;
    }
    _show(NavigationReady(current.data, area));
    await _memory.write(_username, area.key);
  }

  @override
  void dispose() {
    _isDisposed = true;
    super.dispose();
  }

  // The shell can close (sign-out, a 401) while aiku is still answering.
  void _show(NavigationState state) {
    if (_isDisposed) {
      return;
    }
    _state = state;
    notifyListeners();
  }
}
