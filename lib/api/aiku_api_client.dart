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

  Uri _mobileUrl(String path) =>
      Uri.parse('${_config.apiBaseUrl}/app/api/mobile/$path');

  Future<http.Response> _post(
    Uri url,
    Map<String, Object?> body, {
    String? token,
  }) async {
    final http.Response response;
    try {
      response = await _httpClient
          .post(
            url,
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-Aiku-App-Key': _config.appKey,
              if (token != null) 'Authorization': 'Bearer $token',
            },
            body: jsonEncode(body),
          )
          .timeout(_timeout);
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
