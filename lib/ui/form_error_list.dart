/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

import 'package:flutter/widgets.dart';

import 'aiku_palette.dart';

/// Messages not tied to a field, as a bulleted list under the form.
class FormErrorList extends StatelessWidget {
  const FormErrorList({super.key, required this.messages});

  /// The messages to list; nothing is drawn when empty.
  final List<String> messages;

  static const _style = TextStyle(fontSize: 14, color: AikuPalette.errorText);

  @override
  Widget build(BuildContext context) {
    if (messages.isEmpty) {
      return const SizedBox.shrink();
    }
    return Padding(
      padding: const EdgeInsets.only(top: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final message in messages)
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('•  ', style: _style),
                Expanded(child: Text(message, style: _style)),
              ],
            ),
        ],
      ),
    );
  }
}
