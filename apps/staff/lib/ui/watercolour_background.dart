/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:math';
import 'dart:ui';

import 'package:flutter/widgets.dart';

import 'aiku_palette.dart';

/// Warm paper with five soft watercolour pools, like aiku's web login.
///
/// Positions, sizes and colours are chosen once per screen, so every visit
/// looks a little different. The web draws this with SVG filters; here the
/// pools are blurred shapes over a faint paper grain.
class WatercolourBackground extends StatefulWidget {
  const WatercolourBackground({super.key, this.random});

  /// Supplies the randomness; tests pass a seeded one.
  final Random? random;

  @override
  State<WatercolourBackground> createState() => _WatercolourBackgroundState();
}

class _WatercolourBackgroundState extends State<WatercolourBackground> {
  static const _poolCount = 5;
  static const _grainCount = 1400;

  late final List<_Pool> _pools;
  late final List<Offset> _grain;

  @override
  void initState() {
    super.initState();
    final random = widget.random ?? Random();
    final colours = [...AikuPalette.watercolours]..shuffle(random);
    _pools = [
      for (var index = 0; index < _poolCount; index++)
        _Pool.random(random, colours[index]),
    ];
    _grain = [
      for (var index = 0; index < _grainCount; index++)
        Offset(random.nextDouble(), random.nextDouble()),
    ];
  }

  @override
  Widget build(BuildContext context) {
    return RepaintBoundary(
      child: ColoredBox(
        color: AikuPalette.paper,
        child: Stack(
          fit: StackFit.expand,
          children: [
            CustomPaint(painter: _GrainPainter(_grain)),
            ImageFiltered(
              imageFilter: ImageFilter.blur(sigmaX: 36, sigmaY: 36),
              child: CustomPaint(painter: _PoolsPainter(_pools)),
            ),
          ],
        ),
      ),
    );
  }
}

/// One pool: a wobbly blob described in fractions of the screen.
class const _Pool(
  final Offset centre,
  final double radius,
  final List<double> wobble,
  final Color colour,
) {
  factory _Pool.random(Random random, Color baseColour) => _Pool(
    Offset(random.nextDouble() * 1.1 - 0.05, random.nextDouble() * 1.1 - 0.05),
    0.28 + random.nextDouble() * 0.22,
    [for (var point = 0; point < 9; point++) 0.75 + random.nextDouble() * 0.5],
    baseColour.withValues(alpha: 0.26 + random.nextDouble() * 0.10),
  );

  Path pathIn(Size size) {
    final shortestSide = size.shortestSide;
    final centrePoint = Offset(centre.dx * size.width, centre.dy * size.height);
    final points = [
      for (var index = 0; index < wobble.length; index++)
        centrePoint +
            Offset.fromDirection(
              2 * pi * index / wobble.length,
              radius * shortestSide * wobble[index],
            ),
    ];
    final path = Path();
    for (var index = 0; index < points.length; index++) {
      final current = points[index];
      final next = points[(index + 1) % points.length];
      final middle = Offset.lerp(current, next, 0.5)!;
      if (index == 0) {
        path.moveTo(middle.dx, middle.dy);
      } else {
        path.quadraticBezierTo(current.dx, current.dy, middle.dx, middle.dy);
      }
    }
    final first = points.first;
    final firstMiddle = Offset.lerp(first, points[1], 0.5)!;
    return path
      ..quadraticBezierTo(first.dx, first.dy, firstMiddle.dx, firstMiddle.dy)
      ..close();
  }
}

class _PoolsPainter extends CustomPainter {
  const _PoolsPainter(this.pools);

  final List<_Pool> pools;

  @override
  void paint(Canvas canvas, Size size) {
    for (final pool in pools) {
      canvas.drawPath(
        pool.pathIn(size),
        Paint()
          ..color = pool.colour
          ..blendMode = BlendMode.multiply,
      );
    }
  }

  @override
  bool shouldRepaint(_PoolsPainter oldDelegate) => oldDelegate.pools != pools;
}

class _GrainPainter extends CustomPainter {
  const _GrainPainter(this.grain);

  final List<Offset> grain;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = AikuPalette.ink.withValues(alpha: 0.035)
      ..strokeWidth = 1.2
      ..strokeCap = StrokeCap.round;
    canvas.drawPoints(PointMode.points, [
      for (final point in grain)
        Offset(point.dx * size.width, point.dy * size.height),
    ], paint);
  }

  @override
  bool shouldRepaint(_GrainPainter oldDelegate) => oldDelegate.grain != grain;
}
