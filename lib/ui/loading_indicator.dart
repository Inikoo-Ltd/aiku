/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

/// The app's one loading indicator.
class LoadingIndicator extends StatelessWidget {
  const LoadingIndicator({super.key, required this.color, this.size = 16});

  /// The spinner's colour.
  final Color color;

  /// Width and height.
  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox.square(
      dimension: size,
      child: CircularProgressIndicator(strokeWidth: 2, color: color),
    );
  }
}
