/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../api/aiku_api_client.dart';
import '../api/api_failure.dart';
import '../api/login_result.dart';
import 'form_errors.dart';
import 'token_store.dart';

/// Where the user is in signing in.
sealed class AuthState {
  const AuthState();
}

/// Reading the saved session at start-up.
final class Restoring extends AuthState {
  const Restoring();
}

/// Nobody is signed in: show the login screen.
final class SignedOut extends AuthState {
  const SignedOut();
}

/// The credentials were right and aiku wants the one-time password.
final class AwaitingOneTimePassword extends AuthState {
  const AwaitingOneTimePassword();
}

/// Signed in with [session].
final class SignedIn(final StoredSession session) extends AuthState;

/// Runs sign-in, the two-factor step and sign-out.
///
/// Username and password are kept in memory only while the two-factor step
/// needs them, and are never stored or logged.
class AuthController extends ChangeNotifier {
  AuthController({
    required this._client,
    required this._tokenStore,
    required this._deviceName,
    this._now = DateTime.now,
    this._showDiagnostics = kDebugMode,
  });

  /// The fields the login screen shows errors under.
  static const loginFields = {'username', 'password'};

  /// The fields the two-factor screen shows errors under.
  static const oneTimePasswordFields = {'one_time_password'};

  final AikuApiClient _client;
  final TokenStore _tokenStore;
  final Future<String> Function() _deviceName;
  final DateTime Function() _now;
  final bool _showDiagnostics;

  AuthState _state = const Restoring();
  bool _isSubmitting = false;
  FormErrors _errors = FormErrors.none;
  ({String username, String password})? _pendingCredentials;
  List<String>? _themeBeingSaved;
  FormErrors _themeErrors = FormErrors.none;

  /// Which screen to show.
  AuthState get state => _state;

  /// Whether a request to aiku is running.
  bool get isSubmitting => _isSubmitting;

  /// Errors for the screen being shown.
  FormErrors get errors => _errors;

  /// The theme being sent to aiku right now, if any.
  List<String>? get themeBeingSaved => _themeBeingSaved;

  /// Why the last theme could not be saved to aiku.
  FormErrors get themeErrors => _themeErrors;

  /// Makes [theme] the mobile theme: on this phone at once, then on aiku.
  ///
  /// If aiku can't be reached the phone keeps the theme and [themeErrors]
  /// says so. A 401 means the token is gone, so the user is signed out.
  Future<void> chooseMobileTheme(List<String> theme) async {
    final current = _state;
    if (current is! SignedIn || _themeBeingSaved != null) {
      return;
    }
    final onDevice = StoredSession(
      token: current.session.token,
      expiresAt: current.session.expiresAt,
      profile: current.session.profile.withMobileAppTheme(theme),
    );
    await _tokenStore.save(onDevice);
    _themeBeingSaved = theme;
    _themeErrors = FormErrors.none;
    _state = SignedIn(onDevice);
    notifyListeners();

    try {
      final profile = await _client.updateMobileAppTheme(
        token: onDevice.token,
        theme: theme,
      );
      if (_state case SignedIn(:final session)
          when identical(session, onDevice)) {
        final saved = StoredSession(
          token: onDevice.token,
          expiresAt: onDevice.expiresAt,
          profile: profile,
        );
        await _tokenStore.save(saved);
        _state = SignedIn(saved);
      }
    } on Unauthorised {
      await _tokenStore.clear();
      _state = const SignedOut();
    } on ApiFailure catch (failure) {
      _themeErrors = FormErrors.from(
        failure,
        fieldsOnScreen: const {},
        showDiagnostics: _showDiagnostics,
      );
    } finally {
      _themeBeingSaved = null;
      notifyListeners();
    }
  }

  /// Opens the saved session, unless there is none or it has expired.
  Future<void> restore() async {
    final session = await _tokenStore.read();
    final expiresAt = session?.expiresAt;
    if (session != null && (expiresAt == null || expiresAt.isAfter(_now()))) {
      _show(SignedIn(session));
      return;
    }
    await _tokenStore.clear();
    _show(const SignedOut());
  }

