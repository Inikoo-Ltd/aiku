/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

/// The parts of aiku's `ProfileApiResource` the app uses.
class const StaffProfile({
  required final String username,
  required final String? contactName,
  required final List<String>? appTheme,
  final List<String>? mobileAppTheme,
}) {
  /// Reads aiku's profile object, from a login response or a profile update.
  factory StaffProfile.fromJson(Map<String, Object?> json) => StaffProfile(
    username: switch (json['username']) {
      final String value => value,
      _ => '',
    },
    contactName: switch (json['contact_name']) {
      final String value => value,
      _ => null,
    },
    appTheme: _colours(json['app_theme']),
    mobileAppTheme: _colours(json['mobile_app_theme']),
  );

  /// This profile with [theme] as its mobile theme.
  StaffProfile withMobileAppTheme(List<String> theme) => StaffProfile(
    username: username,
    contactName: contactName,
    appTheme: appTheme,
    mobileAppTheme: theme,
  );

  /// The shape [StaffProfile.fromJson] reads, for keeping it on the device.
  Map<String, Object?> toJson() => {
    'username': username,
    'contact_name': contactName,
    'app_theme': appTheme,
    'mobile_app_theme': mobileAppTheme,
  };

  static List<String>? _colours(Object? value) => switch (value) {
    final List<Object?> colours => colours.whereType<String>().toList(),
    _ => null,
  };
}

/// A successful mobile login.
class const LoginResult({
  required final String token,
  required final DateTime? expiresAt,
  required final StaffProfile profile,
});
