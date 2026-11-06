/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-13m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import 'accent_shades.dart';
import 'aiku_palette.dart';

/// A soft accent strip saying a screen is layout only for now.
class PreviewNotice extends StatelessWidget {
  const PreviewNotice({super.key, required this.shades, required this.text});

  /// The current theme's accent shades.
  final AccentShades shades;

  /// What to say.
  final String text;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: shades.soft,
        borderRadius: const BorderRadius.all(Radius.circular(8)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Icon(Icons.info_outline, size: 18, color: shades.deep),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                text,
                style: TextStyle(fontSize: 13, color: shades.deep),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A card for content that isn't connected to aiku yet.
class NotConnectedCard extends StatelessWidget {
  const NotConnectedCard({super.key, required this.icon, required this.text});

  /// Shown above the text.
  final IconData icon;

  /// What will appear here.
  final String text;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            Icon(icon, size: 28, color: AikuPalette.placeholder),
            const SizedBox(height: 8),
            Text(
              text,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 14, color: AikuPalette.label),
            ),
          ],
        ),
      ),
    );
  }
}
