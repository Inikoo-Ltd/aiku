/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 May 2025 17:19:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

import {FlashNotification  as FlashNotificationType} from "@/types/FlashNotification";


export function useNotificationStyles() {
  const getDataWarning = (notif: FlashNotificationType) => {
    if (notif.status === "success") {
      return {
        message: notif.title,
        bgColor: "bg-green-200",
        textColor: "text-green-600",
        icon: "fas fa-check-circle",
        description: notif.message
      };
    } else {
      return {
        message: notif.title,
        bgColor: "bg-red-200",
        textColor: "text-red-600",
        icon: "fad fa-exclamation-triangle",
        description: notif.message
      };
    }
  };

  return {
    getDataWarning
  };
}