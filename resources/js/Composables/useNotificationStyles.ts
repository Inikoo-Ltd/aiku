/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 May 2025 17:19:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

import { FlashNotification as FlashNotificationType } from "@/types/FlashNotification"


export function useNotificationStyles() {
    const getDataWarning = (notif: FlashNotificationType) => {
        if (notif.status === "success") {
            return {
                title: notif.title,
                class: "bg-green-200 text-green-600 border border-green-300",
                icon: "fas fa-check-circle",
                description: notif.description
            }
        } else {
            return {
                title: notif.title,
                class: "bg-red-200 text-red-600 border border-red-300",
                icon: "fad fa-exclamation-triangle",
                description: notif.description
            }
        }
    }

    return {
        getDataWarning
    }
}