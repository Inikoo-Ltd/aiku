---
paths:
  - app/Enums/SysAdmin/User/UserNotificationEnum.php
---

# User

## Send through deliverTo, not channelsFor (focus mode)
Raul, 18 Nov 2026: users can turn on focus mode (moon button next to the search; 30 min, 1 h, 4 h or 8 h; settings.focus_until, User::isFocusing). Code that sends a notification picks channels with UserNotificationEnum::deliverTo($user), which drops Slack and browser push while the user is focusing. channelsFor() is only for showing the user's chosen channels. In the browser, every pop-up and sound goes through isFocusOn() (useFocusMode.ts). The bell and email still arrive.
