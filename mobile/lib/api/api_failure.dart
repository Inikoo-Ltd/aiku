/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

/// Why a call to aiku did not succeed.
sealed class ApiFailure implements Exception {
  const ApiFailure();
}

/// The credentials were right, and aiku now wants the one-time password.
final class OtpRequired(final String message) extends ApiFailure;

/// aiku rejected the request with Laravel's 422 validation errors.
final class ValidationFailure(
  final String message,
  final Map<String, List<String>> fieldErrors,
) extends ApiFailure {
  /// The first message for [field], if aiku sent one.
  String? firstErrorFor(String field) => fieldErrors[field]?.firstOrNull;
}

/// The token is missing, expired or revoked.
final class Unauthorised extends ApiFailure {
  const Unauthorised();
}

/// aiku refused with a 403: the user isn't allowed to see or do this.
final class Forbidden(final Uri url) extends ApiFailure;

/// A 404 from a mobile route: either the app key was refused or the server
/// has no mobile routes. aiku answers both the same way on purpose.
final class AppNotAccepted(final Uri url) extends ApiFailure;

/// aiku's route throttle refused the request.
final class TooManyRequests(final String message) extends ApiFailure;

/// aiku failed with a 5xx.
final class ServerError(final int statusCode, final Uri url) extends ApiFailure;

/// aiku answered with a status the app does not expect.
final class UnexpectedStatus(final int statusCode, final Uri url)
    extends ApiFailure;

/// aiku answered 2xx with a body the app cannot read.
final class InvalidResponse(final Uri url) extends ApiFailure;

/// aiku could not be reached, or did not answer in time.
final class NetworkFailure extends ApiFailure {
  const NetworkFailure();
}
