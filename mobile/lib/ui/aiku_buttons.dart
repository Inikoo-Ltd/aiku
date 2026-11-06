/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import 'aiku_palette.dart';
import 'app_colours.dart';
import 'loading_indicator.dart';

const _buttonHeight = 36.0;
const _buttonShape = RoundedRectangleBorder(
  borderRadius: BorderRadius.all(Radius.circular(6)),
);

/// "Sign in" and "Enter": full width, filled with the default accent and,
/// by the project's one exception to the icon rule, without an icon.
///
/// While [isLoading] it is disabled and shows the loading indicator.
class SignInButton extends StatelessWidget {
  const SignInButton({
    super.key,
    required this.label,
    required this.onPressed,
    required this.isLoading,
  });

  /// The button text.
  final String label;

  /// Called on tap, unless loading.
  final VoidCallback onPressed;

  /// Whether the request it started is running.
  final bool isLoading;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      onPressed: isLoading ? null : onPressed,
      style: ButtonStyle(
        minimumSize: const WidgetStatePropertyAll(
          Size.fromHeight(_buttonHeight),
        ),
        padding: const WidgetStatePropertyAll(
          EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        ),
        shape: const WidgetStatePropertyAll(_buttonShape),
        side: const WidgetStatePropertyAll(
          BorderSide(color: AikuPalette.focus),
        ),
        foregroundColor: const WidgetStatePropertyAll(AikuPalette.signInText),
        overlayColor: const WidgetStatePropertyAll(Colors.transparent),
        backgroundColor: WidgetStateProperty.resolveWith(
          (states) => switch (states) {
            _ when states.contains(WidgetState.disabled) =>
              AikuPalette.signInDisabled,
            _ when states.contains(WidgetState.pressed) =>
              AikuPalette.signInPressed,
            _ => AikuPalette.defaultAccent,
          },
        ),
        textStyle: const WidgetStatePropertyAll(
          TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
        ),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (isLoading) ...[
            const LoadingIndicator(color: AikuPalette.signInText),
            const SizedBox(width: 8),
          ],
          Text(label),
        ],
      ),
    );
  }
}

/// A white button with a border, whose icon carries the colour.
class BorderedIconButton extends StatelessWidget {
  const BorderedIconButton({
    super.key,
    required this.label,
    required this.icon,
    required this.iconColor,
    required this.onPressed,
    this.isLoading = false,
  });

  /// The button text.
  final String label;

  /// The icon before the text.
  final IconData icon;

  /// The icon's colour.
  final Color iconColor;

  /// Called on tap, unless loading.
  final VoidCallback onPressed;

  /// Whether the request it started is running.
  final bool isLoading;

  @override
  Widget build(BuildContext context) {
    // The signed-in app may be dark; the sign-in screens keep the web look.
    final colours = Theme.of(context).extension<AppColours>();
    return OutlinedButton.icon(
      onPressed: isLoading ? null : onPressed,
      style: OutlinedButton.styleFrom(
        backgroundColor: colours?.raised ?? Colors.white,
        foregroundColor: colours?.ink ?? AikuPalette.ink,
        disabledForegroundColor: colours?.secondary ?? AikuPalette.label,
        side: BorderSide(
          color: colours == null
              ? AikuPalette.inputBorder
              : Color.lerp(colours.hairline, colours.muted, 0.4)!,
        ),
        shape: _buttonShape,
        minimumSize: const Size(0, _buttonHeight),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
      ),
      icon: isLoading
          ? LoadingIndicator(color: iconColor)
          : Icon(icon, color: iconColor, size: 18),
      label: Text(label),
    );
  }
}

/// A square button with a coloured outline and icon, and no text.
class SquareIconButton extends StatelessWidget {
  const SquareIconButton({
    super.key,
    required this.icon,
    required this.color,
    required this.tooltip,
    required this.onPressed,
  });

  /// The icon.
  final IconData icon;

  /// The outline and icon colour.
  final Color color;

  /// Read out and shown on long press.
  final String tooltip;

  /// Called on tap.
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox.square(
      dimension: _buttonHeight,
      child: Tooltip(
        message: tooltip,
        child: OutlinedButton(
          onPressed: onPressed,
          style: OutlinedButton.styleFrom(
            backgroundColor: Colors.white,
            padding: EdgeInsets.zero,
            side: BorderSide(color: color),
            shape: _buttonShape,
          ),
          child: Icon(icon, color: color, size: 18),
        ),
      ),
    );
  }
}
