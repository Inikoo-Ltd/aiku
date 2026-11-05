/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../api/login_result.dart';
import 'accent_shades.dart';
import 'aiku_palette.dart';

/// One of aiku's theme presets: eight colours, of which slot 4 is the accent.
class const AikuThemePreset(final String name, final List<String> colours) {
  /// Whether [theme] is this preset, comparing colours case-insensitively.
  bool matches(List<String>? theme) =>
      theme != null &&
      theme.length >= colours.length &&
      [
        for (var slot = 0; slot < colours.length; slot++)
          theme[slot].toLowerCase() == colours[slot].toLowerCase(),
      ].every((same) => same);
}

/// aiku's presets, as listed in `useColorTheme` (resources/js/Composables/
/// useStockList.ts). The first is aiku's default.
const aikuThemePresets = [
  AikuThemePreset('Deep Sea Serenity', [
    '#4f46e5',
    '#f3f4f6',
    '#362cdb',
    '#fde047',
    '#4f46e5',
    '#f3f4f6',
    '#fcd34d',
    '#374151',
  ]),
  AikuThemePreset('Rosewater Blush', [
    '#f43f5e',
    '#F5F5F5',
    '#E3B7C8',
    '#000000',
    '#D6C7E2',
    '#000000',
    '#fca5a5',
    '#374151',
  ]),
  AikuThemePreset('Amoled', [
    '#000000',
    '#f2f2f2',
    '#424242',
    '#fde047',
    '#8e44ad',
    '#ffffff',
    '#fcd34d',
    '#374151',
  ]),
  AikuThemePreset('Black and White (Retina)', [
    '#0F1626',
    '#FFFFFF',
    '#1e293b',
    '#f1f5f9',
    '#0f172a',
    '#e2e8f0',
    '#cccccc',
    '#424242',
  ]),
  AikuThemePreset('UK AW Distributions', [
    '#957A65',
    '#FFFFFF',
    '#4B5058',
    '#FFFFFF',
    '#E87928',
    '#FFFFFF',
    '#cccccc',
    '#957A65',
  ]),
  AikuThemePreset('Spain Distribution 1', [
    '#A57FBC',
    '#FFFFFF',
    '#4B5058',
    '#FFFFFF',
    '#A57FBC',
    '#FFFFFF',
    '#cccccc',
    '#957A65',
  ]),
  AikuThemePreset('Spain Distribution 2', [
    '#8DBAD8',
    '#FFFFFF',
    '#4B5058',
    '#FFFFFF',
    '#8DBAD8',
    '#FFFFFF',
    '#cccccc',
    '#957A65',
  ]),
  AikuThemePreset('Spain Distribution 3', [
    '#C1A027',
    '#FFFFFF',
    '#4B5058',
    '#FFFFFF',
    '#C1A027',
    '#FFFFFF',
    '#cccccc',
    '#957A65',
  ]),
  AikuThemePreset('Spain Distribution 4', [
    '#C48497',
    '#FFFFFF',
    '#4B5058',
    '#FFFFFF',
    '#C48497',
    '#FFFFFF',
    '#cccccc',
    '#957A65',
  ]),
  AikuThemePreset('Spain Distribution 5', [
    '#909B44',
    '#FFFFFF',
    '#4B5058',
    '#FFFFFF',
    '#909B44',
    '#FFFFFF',
    '#cccccc',
    '#957A65',
  ]),
  AikuThemePreset('Pure Black and White', [
    '#000000',
    '#ffffff',
    '#000000',
    '#ffffff',
    '#000000',
    '#ffffff',
    '#000000',
    '#ffffff',
  ]),
];

/// The theme the app shows for [profile]: the mobile theme once one has been
/// picked, otherwise the user's web theme, otherwise aiku's default.
List<String> effectiveTheme(StaffProfile profile) {
  for (final theme in [profile.mobileAppTheme, profile.appTheme]) {
    if (theme != null && theme.length > 4) {
      return theme;
    }
  }
  return aikuThemePresets.first.colours;
}

/// Material theme for the signed-in app.
///
/// The accent is aiku's `theme[4]` exactly, with text on it chosen by
/// luminance. Everything else (containers, surfaces, outlines) comes from a
/// tonal palette built on the accent with the fidelity variant, which keeps
/// the accent's hue and colourfulness, so the rest of the app sits in tones
/// that suit a phone screen around the user's colour.
ThemeData signedInThemeData(AccentShades shades) {
  final scheme = ColorScheme.fromSeed(
    seedColor: shades.accent,
    dynamicSchemeVariant: DynamicSchemeVariant.fidelity,
  ).copyWith(primary: shades.accent, onPrimary: shades.onAccent);

  return ThemeData(
    colorScheme: scheme,
    scaffoldBackgroundColor: scheme.surfaceContainerLowest,
    appBarTheme: AppBarTheme(
      backgroundColor: scheme.surfaceContainerLowest,
      foregroundColor: AikuPalette.ink,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 1,
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: const BorderRadius.all(Radius.circular(8)),
        side: BorderSide(color: scheme.outlineVariant),
      ),
    ),
    textSelectionTheme: TextSelectionThemeData(
      cursorColor: shades.accent,
      selectionHandleColor: shades.accent,
      selectionColor: shades.muted,
    ),
  );
}
