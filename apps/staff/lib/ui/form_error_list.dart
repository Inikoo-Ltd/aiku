/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/material.dart';

import 'app_colours.dart';

/// Messages not tied to a field, as a bulleted list under the form, in the
/// fixed error red (a lighter red in dark mode).
class FormErrorList extends StatelessWidget {
  const FormErrorList({super.key, required this.messages});

  /// The messages to list; nothing is drawn when empty.
  final List<String> messages;

  @override
  Widget build(BuildContext context) {
    if (messages.isEmpty) {
      return const SizedBox.shrink();
    }
    final style = TextStyle(
      fontSize: 14,
      color: AppColours.of(context).errorText,
    );
    return Padding(
      padding: const EdgeInsets.only(top: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final message in messages)
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('•  ', style: style),
                Expanded(child: Text(message, style: style)),
              ],
            ),
        ],
      ),
    );
  }
}
