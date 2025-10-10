/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 25 Aug 2022 00:33:39 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */


import { defineStore } from "pinia"
import { layoutStructure } from "@/Composables/useLayoutStructure"

export const useLayoutStore = defineStore("layout", {
    state: () => (
        layoutStructure
    ),

    getters: {
        isShopPage: (state) => {
            return (state.currentRoute).includes('grp.org.shops.')
        },
        isFulfilmentPage: (state) => {
            return (state.currentRoute).includes('grp.org.fulfilments.')
        },
        // liveUsersWithoutMe: (state) => state.liveUsers.filter((liveUser, keyUser) => keyUser != layout.user.id )
    },
});

