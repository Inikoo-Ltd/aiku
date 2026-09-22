<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 27 Sep 2023 11:35:29 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { useLocaleStore } from "@/Stores/locale"
import { capitalize } from "@/Composables/capitalize"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLevelUp } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import AddressLocation from '@/Components/Elements/Info/AddressLocation.vue'
library.add(faLevelUp)


const props = defineProps<{
    data?: {
        // key?: string
        icon?: string | string[]
        label?: string | string[]
        tooltip?: string
    }
}>()

</script>

<template>
    <!-- <template v-if="data?.key === 'address' && data?.label">
        <AddressLocation :data="data.label" />
    </template> -->
    
    <!-- <template v-else> -->
        <FontAwesomeIcon v-if="data?.icon" :icon="data?.icon" v-tooltip="capitalize(data?.tooltip)" fixed-width aria-hidden="true"
            size="xs" />
        <span class="leading-none mx-1">{{ data?.label }}</span>
        <FontAwesomeIcon flip="horizontal" :icon="'fal fa-level-up'" :class="'mr-2'" fixed-width />
    <!-- </template> -->
</template>
