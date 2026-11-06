/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:aiku_mobile/api/login_result.dart';
import 'package:aiku_mobile/ui/accent_shades.dart';
import 'package:aiku_mobile/ui/aiku_theme.dart';
import 'package:aiku_mobile/ui/app_colours.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

AikuThemePreset _preset(String name) =>
    aikuThemePresets.firstWhere((preset) => preset.name == name);

Color _accentOf(String preset) =>
    AccentShades.fromAppTheme(_preset(preset).colours).accent;

AppColours _colours(String preset, Brightness brightness) =>
    signedInThemeData(_accentOf(preset), brightness).extension<AppColours>()!;

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

  test(
    'in light the accent is exactly theme[4], as the primary and the band',
    () {
      final theme = signedInThemeData(
        _accentOf('UK AW Distributions'),
        Brightness.light,
      );

      expect(theme.colorScheme.primary, const Color(0xFFE87928));
      expect(theme.extension<AppColours>()!.band, const Color(0xFFE87928));
      expect(theme.appBarTheme.backgroundColor, const Color(0xFFE87928));
    },
  );

  test('text on the accent follows the luminance rule, not theme[5]', () {
    final lightBlue = signedInThemeData(
      _accentOf('Spain Distribution 2'),
      Brightness.light,
    );
    final indigo = signedInThemeData(
      _accentOf('Deep Sea Serenity'),
      Brightness.light,
    );

    expect(lightBlue.colorScheme.onPrimary, const Color(0xFF111827));
    expect(indigo.colorScheme.onPrimary, const Color(0xFFFFFFFF));
    expect(lightBlue.extension<AppColours>()!.onBand, const Color(0xFF111827));
  });

  test('accent text on white is darkened only when the accent is too pale', () {
    final pale = _colours('Spain Distribution 2', Brightness.light);
    final indigo = _colours('Deep Sea Serenity', Brightness.light);

    expect(pale.accentText, isNot(_accentOf('Spain Distribution 2')));
    expect(
      contrastRatio(pale.accentText, pale.background),
      greaterThanOrEqualTo(4.5),
    );
    expect(indigo.accentText, _accentOf('Deep Sea Serenity'));
  });

  test('in dark only an accent too dark to see is lightened', () {
    final black = _colours('Pure Black and White', Brightness.dark);
    final orange = _colours('UK AW Distributions', Brightness.dark);

    expect(black.accent, isNot(const Color(0xFF000000)));
    expect(
      contrastRatio(black.accent, black.background),
      greaterThanOrEqualTo(3),
    );
    expect(orange.accent, const Color(0xFFE87928));
  });

  test('every preset reads in dark: text, accent text and band highlights', () {
    for (final preset in aikuThemePresets) {
      final dark = _colours(preset.name, Brightness.dark);

      expect(
        contrastRatio(dark.ink, dark.background),
        greaterThanOrEqualTo(7),
        reason: preset.name,
      );
      expect(
        contrastRatio(dark.accentText, dark.background),
        greaterThanOrEqualTo(4.5),
        reason: preset.name,
      );
      expect(
        contrastRatio(dark.bandHighlight, dark.band),
        greaterThanOrEqualTo(3),
        reason: preset.name,
      );
      expect(
        contrastRatio(dark.onBand, dark.band),
        greaterThanOrEqualTo(4.5),
        reason: preset.name,
      );
    }
  });

  test('the dark page is a dark grey, not black', () {
    for (final preset in aikuThemePresets) {
      final dark = _colours(preset.name, Brightness.dark);

      expect(dark.background, const Color(0xFF202124), reason: preset.name);
      expect(
        contrastRatio(dark.background, const Color(0xFF000000)),
        greaterThan(1.25),
        reason: preset.name,
      );
    }
  });

  test('status colours stay amber, green and red in both appearances', () {
    final light = _colours('Amoled', Brightness.light);
    final dark = _colours('Amoled', Brightness.dark);

    expect(light.blockedText, const Color(0xFF92400E));
    expect(dark.blockedText, const Color(0xFFFCD34D));
    expect(light.doneText, const Color(0xFF15803D));
    expect(dark.doneText, const Color(0xFF4ADE80));
    expect(
      _colours('Rosewater Blush', Brightness.dark).blockedText,
      dark.blockedText,
    );
  });

  test('presets match regardless of hex case', () {
    final upper = [
      for (final colour in _preset('Amoled').colours) colour.toUpperCase(),
    ];

    expect(_preset('Amoled').matches(upper), isTrue);
    expect(_preset('Deep Sea Serenity').matches(upper), isFalse);
  });
}
