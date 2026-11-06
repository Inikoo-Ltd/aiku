/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-31m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:aiku_mobile/home/area_memory.dart';

/// Stands in for secure storage, which tests cannot reach.
class InMemoryAreaMemory implements AreaMemory {
  InMemoryAreaMemory([Map<String, String>? areas]) : areas = {...?areas};

  /// The remembered area key, per username.
  final Map<String, String> areas;

  @override
  Future<String?> read(String username) async => areas[username];

  @override
  Future<void> write(String username, String areaKey) async =>
      areas[username] = areaKey;
}
