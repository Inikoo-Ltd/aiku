/*
 * Author Louis Perez
 * Created on 06-11-2026-09h-27m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'dart:async';

import 'package:flutter/material.dart';

import '../api/aiku_api_client.dart';
import '../api/navigation.dart';
import '../auth/auth_controller.dart';
import '../auth/token_store.dart';
import '../config/app_config.dart';
import '../ui/app_colours.dart';
import '../ui/load_problem.dart';
import '../ui/loading_indicator.dart';
import '../ui/menu_icons.dart';
import 'appearance.dart';
import 'area_drawer.dart';
import 'area_homes.dart';
import 'area_memory.dart';
import 'dashboard_loader.dart';
import 'navigation_controller.dart';
import 'placeholder_screens.dart';
import 'profile_menu.dart';
import 'settings_screen.dart';
import 'theme_screen.dart';

/// The bottom-bar tabs. Tasks, Tickets and Chat are one hub each, across
/// every area.
enum ShellTab {
  home('Home', Icons.home_outlined, Icons.home),
  tasks('Tasks', Icons.checklist, Icons.checklist),
  tickets(
    'Tickets',
    Icons.confirmation_number_outlined,
    Icons.confirmation_number,
  ),
  chat('Chat', Icons.chat_bubble_outline, Icons.chat_bubble);

  const ShellTab(this.label, this.icon, this.selectedIcon);

  /// The tab's name.
  final String label;

  /// The icon while not selected.
  final IconData icon;

  /// The icon while selected.
  final IconData selectedIcon;

  /// The key of this tab's button on the bottom bar, for tests.
  ValueKey<String> get key => ValueKey('shell.tab.$name');
}

/// The signed-in app: ☰ menu and area at the top left, the profile picture
/// at the top right, the area's Home and the hubs on the bottom bar.
class AppShell extends StatefulWidget {
  const AppShell({
    super.key,
    required this.authController,
    required this.session,
    required this.client,
    required this.config,
    required this.areaMemory,
    required this.appearance,
  });

  /// Keys for tests.
  static const menuButtonKey = ValueKey('shell.menu');
  static const titleKey = ValueKey('shell.title');
  static const bottomBarKey = ValueKey('shell.bottomBar');
  static const retryKey = ValueKey('shell.retry');

  /// Runs Theme and Log out, and is told when aiku answers 401.
  final AuthController authController;

  /// The signed-in user and their token.
  final StoredSession session;

  /// The aiku API.
  final AikuApiClient client;

  /// The build settings, for the Settings screen.
  final AppConfig config;

  /// Remembers the last area on the phone.
  final AreaMemory areaMemory;

  /// Light, dark or the phone's setting, chosen on the Theme screen.
  final AppearanceController appearance;

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  final _scaffoldKey = GlobalKey<ScaffoldState>();
  late final NavigationController _navigation;
  late final DashboardSource _dashboards;
  late final AppLifecycleListener _lifecycle;
  ShellTab _tab = ShellTab.home;

  @override
  void initState() {
    super.initState();
    _dashboards = DashboardSource(
      client: widget.client,
      token: widget.session.token,
    );
    _navigation = NavigationController(
      client: widget.client,
      token: widget.session.token,
      username: widget.session.profile.username,
      memory: widget.areaMemory,
      onUnauthorised: widget.authController.sessionExpired,
    );
    _lifecycle = AppLifecycleListener(onResume: _refreshFromAiku);
    // The shell shows a loading state until the menus arrive, and the saved
    // theme until the profile does; nothing waits on either here.
    unawaited(_navigation.load());
    unawaited(widget.authController.refreshProfile());
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    _navigation.dispose();
    super.dispose();
  }

  /// Picks up permission, theme and name changes made in aiku while the app
  /// was in the background. Neither refresh shows a loading state.
  void _refreshFromAiku() {
    unawaited(_navigation.refresh());
    unawaited(widget.authController.refreshProfile());
  }

  void _push(Widget Function(BuildContext context) builder) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: builder));
  }

  void _openUnbuilt(String title) {
    _scaffoldKey.currentState?.closeDrawer();
    _push((_) => NotOnMobileYetScreen(title: title));
  }

  void _selectArea(Area area) {
    _scaffoldKey.currentState?.closeDrawer();
    setState(() => _tab = ShellTab.home);
    unawaited(_navigation.selectArea(area));
  }

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);

    return ListenableBuilder(
      listenable: _navigation,
      builder: (context, _) {
        final state = _navigation.state;
        final tabs = switch (state) {
          NavigationReady(:final data) => [
            ShellTab.home,
            if (data.tabs.tasks) ShellTab.tasks,
            if (data.tabs.tickets) ShellTab.tickets,
            if (data.tabs.chat) ShellTab.chat,
          ],
          _ => const [ShellTab.home],
        };
        final tab = tabs.contains(_tab) ? _tab : ShellTab.home;

        return Scaffold(
          key: _scaffoldKey,
          // The app bar is the top of the theme band (see the app's theme).
          appBar: AppBar(
            leading: IconButton(
              key: AppShell.menuButtonKey,
              tooltip: 'Menu',
              icon: const Icon(Icons.menu),
              onPressed: () => _scaffoldKey.currentState?.openDrawer(),
            ),
            titleSpacing: 0,
            title: switch (state) {
              NavigationReady(:final area) => _AreaTitle(
                key: AppShell.titleKey,
                area: area,
              ),
              _ => const Text('aiku', key: AppShell.titleKey),
            },
            actions: [
              ProfileMenu(
                profile: widget.session.profile,
                onTheme: () => _push(
                  (_) => ThemeScreen(
                    controller: widget.authController,
                    appearance: widget.appearance,
                  ),
                ),
                onSettings: () =>
                    _push((_) => SettingsScreen(config: widget.config)),
                onSignOut: widget.authController.signOut,
              ),
              const SizedBox(width: 8),
            ],
          ),
          drawer: switch (state) {
            NavigationReady(:final data, :final area) => AreaDrawer(
              data: data,
              area: area,
              onSelectArea: _selectArea,
              onOpenUnbuilt: _openUnbuilt,
            ),
            NavigationLoading() || NavigationFailed() => const _WaitingDrawer(),
          },
          body: switch (state) {
            NavigationLoading() => Center(
              child: LoadingIndicator(color: colours.accentText, size: 24),
            ),
            NavigationFailed(:final messages) => _LoadFailed(
              messages: messages,
              onRetry: _navigation.load,
            ),
            NavigationReady(:final area) => switch (tab) {
              ShellTab.home => AreaHome(
                area: area,
                source: _dashboards,
                onUnauthorised: widget.authController.sessionExpired,
                onOpenUnbuilt: _openUnbuilt,
              ),
              ShellTab.tasks ||
              ShellTab.tickets ||
              ShellTab.chat => HubPlaceholder(name: tab.label, icon: tab.icon),
            },
          },
          // A bar with Home alone would have nothing to switch to.
          bottomNavigationBar: tabs.length < 2
              ? null
              : _BottomBar(
                  tabs: tabs,
                  selected: tab,
                  onSelected: (selected) => setState(() => _tab = selected),
                ),
        );
      },
    );
  }
}

/// Home and the hubs aiku allows; the chosen one in the accent, no pill.
class _BottomBar extends StatelessWidget {
  const _BottomBar({
    required this.tabs,
    required this.selected,
    required this.onSelected,
  });

  final List<ShellTab> tabs;
  final ShellTab selected;
  final ValueChanged<ShellTab> onSelected;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    return DecoratedBox(
      decoration: BoxDecoration(
        border: Border(top: BorderSide(color: colours.hairline)),
      ),
      child: NavigationBarTheme(
        data: NavigationBarThemeData(
          labelTextStyle: WidgetStateProperty.resolveWith(
            (states) => TextStyle(
              fontSize: 12,
              fontWeight: states.contains(WidgetState.selected)
                  ? FontWeight.w600
                  : FontWeight.w500,
              color: states.contains(WidgetState.selected)
                  ? colours.accentText
                  : colours.muted,
            ),
          ),
        ),
        child: NavigationBar(
          key: AppShell.bottomBarKey,
          selectedIndex: tabs.indexOf(selected),
          onDestinationSelected: (index) => onSelected(tabs[index]),
          backgroundColor: colours.background,
          surfaceTintColor: Colors.transparent,
          indicatorColor: Colors.transparent,
          elevation: 0,
          height: 60,
          destinations: [
            for (final tab in tabs)
              NavigationDestination(
                key: tab.key,
                icon: Icon(tab.icon, color: colours.muted),
                selectedIcon: Icon(tab.selectedIcon, color: colours.accentText),
                label: tab.label,
              ),
          ],
        ),
      ),
    );
  }
}

/// The area's name, with what kind of area it is underneath, on the band.
class _AreaTitle extends StatelessWidget {
  const _AreaTitle({super.key, required this.area});

  final Area area;

  @override
  Widget build(BuildContext context) {
    final colours = AppColours.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          area.name,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w600,
            color: colours.onBand,
          ),
        ),
        Text(
          areaSubtitle(area),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(fontSize: 12, color: colours.onBandMuted),
        ),
      ],
    );
  }
}

/// What the drawer shows before the menus have arrived.
class _WaitingDrawer extends StatelessWidget {
  const _WaitingDrawer();

  @override
  Widget build(BuildContext context) {
    return Drawer(
      child: Center(
        child: LoadingIndicator(
          color: AppColours.of(context).accentText,
          size: 24,
        ),
      ),
    );
  }
}

/// aiku could not be asked for the menus, or gave the user nothing to open.
class _LoadFailed extends StatelessWidget {
  const _LoadFailed({required this.messages, required this.onRetry});

  final List<String> messages;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(24, 12, 24, 32),
      children: [
        LoadProblem(
          messages: messages,
          onRetry: onRetry,
          retryKey: AppShell.retryKey,
        ),
      ],
    );
  }
}
