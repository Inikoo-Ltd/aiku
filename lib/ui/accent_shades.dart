/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/painting.dart';

import 'aiku_palette.dart';

const _black = Color(0xFF000000);
const _white = Color(0xFFFFFFFF);
const _darkText = Color(0xFF111827);

/// The accent and the shades aiku derives from it in `useAppAccent.ts`.
class const AccentShades({
  required final Color accent,
  required final Color strong,
  required final Color deep,
  required final Color soft,
  required final Color muted,
  required final Color onAccent,
}) {
  /// Derives the shades exactly as aiku's web does.
  factory AccentShades.from(Color accent) => AccentShades(
    accent: accent,
    strong: Color.lerp(accent, _black, 0.2)!,
    deep: Color.lerp(accent, _black, 0.4)!,
    soft: Color.lerp(accent, _white, 0.9)!,
    muted: Color.lerp(accent, _white, 0.75)!,
    onAccent: accent.computeLuminance() < 0.4 ? _white : _darkText,
  );

  /// Uses `theme[4]` of the user's aiku theme, or the default accent when
  /// the theme is missing or unreadable.
  factory AccentShades.fromAppTheme(List<String>? appTheme) {
    final hex = appTheme != null && appTheme.length > 4 ? appTheme[4] : null;
    return AccentShades.from(_parseHex(hex) ?? AikuPalette.defaultAccent);
  }

  static Color? _parseHex(String? hex) {
    if (hex == null || !RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(hex)) {
      return null;
    }
    return Color(0xFF000000 | int.parse(hex.substring(1), radix: 16));
  }
}
