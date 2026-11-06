/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/services.dart';

/// The longest `device_name` aiku accepts.
const maximumDeviceNameLength = 100;

/// Names this phone for its aiku token, e.g. "Google Pixel 8 · Android 15".
Future<String> readAndroidDeviceName() async {
  try {
    final info = await DeviceInfoPlugin().androidInfo;
    final name =
        '${info.manufacturer} ${info.model} · Android ${info.version.release}';
    return name.length > maximumDeviceNameLength
        ? name.substring(0, maximumDeviceNameLength)
        : name;
  } on PlatformException {
    return 'Android device';
  }
}
