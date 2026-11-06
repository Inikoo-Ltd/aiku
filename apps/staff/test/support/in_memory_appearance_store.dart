/*
 * Author Louis Perez
 * Created on 06-11-2026-11h-06m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:aiku_mobile/home/appearance.dart';

/// Stands in for secure storage, which tests cannot reach.
class InMemoryAppearanceStore implements AppearanceStore {
  InMemoryAppearanceStore([this.saved]);

  /// What is saved right now.
  Appearance? saved;

  @override
  Future<Appearance?> read() async => saved;

  @override
  Future<void> write(Appearance appearance) async => saved = appearance;
}
