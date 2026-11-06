/*
 * Author Louis Perez
 * Created on 06-11-2026-11h-06m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import 'aiku_palette.dart';

const _black = Color(0xFF000000);
const _white = Color(0xFFFFFFFF);
const _darkText = Color(0xFF111827);

/// WCAG contrast ratio between [a] and [b], from 1 to 21.
double contrastRatio(Color a, Color b) {
  final lighter = a.computeLuminance();
  final darker = b.computeLuminance();
  final (high, low) = lighter >= darker ? (lighter, darker) : (darker, lighter);
  return (high + 0.05) / (low + 0.05);
}

/// Text on [colour]: white below 0.4 relative luminance, otherwise dark, as
/// aiku's `useAppAccent.ts` decides.
Color textOn(Color colour) =>
    colour.computeLuminance() < 0.4 ? _white : _darkText;

/// [colour] moved towards [target] in small steps until it reads against
/// [background] at [ratio], or [colour] itself when it already does.
Color _readable(
  Color colour, {
  required Color towards,
  required Color background,
  required double ratio,
}) {
  for (var step = 0; step <= 20; step++) {
    final candidate = Color.lerp(colour, towards, step / 20)!;
    if (contrastRatio(candidate, background) >= ratio) {
      return candidate;
    }
  }
  return towards;
}

/// The signed-in app's colours for one brightness, built from the accent
/// of the user's aiku theme (`theme[4]`).
///
/// The theme shows on the header band, the chosen tabs and the accent text.
/// Status colours (amber for blocked, green for done, red for errors) never
/// follow the theme.
@immutable
class AppColours extends ThemeExtension<AppColours> {
  const AppColours({
    required this.brightness,
    required this.accent,
    required this.onAccent,
    required this.accentText,
    required this.band,
    required this.onBand,
    required this.onBandMuted,
    required this.bandHighlight,
    required this.background,
    required this.raised,
    required this.ink,
    required this.secondary,
    required this.muted,
    required this.hairline,
    required this.blockedBackground,
    required this.blockedText,
    required this.doneText,
    required this.errorText,
  });

  /// Light: the accent exactly as in aiku; only accent text on white is
  /// darkened when the accent is too pale to read.
  factory AppColours.light(Color themeAccent) {
    const background = _white;
    final onAccent = textOn(themeAccent);
    return AppColours(
      brightness: Brightness.light,
      accent: themeAccent,
      onAccent: onAccent,
      accentText: _readable(
        themeAccent,
        towards: _black,
        background: background,
        ratio: 4.5,
      ),
      band: themeAccent,
      onBand: onAccent,
      onBandMuted: onAccent.withValues(alpha: 0.72),
      bandHighlight: onAccent,
      background: background,
      raised: _white,
      ink: AikuPalette.ink,
      secondary: AikuPalette.label,
      muted: AikuPalette.placeholder,
      hairline: const Color(0xFFE5E7EB),
      blockedBackground: const Color(0xFFFEF3C7),
      blockedText: const Color(0xFF92400E),
      doneText: const Color(0xFF15803D),
      errorText: AikuPalette.errorText,
    );
  }

  /// Dark: a dark grey page rather than black, which made the band and the
  /// figures look harsh. An accent too dark to see on it is lightened within
  /// its own colour; the band is a deep tone of the accent, deep enough for
  /// white text, and highlights a light one.
  factory AppColours.dark(Color themeAccent) {
    const background = Color(0xFF202124);
    final accent = _readable(
      themeAccent,
      towards: _white,
      background: background,
      ratio: 3,
    );
    final band = _readable(
      Color.lerp(accent, background, 0.5)!,
      towards: _black,
      background: _white,
      ratio: 4.5,
    );
    final onBand = textOn(band);
    return AppColours(
      brightness: Brightness.dark,
      accent: accent,
      onAccent: textOn(accent),
      accentText: _readable(
        accent,
        towards: _white,
        background: background,
        ratio: 4.5,
      ),
      band: band,
      onBand: onBand,
      onBandMuted: onBand.withValues(alpha: 0.7),
      bandHighlight: _readable(
        Color.lerp(accent, _white, 0.45)!,
        towards: _white,
        background: band,
        ratio: 3,
      ),
      background: background,
      raised: const Color(0xFF2A2B2F),
      ink: const Color(0xFFE8EAED),
      secondary: const Color(0xFFBDC1C6),
      muted: const Color(0xFF9AA0A6),
      hairline: const Color(0xFF3C4043),
      blockedBackground: const Color(0xFF4A3A12),
      blockedText: const Color(0xFFFCD34D),
      doneText: const Color(0xFF4ADE80),
      errorText: const Color(0xFFF87171),
    );
  }

  /// The colours of the theme around [context]; outside the signed-in app
  /// (sign-in screens) the light colours of aiku's default accent.
  static AppColours of(BuildContext context) =>
      Theme.of(context).extension<AppColours>() ??
      AppColours.light(AikuPalette.defaultAccent);

  /// Light or dark.
  final Brightness brightness;

  /// Buttons, selected states and indicators.
  final Color accent;

  /// Text and icons on [accent].
  final Color onAccent;

  /// The accent as text or a thin line on [background], readable.
  final Color accentText;

  /// The header band behind the app bar, tabs and leading figure.
  final Color band;

  /// Text and icons on [band].
  final Color onBand;

  /// Quieter text on [band].
  final Color onBandMuted;

  /// The chosen tab's underline and the progress fill on [band].
  final Color bandHighlight;

  /// The page.
  final Color background;

  /// Sheets, menus and cards above the page.
  final Color raised;

  /// Main text and figures.
  final Color ink;

  /// Labels and supporting text.
  final Color secondary;

  /// Placeholders, captions and unchosen tabs.
  final Color muted;

  /// Lines between rows and sections.
  final Color hairline;

  /// Behind something blocked (always amber).
  final Color blockedBackground;

  /// Text of something blocked (always amber).
  final Color blockedText;

  /// Text of something done or dispatched (always green).
  final Color doneText;

  /// Error messages (always red).
  final Color errorText;

  @override
  AppColours copyWith({Color? accent}) => this;

  @override
  AppColours lerp(covariant AppColours? other, double t) {
    if (other == null) {
      return this;
    }
    Color mix(Color a, Color b) => Color.lerp(a, b, t)!;
    return AppColours(
      brightness: t < 0.5 ? brightness : other.brightness,
      accent: mix(accent, other.accent),
      onAccent: mix(onAccent, other.onAccent),
      accentText: mix(accentText, other.accentText),
      band: mix(band, other.band),
      onBand: mix(onBand, other.onBand),
      onBandMuted: mix(onBandMuted, other.onBandMuted),
      bandHighlight: mix(bandHighlight, other.bandHighlight),
      background: mix(background, other.background),
      raised: mix(raised, other.raised),
      ink: mix(ink, other.ink),
      secondary: mix(secondary, other.secondary),
      muted: mix(muted, other.muted),
      hairline: mix(hairline, other.hairline),
      blockedBackground: mix(blockedBackground, other.blockedBackground),
      blockedText: mix(blockedText, other.blockedText),
      doneText: mix(doneText, other.doneText),
      errorText: mix(errorText, other.errorText),
    );
  }
}
