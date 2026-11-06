/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/widgets.dart';

import 'aiku_palette.dart';

/// The invader logo and the "aiku" wordmark.
class BrandHeader extends StatelessWidget {
  const BrandHeader({super.key});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Image.asset(
          'assets/images/invader.png',
          height: 56,
          semanticLabel: 'aiku logo',
        ),
        const SizedBox(width: 12),
        const Text(
          'aiku',
          style: TextStyle(
            fontFamily: AikuPalette.brandFontFamily,
            fontSize: 36,
            fontWeight: FontWeight.w600,
            fontVariations: [FontVariation.weight(600)],
            color: AikuPalette.ink,
            letterSpacing: -0.9,
            height: 1,
          ),
        ),
      ],
    );
  }
}