  /// Signs in, or moves to the two-factor step when aiku asks for it.
  Future<void> signIn({
    required String username,
    required String password,
  }) async {
    if (_isSubmitting) {
      return;
    }
    _startSubmitting();
    try {
      final result = await _client.login(
        username: username,
        password: password,
        deviceName: await _deviceName(),
      );
      await _open(result);
    } on OtpRequired {
      _pendingCredentials = (username: username, password: password);
      _errors = FormErrors.none;
      _state = const AwaitingOneTimePassword();
    } on ApiFailure catch (failure) {
      _errors = FormErrors.from(
        failure,
        fieldsOnScreen: loginFields,
        showDiagnostics: _showDiagnostics,
      );
    } finally {
      _stopSubmitting();
    }
  }

  /// Sends the one-time password with the credentials from [signIn].
  Future<void> submitOneTimePassword(String oneTimePassword) async {
    final credentials = _pendingCredentials;
    if (_isSubmitting || credentials == null) {
      return;
    }
    _startSubmitting();
    try {
      final result = await _client.login(
        username: credentials.username,
        password: credentials.password,
        deviceName: await _deviceName(),
        oneTimePassword: oneTimePassword,
      );
      if (identical(_pendingCredentials, credentials)) {
        await _open(result);
      }
    } on ApiFailure catch (failure) {
      _errors = FormErrors.from(
        failure,
        fieldsOnScreen: oneTimePasswordFields,
        showDiagnostics: _showDiagnostics,
      );
    } finally {
      _stopSubmitting();
    }
  }

  /// Leaves the two-factor step and forgets the credentials.
  void cancelOneTimePassword() {
    _pendingCredentials = null;
    _show(const SignedOut());
  }

  /// Deletes the token on aiku, then on the device even if aiku can't be
  /// reached.
  Future<void> signOut() async {
    final current = _state;
    if (_isSubmitting || current is! SignedIn) {
      return;
    }
    _startSubmitting();
    try {
      await _client.logout(current.session.token);
    } on ApiFailure {
      // The token is forgotten on the device whatever aiku answered.
    } finally {
      await _tokenStore.clear();
      _isSubmitting = false;
      _show(const SignedOut());
    }
  }

  /// Asks aiku for the profile again, so a theme or name changed elsewhere
  /// shows without signing in again.
  ///
  /// The saved profile stays if aiku can't be reached, and nothing changes
  /// while a theme is being saved or the user is logging out. A 401 means
  /// the token is gone, so the user is signed out.
  Future<void> refreshProfile() async {
    final current = _state;
    if (current is! SignedIn || _themeBeingSaved != null || _isSubmitting) {
      return;
    }
    final session = current.session;
    try {
      final profile = await _client.profile(session.token);
      final isStillThisSession = switch (_state) {
        SignedIn(session: final now) => identical(now, session),
        _ => false,
      };
      if (!isStillThisSession ||
          _themeBeingSaved != null ||
          _isSubmitting ||
          _isSameProfile(profile, session.profile)) {
        return;
      }
      final refreshed = StoredSession(
        token: session.token,
        expiresAt: session.expiresAt,
        profile: profile,
      );
      await _tokenStore.save(refreshed);
      _state = SignedIn(refreshed);
      notifyListeners();
    } on Unauthorised {
      await sessionExpired();
    } on ApiFailure {
      // The saved profile stays; the next refresh tries again.
    }
  }

  /// aiku answered 401: the token is gone, so forget it and show login.
  Future<void> sessionExpired() async {
    if (_state is! SignedIn) {
      return;
    }
    await _tokenStore.clear();
    _show(const SignedOut());
  }

  Future<void> _open(LoginResult result) async {
    final session = StoredSession(
      token: result.token,
      expiresAt: result.expiresAt,
      profile: result.profile,
    );
    await _tokenStore.save(session);
    _pendingCredentials = null;
    _errors = FormErrors.none;
    _state = SignedIn(session);
  }

  void _startSubmitting() {
    _isSubmitting = true;
    _errors = FormErrors.none;
    notifyListeners();
  }

  void _stopSubmitting() {
    _isSubmitting = false;
    notifyListeners();
  }

  static bool _isSameProfile(StaffProfile a, StaffProfile b) =>
      jsonEncode(a.toJson()) == jsonEncode(b.toJson());

  void _show(AuthState state) {
    _errors = FormErrors.none;
    _themeErrors = FormErrors.none;
    _state = state;
    notifyListeners();
  }
}
