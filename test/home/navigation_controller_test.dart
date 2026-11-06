/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-31m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:convert';

import 'package:aiku_mobile/api/aiku_api_client.dart';
import 'package:aiku_mobile/api/navigation.dart';
import 'package:aiku_mobile/config/app_config.dart';
import 'package:aiku_mobile/home/navigation_controller.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

import '../support/in_memory_area_memory.dart';
import '../support/navigation_fixtures.dart';

http.Response _json(Object body, int status) => http.Response(
  jsonEncode(body),
  status,
  headers: {'content-type': 'application/json; charset=utf-8'},
);

/// A controller for "raul" whose aiku answers with [handler].
({
  NavigationController controller,
  InMemoryAreaMemory memory,
  List<int> expiries,
})
_controller(
  Future<http.Response> Function(http.Request request) handler, {
  Map<String, String>? remembered,
}) {
  final memory = InMemoryAreaMemory(remembered);
  final expiries = <int>[];
  final controller = NavigationController(
    client: AikuApiClient(
      config: const AppConfig(apiBaseUrl: 'http://aiku.example', appKey: 'k'),
      httpClient: MockClient(handler),
    ),
    token: '1|secret',
    username: 'raul',
    memory: memory,
    onUnauthorised: () async => expiries.add(1),
    showDiagnostics: false,
  );
  addTearDown(controller.dispose);
  return (controller: controller, memory: memory, expiries: expiries);
}

Area _currentArea(NavigationController controller) =>
    switch (controller.state) {
      NavigationReady(:final area) => area,
      final other => throw StateError('Expected ready, got $other'),
    };

