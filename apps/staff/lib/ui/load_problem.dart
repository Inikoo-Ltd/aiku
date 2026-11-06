/*
 * Author Louis Perez
 * Created on 06-11-2026-10h-17m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import 'aiku_buttons.dart';
import 'app_colours.dart';
import 'form_error_list.dart';

/// Why something couldn't be loaded from aiku, with a Try again button.
class LoadProblem extends StatelessWidget {
  const LoadProblem({
    super.key,
    required this.messages,
    required this.onRetry,
    this.retryKey,
  });

  /// What went wrong, as the user should read it.
  final List<String> messages;

  /// Loads again.
  final VoidCallback onRetry;

  /// The Try again button's key, for tests.
  final Key? retryKey;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        FormErrorList(messages: messages),
        const SizedBox(height: 16),
        BorderedIconButton(
          key: retryKey,
          label: 'Try again',
          icon: Icons.refresh,
          iconColor: AppColours.of(context).accentText,
          onPressed: onRetry,
        ),
      ],
    );
  }
}
