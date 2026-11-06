/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/foundation.dart';

import '../api/aiku_api_client.dart';
import '../api/api_failure.dart';
import 'form_errors.dart';

/// Asks aiku to email a password reset link.
class PasswordResetController extends ChangeNotifier {
  PasswordResetController({
    required this._client,
    this._showDiagnostics = kDebugMode,
  });

  /// The fields the forgot-password screen shows errors under.
  static const fields = {'email'};

  final AikuApiClient _client;
  final bool _showDiagnostics;

  bool _isSubmitting = false;
  bool _linkRequested = false;
  FormErrors _errors = FormErrors.none;

  /// Whether the request is running.
  bool get isSubmitting => _isSubmitting;

  /// Whether aiku accepted the request.
  bool get linkRequested => _linkRequested;

  /// Errors to show on the form.
  FormErrors get errors => _errors;

  /// Sends the request for [email].
  Future<void> requestLink(String email) async {
    if (_isSubmitting) {
      return;
    }
    _isSubmitting = true;
    _errors = FormErrors.none;
    notifyListeners();
    try {
      await _client.requestPasswordResetLink(email.trim());
      _linkRequested = true;
    } on ApiFailure catch (failure) {
      _errors = FormErrors.from(
        failure,
        fieldsOnScreen: fields,
        showDiagnostics: _showDiagnostics,
      );
    } finally {
      _isSubmitting = false;
      notifyListeners();
    }
  }
}
