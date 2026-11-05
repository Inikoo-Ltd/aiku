/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:convert';

import 'package:aiku_mobile/api/aiku_api_client.dart';
import 'package:aiku_mobile/api/api_failure.dart';
import 'package:aiku_mobile/config/app_config.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

const _config = AppConfig(
  apiBaseUrl: 'http://aiku.example',
  appKey: 'test-app-key',
);

AikuApiClient _clientAnswering(
  Future<http.Response> Function(http.Request request) handler,
) => AikuApiClient(config: _config, httpClient: MockClient(handler));

http.Response _json(Object body, int status) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

final _loginBody = {
  'token': '1|secret',
  'expires_at': null,
  'user': {
    'id': 7,
    'username': 'raul',
    'contact_name': 'Raul',
    'app_theme': ['#4f46e5', '#f3f4f6', '#362cdb', '#fde047', '#0f766e'],
  },
};

void main() {
  test('sends Accept and the app key on every request, and the bearer token on logout', () async {
    final requests = <http.Request>[];
    final client = _clientAnswering((request) async {
      requests.add(request);
      return request.url.path.endsWith('/login')
          ? _json(_loginBody, 200)
          : http.Response('', 204);
    });

    await client.login(username: 'raul', password: 'pw', deviceName: 'Phone');
    await client.logout('1|secret');

    expect(requests.map((request) => request.url.path), [
      '/app/api/mobile/login',
      '/app/api/mobile/logout',
    ]);
    for (final request in requests) {
      expect(request.headers['Accept'], 'application/json');
      expect(request.headers['X-Aiku-App-Key'], 'test-app-key');
    }
    expect(requests.first.headers.containsKey('Authorization'), isFalse);
    expect(requests.last.headers['Authorization'], 'Bearer 1|secret');
  });

  test('a 200 login returns the token, its expiry and the profile', () async {
    final client = _clientAnswering(
      (_) async =>
          _json({..._loginBody, 'expires_at': '2026-11-06T03:00:00Z'}, 200),
    );

    final result = await client.login(
      username: 'raul',
      password: 'pw',
      deviceName: 'Phone',
    );

    expect(result.token, '1|secret');
    expect(result.expiresAt, DateTime.utc(2026, 11, 6, 3));
    expect(result.profile.username, 'raul');
    expect(result.profile.contactName, 'Raul');
    expect(result.profile.appTheme?[4], '#0f766e');
  });

  test(
    'a 422 with code otp_required is told apart from validation errors',
    () async {
      final client = _clientAnswering(
        (_) async => _json({
          'message': 'Enter OTP from your Authenticator App',
          'code': 'otp_required',
          'errors': {
            'one_time_password': ['Enter OTP from your Authenticator App'],
          },
        }, 422),
      );

      await expectLater(
        client.login(username: 'raul', password: 'pw', deviceName: 'Phone'),
        throwsA(isA<OtpRequired>()),
      );
    },
  );

  test('422 field errors are parsed per field', () async {
    final client = _clientAnswering(
      (_) async => _json({
        'message': 'These credentials do not match our records.',
        'errors': {
          'username': ['These credentials do not match our records.'],
          'device_name': ['The device name field is required.'],
        },
      }, 422),
    );

    await expectLater(
      client.login(username: 'raul', password: 'bad', deviceName: 'Phone'),
      throwsA(
        isA<ValidationFailure>()
            .having(
              (failure) => failure.firstErrorFor('username'),
              'username error',
              'These credentials do not match our records.',
            )
            .having(
              (failure) => failure.firstErrorFor('device_name'),
              'device_name error',
              'The device name field is required.',
            ),
      ),
    );
  });

  test('a 404 means the app was not accepted, and names the address', () async {
    final client = _clientAnswering((_) async => http.Response('', 404));

    await expectLater(
      client.requestPasswordResetLink('raul@example.com'),
      throwsA(
        isA<AppNotAccepted>().having(
          (failure) => failure.url.path,
          'path',
          '/app/api/mobile/password/reset-link',
        ),
      ),
    );
  });

  test('a 401 is reported as unauthorised', () async {
    final client = _clientAnswering(
      (_) async => _json({'message': 'Unauthenticated.'}, 401),
    );

    await expectLater(client.logout('1|old'), throwsA(isA<Unauthorised>()));
  });

  test('a connection failure is reported as a network failure', () async {
    final client = _clientAnswering(
      (request) async => throw http.ClientException('offline', request.url),
    );

    await expectLater(
      client.login(username: 'raul', password: 'pw', deviceName: 'Phone'),
      throwsA(isA<NetworkFailure>()),
    );
  });
}
