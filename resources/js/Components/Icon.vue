<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 01 Feb 2024 09:15:14 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { Icon as IconTS } from "@/types/Utils/Icon"
import ZombieIcon from "@/Components/Icons/ZombieIcon.vue"
import Image from "@common/Components/Image.vue"

const svgComponents: Record<string, unknown> = {
    zombie: ZombieIcon,
}

defineProps<{
    data: IconTS
    title?: string
}>()
</script>

<template>

    <span
        v-if="data?.text"
        v-tooltip="title ? title : data.tooltip"
        :class="['inline-flex items-center justify-center font-bold text-xs', data.class]"
    >{{ data.text }}</span>

    <component
        :is="svgComponents[data.svg]"
        v-else-if="data?.svg && svgComponents[data.svg]"
        v-tooltip="title ? title : data.tooltip"
        :class="data.class"
    />

    <Image
        v-else-if="data?.image"
        :src="data.image"
        v-tooltip="title ? title : data.tooltip"
        :class="['aspect-square h-4', data.class]"
        :alt="data.tooltip ?? ''"
        :responsiveEnabled="false"
    />

    <img
        v-else-if="data?.img"
        :src="data.img"
        v-tooltip="title ? title : data.tooltip"
        :class="['w-4 h-4 object-contain', data.class]"
        :alt="data.tooltip ?? ''"
    />

    <FontAwesomeIcon
        v-else-if="data?.icon"
        v-tooltip="title ? title : data.tooltip"
        aria-hidden="true"
        :icon="data.icon"
        :class="data.class"
        fixed-width
        :rotation="data?.icon_rotation"
    />

</template>
