/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:async';
import 'dart:convert';

import 'package:aiku_mobile/api/aiku_api_client.dart';
import 'package:aiku_mobile/app.dart';
import 'package:aiku_mobile/auth/auth_controller.dart';
import 'package:aiku_mobile/auth/login_screen.dart';
import 'package:aiku_mobile/auth/two_factor_screen.dart';
import 'package:aiku_mobile/config/app_config.dart';
import 'package:aiku_mobile/ui/aiku_text_field.dart';
import 'package:aiku_mobile/ui/loading_indicator.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import '../support/in_memory_token_store.dart';

/// Pumps the whole app, signed out, on a phone-sized screen.
Future<AuthController> _pumpSignedOutApp(
  WidgetTester tester,
  Future<http.Response> Function(http.Request request) handler,
) async {
  tester.view.physicalSize = const Size(1080, 2340);
  tester.view.devicePixelRatio = 2.75;
  addTearDown(tester.view.reset);

  final client = AikuApiClient(
    config: const AppConfig(apiBaseUrl: 'http://aiku.example', appKey: 'key'),
    httpClient: MockClient(handler),
  );
  final controller = AuthController(
    client: client,
    tokenStore: InMemoryTokenStore(),
    deviceName: () async => 'Test Phone · Android 15',
    showDiagnostics: false,
  );
  addTearDown(controller.dispose);
  await controller.restore();

  await tester.pumpWidget(AikuApp(controller: controller, client: client));
  await tester.pump();
  return controller;
}

http.Response _otpRequired() => http.Response(
  jsonEncode({
    'message': 'Enter OTP from your Authenticator App',
    'code': 'otp_required',
    'errors': {
      'one_time_password': ['Enter OTP from your Authenticator App'],
    },
  }),
  422,
);

AikuTextField _fieldAround(WidgetTester tester, Key fieldKey) =>
    tester.widget<AikuTextField>(
      find.ancestor(
        of: find.byKey(fieldKey),
        matching: find.byType(AikuTextField),
      ),
    );

Future<void> _signIn(WidgetTester tester) async {
  await tester.enterText(find.byKey(LoginScreen.usernameFieldKey), 'raul');
  await tester.enterText(find.byKey(LoginScreen.passwordFieldKey), 'secret');
  await tester.tap(find.byKey(LoginScreen.signInButtonKey));
  await tester.pump();
}

void main() {
  testWidgets('the login screen shows its labels and placeholders', (
    tester,
  ) async {
    await _pumpSignedOutApp(tester, (_) async => http.Response('', 500));

    final username = _fieldAround(tester, LoginScreen.usernameFieldKey);
    final password = _fieldAround(tester, LoginScreen.passwordFieldKey);

    expect((username.label, username.hint), ('Username', 'Username'));
    expect((password.label, password.hint), ('Password', '********'));
    expect(find.byKey(LoginScreen.forgotPasswordKey), findsOneWidget);
  });

  testWidgets(
    'sign in is disabled and shows the loading indicator while the request runs',
    (tester) async {
      final response = Completer<http.Response>();
      await _pumpSignedOutApp(tester, (_) => response.future);

      await _signIn(tester);

      final button = find.byKey(LoginScreen.signInButtonKey);
      expect(
        tester
            .widget<FilledButton>(
              find.descendant(of: button, matching: find.byType(FilledButton)),
            )
            .onPressed,
        isNull,
      );
      expect(
        find.descendant(of: button, matching: find.byType(LoadingIndicator)),
        findsOneWidget,
      );

      response.complete(_otpRequired());
      await tester.pump();
    },
  );

  testWidgets('a field error appears under its field', (tester) async {
    await _pumpSignedOutApp(
      tester,
      (_) async => http.Response(
        jsonEncode({
          'message': 'These credentials do not match our records.',
          'errors': {
            'username': ['These credentials do not match our records.'],
          },
        }),
        422,
      ),
    );

    await _signIn(tester);
    await tester.pump(const Duration(milliseconds: 400));

    expect(
      _fieldAround(tester, LoginScreen.usernameFieldKey).errorText,
      'These credentials do not match our records.',
    );
    expect(
      _fieldAround(tester, LoginScreen.passwordFieldKey).errorText,
      isNull,
    );
  });

  testWidgets('otp_required moves to the two-factor screen', (tester) async {
    await _pumpSignedOutApp(tester, (_) async => _otpRequired());

    await _signIn(tester);
    await tester.pump();

    expect(find.byKey(TwoFactorScreen.codeFieldKey), findsOneWidget);
    expect(find.byKey(LoginScreen.usernameFieldKey), findsNothing);
  });

  testWidgets(
    'the two-factor sign-out button returns to login with the fields cleared',
    (tester) async {
      await _pumpSignedOutApp(tester, (_) async => _otpRequired());
      await _signIn(tester);
      await tester.pump();

      await tester.tap(find.byKey(TwoFactorScreen.signOutButtonKey));
      await tester.pump();

      expect(find.byKey(TwoFactorScreen.codeFieldKey), findsNothing);
      expect(
        tester
            .widget<TextField>(find.byKey(LoginScreen.usernameFieldKey))
            .controller
            ?.text,
        isEmpty,
      );
      expect(
        tester
            .widget<TextField>(find.byKey(LoginScreen.passwordFieldKey))
            .controller
            ?.text,
        isEmpty,
      );
    },
  );
}
