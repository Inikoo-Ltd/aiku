/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../auth/auth_controller.dart';
import '../ui/accent_shades.dart';
import '../ui/aiku_palette.dart';
import '../ui/aiku_theme.dart';
import '../ui/form_error_list.dart';
import '../ui/loading_indicator.dart';

/// Picks the mobile theme from aiku's presets.
///
/// The choice is saved on this phone straight away and then on aiku as the
/// user's mobile theme; the web keeps its own theme.
class ThemeScreen extends StatelessWidget {
  const ThemeScreen({super.key, required this.controller});

  /// The key of the tile for the preset called [name], for tests.
  static ValueKey<String> presetKey(String name) => ValueKey('theme.$name');

  /// Saves the choice.
  final AuthController controller;

  static const _bodyStyle = TextStyle(fontSize: 14, color: AikuPalette.label);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Theme')),
      body: ListenableBuilder(
        listenable: controller,
        builder: (context, _) {
          final state = controller.state;
          if (state is! SignedIn) {
            return const SizedBox.shrink();
          }
          final profile = state.session.profile;
          final current = effectiveTheme(profile);
          final beingSaved = controller.themeBeingSaved;

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
            children: [
              Text(
                profile.mobileAppTheme == null
                    ? 'The app follows your aiku web theme until you pick one '
                          'here. Your pick is saved on this phone and in aiku; '
                          'the web keeps its own theme.'
                    : 'Saved on this phone and in aiku. The web keeps its own '
                          'theme.',
                style: _bodyStyle,
              ),
              FormErrorList(messages: controller.themeErrors.messages),
              const SizedBox(height: 16),
              for (final preset in aikuThemePresets) ...[
                _PresetTile(
                  key: presetKey(preset.name),
                  preset: preset,
                  isSelected: preset.matches(current),
                  isSaving: beingSaved != null && preset.matches(beingSaved),
                  onTap: () => controller.chooseMobileTheme(preset.colours),
                ),
                const SizedBox(height: 8),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _PresetTile extends StatelessWidget {
  const _PresetTile({
    super.key,
    required this.preset,
    required this.isSelected,
    required this.isSaving,
    required this.onTap,
  });

  final AikuThemePreset preset;
  final bool isSelected;
  final bool isSaving;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final shades = AccentShades.fromAppTheme(preset.colours);

    return Card(
      shape: RoundedRectangleBorder(
        borderRadius: const BorderRadius.all(Radius.circular(8)),
        side: BorderSide(
          color: isSelected ? shades.accent : AikuPalette.inputBorder,
          width: isSelected ? 2 : 1,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              CircleAvatar(radius: 16, backgroundColor: shades.accent),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      preset.name,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                        color: AikuPalette.ink,
                      ),
                    ),
                    const SizedBox(height: 6),
                    _Swatches(colours: preset.colours),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              SizedBox.square(
                dimension: 24,
                child: isSaving
                    ? Center(child: LoadingIndicator(color: shades.accent))
                    : isSelected
                    ? Icon(Icons.check_circle, color: shades.accent)
                    : null,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Swatches extends StatelessWidget {
  const _Swatches({required this.colours});

  final List<String> colours;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (final colour in colours)
          Padding(
            padding: const EdgeInsets.only(right: 4),
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: AccentShades.fromAppTheme([
                  for (var slot = 0; slot < 5; slot++) colour,
                ]).accent,
                borderRadius: const BorderRadius.all(Radius.circular(3)),
                border: Border.all(color: const Color(0x1F000000)),
              ),
              child: const SizedBox.square(dimension: 14),
            ),
          ),
      ],
    );
  }
}
