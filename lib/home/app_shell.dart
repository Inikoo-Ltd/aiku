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
import '../ui/accent_shades.dart';
import '../ui/aiku_buttons.dart';
import '../ui/aiku_palette.dart';
import '../ui/aiku_theme.dart';
import '../ui/form_error_list.dart';
import '../ui/loading_indicator.dart';
import '../ui/menu_icons.dart';
import 'area_drawer.dart';
import 'area_homes.dart';
import 'area_memory.dart';
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

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  final _scaffoldKey = GlobalKey<ScaffoldState>();
  late final NavigationController _navigation;
  late final AppLifecycleListener _lifecycle;
  ShellTab _tab = ShellTab.home;

  @override
  void initState() {
    super.initState();
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
    final shades = AccentShades.fromAppTheme(
      effectiveTheme(widget.session.profile),
    );

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
                shades: shades,
                onTheme: () => _push(
                  (_) => ThemeScreen(controller: widget.authController),
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
              shades: shades,
              onSelectArea: _selectArea,
              onOpenUnbuilt: _openUnbuilt,
            ),
            NavigationLoading() ||
            NavigationFailed() => _WaitingDrawer(shades: shades),
          },
          body: switch (state) {
            NavigationLoading() => Center(
              child: LoadingIndicator(color: shades.accent, size: 24),
            ),
            NavigationFailed(:final messages) => _LoadFailed(
              messages: messages,
              shades: shades,
              onRetry: _navigation.load,
            ),
            NavigationReady(:final area) => switch (tab) {
              ShellTab.home => AreaHome(
                area: area,
                shades: shades,
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
              : NavigationBar(
                  key: AppShell.bottomBarKey,
                  selectedIndex: tabs.indexOf(tab),
                  onDestinationSelected: (index) =>
                      setState(() => _tab = tabs[index]),
                  backgroundColor: Colors.white,
                  indicatorColor: shades.muted,
                  height: 64,
                  destinations: [
                    for (final shellTab in tabs)
                      NavigationDestination(
                        key: shellTab.key,
                        icon: Icon(shellTab.icon),
                        selectedIcon: Icon(
                          shellTab.selectedIcon,
                          color: shades.deep,
                        ),
                        label: shellTab.label,
                      ),
                  ],
                ),
        );
      },
    );
  }
}

/// The area's name, with what kind of area it is underneath.
class _AreaTitle extends StatelessWidget {
  const _AreaTitle({super.key, required this.area});

  final Area area;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          area.name,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
        ),
        Text(
          areaSubtitle(area),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(fontSize: 12, color: AikuPalette.label),
        ),
      ],
    );
  }
}

/// What the drawer shows before the menus have arrived.
class _WaitingDrawer extends StatelessWidget {
  const _WaitingDrawer({required this.shades});

  final AccentShades shades;

  @override
  Widget build(BuildContext context) {
    return Drawer(
      backgroundColor: Colors.white,
      child: Center(child: LoadingIndicator(color: shades.accent, size: 24)),
    );
  }
}

/// aiku could not be asked for the menus, or gave the user nothing to open.
class _LoadFailed extends StatelessWidget {
  const _LoadFailed({
    required this.messages,
    required this.shades,
    required this.onRetry,
  });

  final List<String> messages;
  final AccentShades shades;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(24, 12, 24, 32),
      children: [
        FormErrorList(messages: messages),
        const SizedBox(height: 24),
        Align(
          alignment: Alignment.centerLeft,
          child: BorderedIconButton(
            key: AppShell.retryKey,
            label: 'Try again',
            icon: Icons.refresh,
            iconColor: shades.accent,
            onPressed: onRetry,
          ),
        ),
      ],
    );
  }
}
