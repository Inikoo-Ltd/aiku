/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import 'api_failure.dart';
import 'login_result.dart';
import 'navigation.dart';

/// Talks to aiku's mobile staff API under `/app/api/mobile`.
///
/// Every request carries `X-Aiku-App-Key`. Failures are thrown as
/// [ApiFailure]s; nothing here logs credentials, codes or tokens.
class AikuApiClient {
  AikuApiClient({
    required this._config,
    required this._httpClient,
    this._timeout = const Duration(seconds: 20),
  });

  final AppConfig _config;
  final http.Client _httpClient;
  final Duration _timeout;

  /// Signs in and returns a token that lasts until [logout].
  ///
  /// Throws [OtpRequired] when the user has two-factor set up and no
  /// [oneTimePassword] was sent.
  Future<LoginResult> login({
    required String username,
    required String password,
    required String deviceName,
    String? oneTimePassword,
  }) async {
    final url = _mobileUrl('login');
    final response = await _post(url, {
      'username': username,
      'password': password,
      'device_name': deviceName,
      'one_time_password': ?oneTimePassword,
    });

    if (_decodeObject(response.body)
        case {
              'token': final String token,
              'user': final Map<String, Object?> user,
            } &&
            final body) {
      final expiresAt = body['expires_at'];
      return LoginResult(
        token: token,
        expiresAt: expiresAt is String ? DateTime.tryParse(expiresAt) : null,
        profile: StaffProfile.fromJson(user),
      );
    }
    throw InvalidResponse(url);
  }

  /// Deletes the token on aiku.
  Future<void> logout(String token) async {
    await _post(_mobileUrl('logout'), const {}, token: token);
  }

  /// Asks aiku to email a password reset link to [email].
  ///
  /// aiku answers the same way whether or not the email belongs to anyone.
  Future<void> requestPasswordResetLink(String email) async {
    await _post(_mobileUrl('password/reset-link'), {'email': email});
  }

  /// Saves [theme] (8 colours) as the user's mobile theme on aiku and
  /// returns the updated profile. The web theme is not touched.
  Future<StaffProfile> updateMobileAppTheme({
    required String token,
    required List<String> theme,
  }) async {
    final url = _mobileUrl('profile/theme');
    final response = await _post(
      url,
      {'mobile_app_theme': theme},
      token: token,
      method: 'PATCH',
    );

    return _profileFrom(response, url);
  }

  /// The signed-in user's profile as aiku has it now, with both themes.
  Future<StaffProfile> profile(String token) async {
    final url = _mobileUrl('profile');
    final response = await _send('GET', url, token: token);

    return _profileFrom(response, url);
  }

  StaffProfile _profileFrom(http.Response response, Uri url) {
    if (_decodeObject(response.body) case {
      'data': final Map<String, Object?> profile,
    }) {
      return StaffProfile.fromJson(profile);
    }
    throw InvalidResponse(url);
  }

  /// The areas and menus aiku builds for the signed-in user, limited by
  /// their permissions exactly as the web sidebar is.
  Future<NavigationData> navigation(String token) async {
    final url = _mobileUrl('navigation');
    final response = await _send('GET', url, token: token);

    return NavigationData.fromJson(_decodeObject(response.body)) ??
        (throw InvalidResponse(url));
  }

  Uri _mobileUrl(String path) =>
      Uri.parse('${_config.apiBaseUrl}/app/api/mobile/$path');

  Future<http.Response> _post(
    Uri url,
    Map<String, Object?> body, {
    String? token,
    String method = 'POST',
  }) => _send(method, url, body: body, token: token);

  Future<http.Response> _send(
    String method,
    Uri url, {
    Map<String, Object?>? body,
    String? token,
  }) async {
    final request = http.Request(method, url)
      ..headers.addAll({
        'Accept': 'application/json',
        'X-Aiku-App-Key': _config.appKey,
        if (body != null) 'Content-Type': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      });
    if (body != null) {
      request.body = jsonEncode(body);
    }

    final http.Response response;
    try {
      response = await http.Response.fromStream(
        await _httpClient.send(request).timeout(_timeout),
      ).timeout(_timeout);
    } on http.ClientException {
      throw const NetworkFailure();
    } on TimeoutException {
      throw const NetworkFailure();
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return response;
    }
    throw _failureFor(response, url);
  }

  ApiFailure _failureFor(http.Response response, Uri url) {
    final body = _decodeObject(response.body) ?? const <String, Object?>{};
    final message = switch (body['message']) {
      final String text => text,
      _ => '',
    };

    return switch (response.statusCode) {
      401 => const Unauthorised(),
      404 => AppNotAccepted(url),
      422 when body['code'] == 'otp_required' => OtpRequired(message),
      422 => ValidationFailure(message, _fieldErrors(body['errors'])),
      429 => TooManyRequests(message),
      final status when status >= 500 => ServerError(status, url),
      final status => UnexpectedStatus(status, url),
    };
  }

  static Map<String, List<String>> _fieldErrors(Object? errors) {
    if (errors is! Map<String, Object?>) {
      return const {};
    }
    return {
      for (final MapEntry(:key, :value) in errors.entries)
        if (value is List<Object?>) key: value.whereType<String>().toList(),
    };
  }

  static Map<String, Object?>? _decodeObject(String body) {
    try {
      return switch (jsonDecode(body)) {
        final Map<String, Object?> object => object,
        _ => null,
      };
    } on FormatException {
      return null;
    }
  }
}
