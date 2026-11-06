/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../api/login_result.dart';
import 'app_colours.dart';

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

/// Material theme for the signed-in app, light or dark.
///
/// In light the accent is aiku's `theme[4]` exactly, with text on it chosen
/// by luminance; in dark an accent too dark to see is lightened within its
/// own colour (see [AppColours]). Other roles come from a tonal palette
/// built on the accent with the fidelity variant, which keeps the accent's
/// hue, so the app sits in tones around the user's colour. The app bar is
/// the theme-coloured band.
ThemeData signedInThemeData(Color themeAccent, Brightness brightness) {
  final colours = switch (brightness) {
    Brightness.light => AppColours.light(themeAccent),
    Brightness.dark => AppColours.dark(themeAccent),
  };
  final scheme =
      ColorScheme.fromSeed(
        seedColor: colours.accent,
        brightness: brightness,
        dynamicSchemeVariant: DynamicSchemeVariant.fidelity,
      ).copyWith(
        primary: colours.accent,
        onPrimary: colours.onAccent,
        surface: colours.background,
        onSurface: colours.ink,
        onSurfaceVariant: colours.secondary,
        outlineVariant: colours.hairline,
        error: colours.errorText,
      );
  final bandIsDark = colours.onBand.computeLuminance() > 0.5;

  return ThemeData(
    colorScheme: scheme,
    brightness: brightness,
    scaffoldBackgroundColor: colours.background,
    canvasColor: colours.background,
    dividerColor: colours.hairline,
    extensions: [colours],
    appBarTheme: AppBarTheme(
      backgroundColor: colours.band,
      foregroundColor: colours.onBand,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      systemOverlayStyle: bandIsDark
          ? SystemUiOverlayStyle.light
          : SystemUiOverlayStyle.dark,
    ),
    drawerTheme: DrawerThemeData(backgroundColor: colours.background),
    bottomSheetTheme: BottomSheetThemeData(
      backgroundColor: colours.raised,
      surfaceTintColor: Colors.transparent,
    ),
    popupMenuTheme: PopupMenuThemeData(
      color: colours.raised,
      surfaceTintColor: Colors.transparent,
    ),
    dividerTheme: DividerThemeData(color: colours.hairline),
    progressIndicatorTheme: ProgressIndicatorThemeData(
      color: colours.accentText,
    ),
    cardTheme: CardThemeData(
      color: colours.raised,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: const BorderRadius.all(Radius.circular(8)),
        side: BorderSide(color: colours.hairline),
      ),
    ),
    textSelectionTheme: TextSelectionThemeData(
      cursorColor: colours.accentText,
      selectionHandleColor: colours.accentText,
      selectionColor: colours.accent.withValues(alpha: 0.3),
    ),
  );
}
