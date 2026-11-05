/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:aiku_mobile/api/login_result.dart';
import 'package:aiku_mobile/ui/accent_shades.dart';
import 'package:aiku_mobile/ui/aiku_theme.dart';
import 'package:flutter/painting.dart';
import 'package:flutter_test/flutter_test.dart';

AikuThemePreset _preset(String name) =>
    aikuThemePresets.firstWhere((preset) => preset.name == name);

void main() {
  test('the effective theme is the mobile theme, then the web theme, then the default', () {
    final web = _preset('Rosewater Blush').colours;
    final mobile = _preset('Amoled').colours;

    expect(
      effectiveTheme(
        StaffProfile(
          username: 'u',
          contactName: null,
          appTheme: web,
          mobileAppTheme: mobile,
        ),
      ),
      mobile,
    );
    expect(
      effectiveTheme(
        StaffProfile(username: 'u', contactName: null, appTheme: web),
      ),
      web,
    );
    expect(
      effectiveTheme(
        const StaffProfile(username: 'u', contactName: null, appTheme: null),
      ),
      aikuThemePresets.first.colours,
    );
  });

  test('the signed-in theme keeps the exact accent as the primary colour', () {
    final theme = signedInThemeData(
      AccentShades.fromAppTheme(_preset('UK AW Distributions').colours),
    );

    expect(theme.colorScheme.primary, const Color(0xFFE87928));
  });

  test('text on the accent follows the luminance rule, not theme[5]', () {
    final lightBlue = signedInThemeData(
      AccentShades.fromAppTheme(_preset('Spain Distribution 2').colours),
    );
    final indigo = signedInThemeData(
      AccentShades.fromAppTheme(_preset('Deep Sea Serenity').colours),
    );

    expect(lightBlue.colorScheme.onPrimary, const Color(0xFF111827));
    expect(indigo.colorScheme.onPrimary, const Color(0xFFFFFFFF));
  });

  test('presets match regardless of hex case', () {
    final upper = [
      for (final colour in _preset('Amoled').colours) colour.toUpperCase(),
    ];

    expect(_preset('Amoled').matches(upper), isTrue);
    expect(_preset('Deep Sea Serenity').matches(upper), isFalse);
  });
}
