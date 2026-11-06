/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-13m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../ui/aiku_palette.dart';

/// Opened from a menu entry the app has no screen for yet.
class NotOnMobileYetScreen extends StatelessWidget {
  const NotOnMobileYetScreen({super.key, required this.title});

  /// Key for tests.
  static const messageKey = ValueKey('notOnMobileYet.message');

  /// The menu entry's name.
  final String title;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: const _CentredMessage(
        key: messageKey,
        icon: Icons.construction_outlined,
        title: "This isn't on the app yet",
        text: 'Use aiku on the web for this for now.',
      ),
    );
  }
}

/// What a bottom-bar hub shows until it is built.
class HubPlaceholder extends StatelessWidget {
  const HubPlaceholder({super.key, required this.name, required this.icon});

  /// The hub's name, e.g. "Tickets".
  final String name;

  /// The hub's icon.
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return _CentredMessage(
      icon: icon,
      title: '$name is coming to the app',
      text: 'One place for all your $name, across every area.',
    );
  }
}

class _CentredMessage extends StatelessWidget {
  const _CentredMessage({
    super.key,
    required this.icon,
    required this.title,
    required this.text,
  });

  final IconData icon;
  final String title;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 40, color: AikuPalette.placeholder),
            const SizedBox(height: 12),
            Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: AikuPalette.ink,
              ),
            ),
            const SizedBox(height: 6),
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
