/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/painting.dart';

/// The fixed colours of aiku's sign-in pages, measured from the web login.
///
/// Sign-in happens before any user is known, so these never follow a user
/// theme. Signed-in screens take their accent from [AccentShades] instead.
abstract final class AikuPalette {
  /// Warm paper behind the watercolour pools.
  static const paper = Color(0xFFFBF7EE);

  /// The brand wordmark and body text.
  static const ink = Color(0xFF1C1B22);

  /// aiku's default accent, `theme[4]` of the default theme.
  static const defaultAccent = Color(0xFF4F46E5);

  /// The sign-in button while pressed.
  static const signInPressed = Color(0xFF4338CA);

  /// The sign-in button while disabled.
  static const signInDisabled = Color(0xFF3730A3);

  /// The sign-in button border, and an input's border while focused.
  static const focus = Color(0xFF6366F1);

  /// Text on the sign-in button.
  static const signInText = Color(0xFFF0FDFA);

  /// Field labels.
  static const label = Color(0xFF374151);

  /// Input borders.
  static const inputBorder = Color(0xFFD1D5DB);

  /// Placeholders and the password toggle icon.
  static const placeholder = Color(0xFF9CA3AF);

  /// The password toggle's background.
  static const toggleBackground = Color(0xFFF9FAFB);

  /// The exclamation icon inside a field with an error.
  static const errorIcon = Color(0xFFEF4444);

  /// Error messages.
  static const errorText = Color(0xFFDC2626);

  /// A green ✓ on a confirming button.
  static const confirm = Color(0xFF16A34A);

  /// A red ✕ on a cancelling button.
  static const cancel = Color(0xFFDC2626);

  /// The "Forgot password?" link.
  static const quietLink = Color(0xFF4B5563);

  /// The watercolour pools are drawn from these, shuffled on every load.
  static const watercolours = [
    Color(0xFF8B5CF6),
    Color(0xFF76B7C2),
    Color(0xFFF3C04A),
    Color(0xFFE88A73),
    Color(0xFF9DCAB0),
    Color(0xFFB4567A),
    Color(0xFF6D63D6),
  ];

  /// The wordmark's typeface. The web uses Georgia, which Android lacks;
  /// Gelasio (SIL OFL, bundled in `assets/fonts/`) is drawn to Georgia's
  /// metrics.
  static const brandFontFamily = 'Gelasio';
}
