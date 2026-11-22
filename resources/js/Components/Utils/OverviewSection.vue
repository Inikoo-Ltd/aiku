<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import axios from "axios"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faChevronRight } from "@fal"

library.add(faChevronDown, faChevronRight)

const props = defineProps<{
    sectionKey: string
    title: string
    icon?: string
    sections: Record<string, boolean>
    summary?: string
}>()

const isOpen = computed(() => props.sections[props.sectionKey] ?? true)

const toggle = () => {
    props.sections[props.sectionKey] = !isOpen.value
    axios.patch(route("grp.models.profile.update"), { overview_sections: props.sections })
}
</script>

<template>
    <section>
        <div class="flex items-center gap-2">
        <button
            type="button"
            class="flex min-w-0 flex-1 items-center gap-2 py-1 text-left text-xs font-medium uppercase tracking-wide text-gray-500 hover:text-gray-800"
            :aria-expanded="isOpen"
            @click="toggle">
            <FontAwesomeIcon :icon="isOpen ? 'fal fa-chevron-down' : 'fal fa-chevron-right'" class="text-gray-400" fixed-width aria-hidden="true" />
            <FontAwesomeIcon v-if="icon" :icon="icon" class="text-gray-400" fixed-width aria-hidden="true" />
            {{ title }}
            <span v-if="!isOpen && summary" class="ml-1 truncate normal-case tracking-normal text-gray-400">{{ summary }}</span>
        </button>
        <slot v-if="isOpen" name="actions" />
        </div>
        <div v-if="isOpen" class="mt-2">
            <slot />
        </div>
    </section>
</template>
