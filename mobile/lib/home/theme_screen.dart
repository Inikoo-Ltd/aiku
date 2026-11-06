/*
 * Author Louis Perez
 * Created on 05-11-2026-16h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../auth/auth_controller.dart';
import '../ui/accent_shades.dart';
import '../ui/app_colours.dart';
import '../ui/aiku_theme.dart';
import '../ui/dashboard_kit.dart';
import '../ui/form_error_list.dart';
import '../ui/loading_indicator.dart';
import 'appearance.dart';

/// Picks light, dark or the phone's setting, and the mobile theme from
/// aiku's presets.
///
/// The appearance is kept on this phone only. The theme is saved on this
/// phone straight away and then on aiku as the user's mobile theme; the web
/// keeps its own theme.
class ThemeScreen extends StatelessWidget {
  const ThemeScreen({
    super.key,
    required this.controller,
    required this.appearance,
  });

  /// The key of the tile for the preset called [name], for tests.
  static ValueKey<String> presetKey(String name) => ValueKey('theme.$name');

  /// The key of the appearance choice [appearance], for tests.
  static ValueKey<String> appearanceKey(Appearance appearance) =>
      ValueKey('theme.appearance.${appearance.name}');

  /// Saves the theme.
  final AuthController controller;

  /// Light, dark or the phone's setting.
  final AppearanceController appearance;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Theme')),
      body: ListenableBuilder(
        listenable: Listenable.merge([controller, appearance]),
        builder: (context, _) {
          final state = controller.state;
          if (state is! SignedIn) {
            return const SizedBox.shrink();
          }
          final colours = AppColours.of(context);
          final bodyStyle = TextStyle(fontSize: 14, color: colours.secondary);
          final profile = state.session.profile;
          final current = effectiveTheme(profile);
          final beingSaved = controller.themeBeingSaved;

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              const SectionHeader('Appearance'),
              const SizedBox(height: 4),
              RadioGroup<Appearance>(
                groupValue: appearance.appearance,
                onChanged: (choice) {
                  if (choice != null) {
                    appearance.choose(choice);
                  }
                },
                child: Column(
                  children: [
                    for (final option in Appearance.values)
                      _AppearanceOption(
                        key: appearanceKey(option),
                        option: option,
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              Text('Kept on this phone.', style: bodyStyle),
              const SizedBox(height: 28),
              const SectionHeader('Colour'),
              const SizedBox(height: 6),
              Text(
                profile.mobileAppTheme == null
                    ? 'The app follows your aiku web theme until you pick one '
                          'here. Your pick is saved on this phone and in aiku; '
                          'the web keeps its own theme.'
                    : 'Saved on this phone and in aiku. The web keeps its own '
                          'theme.',
                style: bodyStyle,
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

/// One appearance as a row with a radio button, like the preset rows.
class _AppearanceOption extends StatelessWidget {
  const _AppearanceOption({super.key, required this.option});

  final Appearance option;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    return DecoratedBox(
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: colours.hairline)),
      ),
      child: RadioListTile<Appearance>(
        value: option,
        contentPadding: EdgeInsets.zero,
        controlAffinity: ListTileControlAffinity.trailing,
        fillColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? colours.accentText
              : colours.muted,
        ),
        secondary: Icon(switch (option) {
          Appearance.system => Icons.brightness_auto_outlined,
          Appearance.light => Icons.light_mode_outlined,
          Appearance.dark => Icons.dark_mode_outlined,
        }, color: colours.accentText),
        title: Text(
          option.label,
          style: TextStyle(fontSize: 14, color: colours.ink),
        ),
        subtitle: option == Appearance.system
            ? Text(
                'Follows the phone’s dark mode',
                style: TextStyle(fontSize: 12, color: colours.secondary),
              )
            : null,
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
    final colours = AppColours.of(context);

    return DecoratedBox(
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: colours.hairline)),
      ),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12),
          child: Row(
            children: [
              // The preset's own accent, with an edge so a near-black or
              // near-white accent still shows on either page.
              DecoratedBox(
                decoration: BoxDecoration(
                  color: shades.accent,
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: Color.lerp(colours.hairline, colours.muted, 0.5)!,
                  ),
                ),
                child: const SizedBox.square(dimension: 32),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      preset.name,
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: isSelected
                            ? FontWeight.w600
                            : FontWeight.w500,
                        color: colours.ink,
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
                // The chosen preset is the theme in use, so its readable
                // accent marks it, even for pale presets.
                child: isSaving
                    ? Center(child: LoadingIndicator(color: colours.accentText))
                    : isSelected
                    ? Icon(Icons.check_circle, color: colours.accentText)
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
    final edge = AppColours.of(context);
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
                // Black and white swatches need an edge on either page.
                border: Border.all(
                  color: Color.lerp(edge.hairline, edge.muted, 0.5)!,
                ),
              ),
              child: const SizedBox.square(dimension: 14),
            ),
          ),
      ],
    );
  }
}
