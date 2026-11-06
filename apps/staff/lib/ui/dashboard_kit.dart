/*
 * Author Louis Perez
 * Created on 06-11-2026-10h-17m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import '../api/dashboard.dart';
import 'app_colours.dart';

/// The dashboards' building blocks: the theme-coloured band at the top, then
/// text, hairlines and whitespace rather than boxes and pills.

const placeholderFigure = '—';

/// Figures line up in columns.
const tabularFigures = [FontFeature.tabularFigures()];

/// [count] with thousands separators, as aiku's web shows counts: 90,716.
String formatCount(int count) {
  final digits = count.abs().toString();
  final grouped = StringBuffer();
  for (var index = 0; index < digits.length; index++) {
    if (index > 0 && (digits.length - index) % 3 == 0) {
      grouped.write(',');
    }
    grouped.write(digits[index]);
  }
  return count < 0 ? '-$grouped' : grouped.toString();
}

/// "Updated 14:28", in the phone's local time.
String updatedAt(DateTime time) {
  final local = time.toLocal();
  String twoDigits(int value) => value.toString().padLeft(2, '0');
  return 'Updated ${twoDigits(local.hour)}:${twoDigits(local.minute)}';
}

/// The theme-coloured band that continues the app bar: tabs and the figure
/// a Home leads with sit on it.
class ThemeBand extends StatelessWidget {
  const ThemeBand({super.key, required this.children});

  /// What sits on the band, top to bottom.
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AppColours.of(context).band,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: children,
        ),
      ),
    );
  }
}

/// One choice in [TextTabs].
class const TextTab<T>({
  required final T value,
  required final String label,
  final Key? key,
});

/// A row of text choices; the chosen one is underlined. On the band
/// ([onBand]) it takes the band's colours, on the page the accent.
class TextTabs<T> extends StatelessWidget {
  const TextTabs({
    super.key,
    required this.tabs,
    required this.selected,
    required this.onSelected,
    this.onBand = false,
    this.showBaseline = true,
    this.fontSize = 14,
  });

  /// The choices, in order.
  final List<TextTab<T>> tabs;

  /// The chosen value.
  final T selected;

  /// Called with the tapped choice.
  final ValueChanged<T> onSelected;

  /// Whether the row sits on the theme band.
  final bool onBand;

  /// Whether a hairline runs under the whole row (not on the band).
  final bool showBaseline;

  /// The labels' size.
  final double fontSize;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    return DecoratedBox(
      decoration: BoxDecoration(
        border: showBaseline && !onBand
            ? Border(bottom: BorderSide(color: colours.hairline))
            : null,
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (final tab in tabs)
              _TextTabButton(
                key: tab.key,
                label: tab.label,
                isSelected: tab.value == selected,
                fontSize: fontSize,
                selectedColour: onBand ? colours.onBand : colours.ink,
                otherColour: onBand ? colours.onBandMuted : colours.muted,
                underline: onBand ? colours.bandHighlight : colours.accentText,
                onTap: () => onSelected(tab.value),
              ),
          ],
        ),
      ),
    );
  }
}

class _TextTabButton extends StatelessWidget {
  const _TextTabButton({
    super.key,
    required this.label,
    required this.isSelected,
    required this.fontSize,
    required this.selectedColour,
    required this.otherColour,
    required this.underline,
    required this.onTap,
  });

  final String label;
  final bool isSelected;
  final double fontSize;
  final Color selectedColour;
  final Color otherColour;
  final Color underline;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    // The moving underline is the feedback; a grey tap box would fight the
    // flat look.
    return InkWell(
      onTap: onTap,
      splashFactory: NoSplash.splashFactory,
      highlightColor: Colors.transparent,
      hoverColor: Colors.transparent,
      focusColor: Colors.transparent,
      child: Container(
        padding: const EdgeInsets.fromLTRB(2, 10, 2, 8),
        margin: const EdgeInsets.only(right: 20),
        decoration: BoxDecoration(
          border: Border(
            bottom: BorderSide(
              color: isSelected ? underline : Colors.transparent,
              width: 2,
            ),
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: fontSize,
            fontWeight: isSelected ? FontWeight.w600 : FontWeight.w500,
            color: isSelected ? selectedColour : otherColour,
          ),
        ),
      ),
    );
  }
}

/// A small title over a section, in the accent, with something on the right.
class SectionHeader extends StatelessWidget {
  const SectionHeader(this.title, {super.key, this.trailing});

  /// The section's name.
  final String title;

  /// Shown on the right, e.g. the updated time.
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: AppColours.of(context).accentText,
            ),
          ),
        ),
        ?trailing,
      ],
    );
  }
}

/// A big figure under a small label: the number a Home leads with, on the
/// theme band.
class HeroFigure extends StatelessWidget {
  const HeroFigure({
    super.key,
    required this.label,
    required this.value,
    this.trailing,
  });

  /// What the figure is.
  final String label;

  /// The figure, formatted, or null while loading.
  final String? value;

  /// Shown on the label's right, e.g. the updated time.
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  color: colours.onBandMuted,
                ),
              ),
            ),
            ?trailing,
          ],
        ),
        const SizedBox(height: 2),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(
            value ?? placeholderFigure,
            style: TextStyle(
              fontSize: 32,
              fontWeight: FontWeight.w600,
              color: colours.onBand,
              letterSpacing: -0.5,
              fontFeatures: tabularFigures,
            ),
          ),
        ),
      ],
    );
  }
}

/// A quiet caption, e.g. "Updated 14:28"; on the band it takes the band's
/// colours.
class Caption extends StatelessWidget {
  const Caption(this.text, {super.key, this.onBand = false});

  /// What to say.
  final String text;

  /// Whether it sits on the theme band.
  final bool onBand;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    return Text(
      text,
      style: TextStyle(
        fontSize: 12,
        color: onBand ? colours.onBandMuted : colours.muted,
      ),
    );
  }
}

/// Fixed colours for aiku's delivery-note stages. Stat colours never follow
/// the theme; amber marks Blocked and green Dispatched, as everywhere.
const stageColours = <String, Color>{
  'unassigned': Color(0xFF64748B),
  'queued': Color(0xFF94A3B8),
  'picking': Color(0xFF3B82F6),
  'blocked': Color(0xFFF59E0B),
  'packing': Color(0xFF8B5CF6),
  'packed': Color(0xFF06B6D4),
  'finalised': Color(0xFF14B8A6),
  'dispatched': Color(0xFF22C55E),
};

/// The stages aiku counts delivery notes in: a bar of how the open ones
/// split, then one row per stage. With no [stages] yet, placeholders.
class StageList extends StatelessWidget {
  const StageList({super.key, required this.stages});

  /// aiku's stages in its order, or null while they load.
  final List<OperationsStage>? stages;

  static const _placeholderRows = 8;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    final stages = this.stages;
    final open = [
      for (final stage in stages ?? const <OperationsStage>[])
        if (stage.key != 'dispatched' && stage.count > 0) stage,
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        ClipRRect(
          borderRadius: const BorderRadius.all(Radius.circular(4)),
          child: SizedBox(
            height: 8,
            child: open.isEmpty
                ? ColoredBox(color: colours.hairline)
                : Row(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      for (final (index, stage) in open.indexed) ...[
                        if (index > 0) const SizedBox(width: 2),
                        Expanded(
                          flex: stage.count,
                          child: ColoredBox(
                            color: stageColours[stage.key] ?? colours.hairline,
                          ),
                        ),
                      ],
                    ],
                  ),
          ),
        ),
        const SizedBox(height: 8),
        if (stages == null)
          for (var row = 0; row < _placeholderRows; row++)
            _StageRow(
              label: placeholderFigure,
              count: placeholderFigure,
              dot: colours.hairline,
            )
        else
          for (final stage in stages)
            _StageRow(
              key: ValueKey('stage.${stage.key}'),
              label: stage.label,
              count: formatCount(stage.count),
              dot: stageColours[stage.key] ?? colours.hairline,
              isBlocked: stage.key == 'blocked' && stage.count > 0,
              isDispatched: stage.key == 'dispatched',
            ),
      ],
    );
  }
}

class _StageRow extends StatelessWidget {
  const _StageRow({
    super.key,
    required this.label,
    required this.count,
    required this.dot,
    this.isBlocked = false,
    this.isDispatched = false,
  });

  final String label;
  final String count;
  final Color dot;
  final bool isBlocked;
  final bool isDispatched;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    final textColour = isBlocked
        ? colours.blockedText
        : isDispatched
        ? colours.doneText
        : colours.ink;
    final style = TextStyle(fontSize: 15, color: textColour);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 11),
      decoration: BoxDecoration(
        color: isBlocked ? colours.blockedBackground : null,
        border: isBlocked
            ? null
            : Border(bottom: BorderSide(color: colours.hairline)),
      ),
      child: Row(
        children: [
          if (isBlocked)
            Icon(
              Icons.warning_amber_rounded,
              size: 16,
              color: colours.blockedText,
            )
          else
            DecoratedBox(
              decoration: BoxDecoration(color: dot, shape: BoxShape.circle),
              child: const SizedBox.square(dimension: 8),
            ),
          SizedBox(width: isBlocked ? 8 : 12),
          Expanded(child: Text(label, style: style)),
          Text(
            count,
            style: style.copyWith(
              fontWeight: FontWeight.w600,
              fontFeatures: tabularFigures,
            ),
          ),
        ],
      ),
    );
  }
}
