/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

/// The aiku server settings compiled into this build with
/// `--dart-define-from-file=dart_define/<env>.json`.
class const AppConfig({
  required final String apiBaseUrl,
  required final String appKey,
}) {
  /// Reads the settings this build was compiled with.
  factory AppConfig.fromEnvironment() => const AppConfig(
    apiBaseUrl: String.fromEnvironment('AIKU_API_BASE_URL'),
    appKey: String.fromEnvironment('AIKU_APP_KEY'),
  );

  /// Whether both settings were supplied at build time.
  bool get isComplete => apiBaseUrl.isNotEmpty && appKey.isNotEmpty;
}
