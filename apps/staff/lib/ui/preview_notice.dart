/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-13m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import 'app_colours.dart';

/// A quiet line saying part of a screen isn't on the app yet.
class PreviewNotice extends StatelessWidget {
  const PreviewNotice({super.key, required this.text});

  /// What to say.
  final String text;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 1),
          child: Icon(Icons.info_outline, size: 16, color: colours.accentText),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: TextStyle(fontSize: 13, color: colours.secondary),
          ),
        ),
      ],
    );
  }
}

/// Where content that isn't connected to aiku yet will go: an icon and a
/// line of text on the page, no box.
class NotConnectedCard extends StatelessWidget {
  const NotConnectedCard({super.key, required this.icon, required this.text});

  /// Shown above the text.
  final IconData icon;

  /// What will appear here.
  final String text;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 28),
      child: Column(
        children: [
          Icon(icon, size: 28, color: colours.muted),
          const SizedBox(height: 8),
          Text(
            text,
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 14, color: colours.secondary),
          ),
        ],
      ),
    );
  }
}
