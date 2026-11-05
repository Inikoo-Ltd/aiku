/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import '../api/api_failure.dart';

/// Errors to show on a form: one message under each field that has one, and
/// a list under the form for everything else.
class const FormErrors({
  final Map<String, String> fieldErrors = const {},
  final List<String> messages = const [],
}) {
  /// No errors.
  static const none = FormErrors();

  /// Turns [failure] into what a form shows.
  ///
  /// Field errors for [fieldsOnScreen] go under their field; aiku's messages
  /// are shown as sent. With [showDiagnostics] (debug builds), messages about
  /// the server also name the status and address, so a wrong key can be told
  /// apart from a server without the mobile routes.
  factory FormErrors.from(
    ApiFailure failure, {
    required Set<String> fieldsOnScreen,
    required bool showDiagnostics,
  }) {
    String withDiagnostics(String message, String detail) =>
        showDiagnostics ? '$message ($detail)' : message;

    return switch (failure) {
      ValidationFailure(:final message, :final fieldErrors) => _fromValidation(
        message,
        fieldErrors,
        fieldsOnScreen,
      ),
      AppNotAccepted(:final url) => FormErrors(
        messages: [withDiagnostics(appNotAcceptedMessage, '404 from $url')],
      ),
      TooManyRequests(:final message) => FormErrors(
        messages: [message.isEmpty ? tooManyRequestsMessage : message],
      ),
      NetworkFailure() => const FormErrors(messages: [networkFailureMessage]),
      ServerError(:final statusCode, :final url) ||
      UnexpectedStatus(:final statusCode, :final url) => FormErrors(
        messages: [
          withDiagnostics(serverProblemMessage, '$statusCode from $url'),
        ],
      ),
      InvalidResponse(:final url) => FormErrors(
        messages: [
          withDiagnostics(serverProblemMessage, 'unreadable reply from $url'),
        ],
      ),
      OtpRequired() ||
      Unauthorised() => const FormErrors(messages: [serverProblemMessage]),
    };
  }

  /// Shown for a 404 from a mobile route.
  static const appNotAcceptedMessage =
      "Can't sign in from this app. Make sure it's up to date, or ask your manager for help.";

  /// Shown when aiku can't be reached.
  static const networkFailureMessage =
      "Can't reach aiku. Check your connection and try again.";

  /// Shown when aiku fails on its side.
  static const serverProblemMessage =
      "Something went wrong on aiku's side. Please try again.";

  /// Shown for a 429 that came without a message.
  static const tooManyRequestsMessage =
      'Too many attempts. Please wait a minute and try again.';

  /// Whether there is nothing to show.
  bool get isEmpty => fieldErrors.isEmpty && messages.isEmpty;

  /// The message to show under [field], if any.
  String? forField(String field) => fieldErrors[field];

  static FormErrors _fromValidation(
    String message,
    Map<String, List<String>> errors,
    Set<String> fieldsOnScreen,
  ) {
    final fieldErrors = <String, String>{};
    final messages = <String>[];
    for (final MapEntry(key: field, value: fieldMessages) in errors.entries) {
      if (fieldMessages.isEmpty) {
        continue;
      }
      if (fieldsOnScreen.contains(field)) {
        fieldErrors[field] = fieldMessages.first;
      } else {
        messages.addAll(fieldMessages);
      }
    }
    if (fieldErrors.isEmpty && messages.isEmpty && message.isNotEmpty) {
      messages.add(message);
    }
    return FormErrors(fieldErrors: fieldErrors, messages: messages);
  }
}
