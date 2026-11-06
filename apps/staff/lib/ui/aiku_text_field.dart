/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:math';

import 'package:flutter/material.dart';

import 'aiku_palette.dart';

/// A labelled input styled like aiku's web login.
///
/// With an [errorText] it shows a red exclamation inside the field, the
/// message under it, and gives a short shake.
class AikuTextField extends StatefulWidget {
  const AikuTextField({
    super.key,
    required this.label,
    required this.hint,
    required this.controller,
    this.fieldKey,
    this.toggleKey,
    this.errorText,
    this.autofocus = false,
    this.canBeHidden = false,
    this.keyboardType,
    this.autofillHints,
    this.textInputAction,
    this.onSubmitted,
  });

  /// Shown above the input.
  final String label;

  /// Shown inside the empty input.
  final String hint;

  /// Holds the text.
  final TextEditingController controller;

  /// Key for the [TextField] itself, for tests.
  final Key? fieldKey;

  /// Key for the show/hide toggle, for tests.
  final Key? toggleKey;

  /// The error to show under the field.
  final String? errorText;

  /// Whether the field takes focus when it appears.
  final bool autofocus;

  /// Whether the text starts hidden, with a toggle to show it.
  final bool canBeHidden;

  /// The keyboard to open.
  final TextInputType? keyboardType;

  /// What the platform may autofill.
  final Iterable<String>? autofillHints;

  /// The keyboard's action button.
  final TextInputAction? textInputAction;

  /// Called when the user presses Enter.
  final ValueChanged<String>? onSubmitted;

  @override
  State<AikuTextField> createState() => _AikuTextFieldState();
}

class _AikuTextFieldState extends State<AikuTextField>
    with SingleTickerProviderStateMixin {
  static const _radius = Radius.circular(6);
  static const _textStyle = TextStyle(fontSize: 14, color: AikuPalette.ink);

  late final AnimationController _shake = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 360),
  );
  bool _hidden = true;

  @override
  void didUpdateWidget(AikuTextField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.errorText != null && widget.errorText != oldWidget.errorText) {
      _shake.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _shake.dispose();
    super.dispose();
  }

  void _toggleHidden() {
    setState(() => _hidden = !_hidden);
  }

  @override
  Widget build(BuildContext context) {
    final errorText = widget.errorText;
    final borderRadius = widget.canBeHidden
        ? const BorderRadius.horizontal(left: _radius)
        : const BorderRadius.all(_radius);

    final field = TextField(
      key: widget.fieldKey,
      controller: widget.controller,
      autofocus: widget.autofocus,
      obscureText: widget.canBeHidden && _hidden,
      keyboardType: widget.keyboardType,
      autofillHints: widget.autofillHints,
      textInputAction: widget.textInputAction,
      onSubmitted: widget.onSubmitted,
      autocorrect: false,
      enableSuggestions: false,
      spellCheckConfiguration: const SpellCheckConfiguration.disabled(),
      style: _textStyle,
      cursorColor: AikuPalette.focus,
      decoration: InputDecoration(
        isDense: true,
        filled: true,
        fillColor: Colors.white,
        hintText: widget.hint,
        hintStyle: const TextStyle(
          fontSize: 14,
          color: AikuPalette.placeholder,
        ),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 12,
          vertical: 10,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: borderRadius,
          borderSide: const BorderSide(color: AikuPalette.inputBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: borderRadius,
          borderSide: const BorderSide(color: AikuPalette.focus, width: 2),
        ),
        suffixIcon: errorText == null
            ? null
            : const Icon(Icons.error, color: AikuPalette.errorIcon, size: 18),
        suffixIconConstraints: const BoxConstraints(minWidth: 36),
      ),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          widget.label,
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w500,
            color: AikuPalette.label,
          ),
        ),
        const SizedBox(height: 4),
        AnimatedBuilder(
          animation: _shake,
          builder: (context, child) => Transform.translate(
            offset: Offset(
              sin(_shake.value * pi * 6) * 6 * (1 - _shake.value),
              0,
            ),
            child: child,
          ),
          child: DecoratedBox(
            decoration: const BoxDecoration(
              borderRadius: BorderRadius.all(_radius),
              boxShadow: [
                BoxShadow(
                  color: Color(0x0D000000),
                  offset: Offset(0, 1),
                  blurRadius: 2,
                ),
              ],
            ),
            child: widget.canBeHidden
                ? IntrinsicHeight(
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Expanded(child: field),
                        _VisibilityToggle(
                          key: widget.toggleKey,
                          isHidden: _hidden,
                          onPressed: _toggleHidden,
                        ),
                      ],
                    ),
                  )
                : field,
          ),
        ),
        if (errorText != null)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              errorText,
              style: const TextStyle(
                fontSize: 14,
                color: AikuPalette.errorText,
              ),
            ),
          ),
      ],
    );
  }
}

/// The square show/hide button attached to the right of a hidden field.
class _VisibilityToggle extends StatelessWidget {
  const _VisibilityToggle({
    super.key,
    required this.isHidden,
    required this.onPressed,
  });

  final bool isHidden;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AikuPalette.toggleBackground,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.horizontal(right: Radius.circular(6)),
        side: BorderSide(color: AikuPalette.inputBorder),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onPressed,
        child: SizedBox(
          width: 40,
          child: Tooltip(
            message: isHidden ? 'Show password' : 'Hide password',
            child: Icon(
              isHidden ? Icons.visibility : Icons.visibility_off,
              color: AikuPalette.placeholder,
              size: 18,
            ),
          ),
        ),
      ),
    );
  }
}
