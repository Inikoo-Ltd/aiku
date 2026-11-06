import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;

import 'api/aiku_api_client.dart';
import 'app.dart';
import 'auth/auth_controller.dart';
import 'auth/device_name.dart';
import 'auth/token_store.dart';
import 'config/app_config.dart';
import 'home/appearance.dart';
import 'home/area_memory.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  LicenseRegistry.addLicense(_gelasioLicence);

  final config = AppConfig.fromEnvironment();
  if (!config.isComplete) {
    throw StateError(
      'This build has no aiku server settings. Run it with '
      '--dart-define-from-file=dart_define/<env>.json',
    );
  }

  final client = AikuApiClient(config: config, httpClient: http.Client());
  final controller = AuthController(
    client: client,
    tokenStore: SecureTokenStore(),
    deviceName: readAndroidDeviceName,
  );
  // The app opens on a loading screen and switches once the saved session
  // has been read, so nothing waits on this here.
  unawaited(controller.restore());

  final appearance = AppearanceController(store: SecureAppearanceStore());
  // The app starts in the phone's appearance and switches if another was
  // saved, so nothing waits on this either.
  unawaited(appearance.restore());

  runApp(
    AikuApp(
      controller: controller,
      client: client,
      config: config,
      areaMemory: SecureAreaMemory(),
      appearance: appearance,
    ),
  );
}

/// The SIL Open Font Licence travels with the bundled Gelasio font.
Stream<LicenseEntry> _gelasioLicence() async* {
  final licence = await rootBundle.loadString('assets/fonts/OFL.txt');
  yield LicenseEntryWithLineBreaks(const ['Gelasio'], licence);
}
