/*
 * Author Louis Perez
 * Created on 06-11-2026-10h-17m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:async';

import 'package:aiku_mobile/api/api_failure.dart';
import 'package:aiku_mobile/home/dashboard_loader.dart';
import 'package:flutter_test/flutter_test.dart';

/// A loader whose fetch answers with whatever [answers] holds next.
({DashboardLoader<int> loader, List<int> expiries, List<int> fetches}) _loader(
  List<FutureOr<int> Function()> answers,
) {
  final expiries = <int>[];
  final fetches = <int>[];
  final loader = DashboardLoader<int>(
    fetch: () async {
      fetches.add(1);
      return answers.removeAt(0)();
    },
    onUnauthorised: () async => expiries.add(1),
    showDiagnostics: false,
  );
  addTearDown(loader.dispose);
  return (loader: loader, expiries: expiries, fetches: fetches);
}

void main() {
  test('starts loading and shows the figures once aiku answers', () async {
    final (:loader, expiries: _, fetches: _) = _loader([() => 7]);
    expect(loader.state, isA<DashboardLoading<int>>());

    await loader.load();

    expect(
      loader.state,
      isA<DashboardReady<int>>().having((state) => state.data, 'data', 7),
    );
  });

  test('a failed first load says why, and loading again recovers', () async {
    final (:loader, expiries: _, fetches: _) = _loader([
      () => throw const NetworkFailure(),
      () => 7,
    ]);

    await loader.load();
    expect(
      loader.state,
      isA<DashboardFailed<int>>().having(
        (state) => state.messages,
        'messages',
        isNotEmpty,
      ),
    );

    await loader.load();
    expect(loader.state, isA<DashboardReady<int>>());
  });

  test('a failed refresh keeps the figures shown and says why', () async {
    final (:loader, expiries: _, fetches: _) = _loader([
      () => 7,
      () => throw const NetworkFailure(),
    ]);
    await loader.load();

    await loader.load();

    expect(
      loader.state,
      isA<DashboardReady<int>>()
          .having((state) => state.data, 'data', 7)
          .having((state) => state.refreshMessages, 'messages', isNotEmpty),
    );
  });

  test('a refresh never shows the loading state again', () async {
    final (:loader, expiries: _, fetches: _) = _loader([() => 7, () => 8]);
    await loader.load();
    final seen = <Type>[];
    loader.addListener(() => seen.add(loader.state.runtimeType));

    await loader.load();

    expect(seen, [DashboardReady<int>]);
  });

  test('a 401 hands over to sign-out', () async {
    final (:loader, :expiries, fetches: _) = _loader([
      () => throw const Unauthorised(),
    ]);

    await loader.load();

    expect(expiries, hasLength(1));
  });

  test('a load while one is running asks aiku only once', () async {
    final answer = Completer<int>();
    final (:loader, expiries: _, :fetches) = _loader([() => answer.future]);

    final first = loader.load();
    final second = loader.load();
    answer.complete(7);
    await Future.wait([first, second]);

    expect(fetches, hasLength(1));
  });

  test('an answer arriving after the Home closed is dropped quietly', () async {
    final answer = Completer<int>();
    final loader = DashboardLoader<int>(
      fetch: () => answer.future,
      onUnauthorised: () async {},
    );

    final loading = loader.load();
    loader.dispose();
    answer.complete(7);

    await expectLater(loading, completes);
  });
}