void main() {
  test('asks aiku with the bearer token', () async {
    final requests = <http.Request>[];
    final (:controller, memory: _, expiries: _) = _controller((request) async {
      requests.add(request);
      return _json(groupUserNavigation(), 200);
    });

    await controller.load();

    expect(requests.single.method, 'GET');
    expect(requests.single.url.path, '/app/api/mobile/navigation');
    expect(requests.single.headers['Authorization'], 'Bearer 1|secret');
  });

  test('opens the remembered area when the user still has it', () async {
    final (:controller, memory: _, expiries: _) = _controller(
      (_) async => _json(groupUserNavigation(), 200),
      remembered: {'raul': 'warehouse:europa-drive'},
    );

    await controller.load();

    expect(_currentArea(controller).key, 'warehouse:europa-drive');
  });

  test('falls back to the default when the remembered area is gone', () async {
    final (:controller, memory: _, expiries: _) = _controller(
      (_) async => _json(groupUserNavigation(), 200),
      remembered: {'raul': 'shop:closed-shop'},
    );

    await controller.load();

    expect(_currentArea(controller).key, 'group:aw-group');
  });

  test('another user on the same phone does not get raul\'s area', () async {
    final (:controller, memory: _, expiries: _) = _controller(
      (_) async => _json(groupUserNavigation(), 200),
      remembered: {'someone-else': 'warehouse:europa-drive'},
    );

    await controller.load();

    expect(_currentArea(controller).key, 'group:aw-group');
  });

  test('choosing an area switches to it and remembers it', () async {
    final (:controller, :memory, expiries: _) = _controller(
      (_) async => _json(groupUserNavigation(), 200),
    );
    await controller.load();
    final warehouse = switch (controller.state) {
      NavigationReady(:final data) => data.areaWithKey(
        'warehouse:europa-drive',
      )!,
      _ => throw StateError('not ready'),
    };

    await controller.selectArea(warehouse);

    expect(_currentArea(controller).key, 'warehouse:europa-drive');
    expect(memory.areas, {'raul': 'warehouse:europa-drive'});
  });

  test('a 401 hands over to sign-out instead of showing an error', () async {
    final (:controller, memory: _, :expiries) = _controller(
      (_) async => http.Response('', 401),
    );

    await controller.load();

    expect(expiries, hasLength(1));
    expect(controller.state, isA<NavigationLoading>());
  });

  test('a server error is shown as a message', () async {
    final (:controller, memory: _, expiries: _) = _controller(
      (_) async => http.Response('', 500),
    );

    await controller.load();

    expect(
      controller.state,
      isA<NavigationFailed>().having(
        (state) => state.messages,
        'messages',
        isNotEmpty,
      ),
    );
  });

  test('a user aiku gives no area says there is nothing to open', () async {
    final (:controller, memory: _, expiries: _) = _controller(
      (_) async => _json({
        'tabs': {'tasks': false, 'tickets': false, 'chat': false},
        'group': null,
        'organisations': <Object?>[],
      }, 200),
    );

    await controller.load();

    expect(
      controller.state,
      isA<NavigationFailed>().having((state) => state.messages, 'messages', [
        NavigationController.nothingToOpenMessage,
      ]),
    );
  });

  test(
    'an answer arriving after the shell closed is dropped quietly',
    () async {
      final memory = InMemoryAreaMemory();
      final controller = NavigationController(
        client: AikuApiClient(
          config: const AppConfig(
            apiBaseUrl: 'http://aiku.example',
            appKey: 'k',
          ),
          httpClient: MockClient(
            (_) async => _json(groupUserNavigation(), 200),
          ),
        ),
        token: '1|secret',
        username: 'raul',
        memory: memory,
        onUnauthorised: () async {},
      );

      final loading = controller.load();
      controller.dispose();

      await expectLater(loading, completes);
    },
  );

  group('refresh', () {
    test('keeps the current area and picks up new menus', () async {
      var answer = groupUserNavigation(chat: false);
      final (:controller, memory: _, expiries: _) = _controller(
        (_) async => _json(answer, 200),
        remembered: {'raul': 'warehouse:europa-drive'},
      );
      await controller.load();

      answer = groupUserNavigation(chat: true);
      await controller.refresh();

      final state = controller.state as NavigationReady;
      expect(state.area.key, 'warehouse:europa-drive');
      expect(state.data.tabs.chat, isTrue);
    });

    test('moves to the default when the current area was taken away', () async {
      var answer = groupUserNavigation();
      final (:controller, memory: _, expiries: _) = _controller(
        (_) async => _json(answer, 200),
        remembered: {'raul': 'warehouse:europa-drive'},
      );
      await controller.load();

      answer = groupUserNavigation(organisations: []);
      await controller.refresh();

      expect(_currentArea(controller).key, 'group:aw-group');
    });

    test('never shows a loading state', () async {
      final (:controller, memory: _, expiries: _) = _controller(
        (_) async => _json(groupUserNavigation(), 200),
      );
      await controller.load();
      final seen = <NavigationState>[];
      controller.addListener(() => seen.add(controller.state));

      await controller.refresh();

      expect(seen, everyElement(isA<NavigationReady>()));
    });

    test('keeps the menus shown when aiku cannot be reached', () async {
      var isOnline = true;
      final (:controller, memory: _, expiries: _) = _controller(
        (request) async => isOnline
            ? _json(groupUserNavigation(), 200)
            : throw http.ClientException('offline', request.url),
      );
      await controller.load();

      isOnline = false;
      await controller.refresh();

      expect(_currentArea(controller).key, 'group:aw-group');
    });

    test('a 401 hands over to sign-out', () async {
      var status = 200;
      final (:controller, memory: _, :expiries) = _controller(
        (_) async => status == 200
            ? _json(groupUserNavigation(), 200)
            : http.Response('', status),
      );
      await controller.load();

      status = 401;
      await controller.refresh();

      expect(expiries, hasLength(1));
    });

    test('after a failed load it loads again', () async {
      var status = 500;
      final (:controller, memory: _, expiries: _) = _controller(
        (_) async => status == 200
            ? _json(groupUserNavigation(), 200)
            : http.Response('', status),
      );
      await controller.load();
      expect(controller.state, isA<NavigationFailed>());

      status = 200;
      await controller.refresh();

      expect(_currentArea(controller).key, 'group:aw-group');
    });
  });
}
