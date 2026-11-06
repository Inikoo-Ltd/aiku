/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:math';
import 'dart:ui';

import 'package:flutter/material.dart';

import 'aiku_palette.dart';
import 'brand_header.dart';
import 'watercolour_background.dart';

/// The sign-in page frame: paper and watercolour, the brand at the top left,
/// and [child] in a frosted card centred a little above the middle.
class AuthPage extends StatelessWidget {
  const AuthPage({super.key, required this.child});

  /// The card's content.
  final Widget child;

  /// Below this height the page scrolls, so the open keyboard never pushes
  /// the card over the brand.
  static const _minimumHeight = 640.0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AikuPalette.paper,
      body: Stack(
        fit: StackFit.expand,
        children: [
          const WatercolourBackground(),
          SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) => SingleChildScrollView(
                child: SizedBox(
                  height: max(constraints.maxHeight, _minimumHeight),
                  child: Stack(
                    children: [
                      const Positioned(top: 32, left: 40, child: BrandHeader()),
                      Center(
                        child: Transform.translate(
                          offset: const Offset(0, -16),
                          child: AuthCard(child: child),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// White at 80% with a backdrop blur, a hairline border and a soft shadow.
class AuthCard extends StatelessWidget {
  const AuthCard({super.key, required this.child});

  /// The card's content.
  final Widget child;

  static const _radius = BorderRadius.all(Radius.circular(8));

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 448),
      child: DecoratedBox(
        decoration: const BoxDecoration(
          borderRadius: _radius,
          boxShadow: [
            BoxShadow(
              color: Color(0x141C1B22),
              offset: Offset(0, 8),
              blurRadius: 30,
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: _radius,
          child: BackdropFilter(
            filter: ImageFilter.blur(sigmaX: 8, sigmaY: 8),
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.8),
                borderRadius: _radius,
                border: Border.all(
                  color: AikuPalette.ink.withValues(alpha: 0.1),
                ),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  vertical: 32,
                  horizontal: 16,
                ),
                child: child,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
