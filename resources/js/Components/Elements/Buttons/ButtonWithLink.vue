<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sun, 30 Oct 2022 15:27:23 Greenwich Mean Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref } from 'vue'
import { routeType } from '@/types/route'
import { Link } from '@inertiajs/vue3'
import Button from '@/Components/Elements/Buttons/Button.vue'

const props = defineProps<{
    style?: string | object
    size?: string
    icon?: string | string[]
    iconRight?: string | string[]
    action?: string
    label?: string
    full?: boolean
    capitalize?: boolean
    tooltip?: string
    loading?: boolean
    type?: string
    disabled?: boolean
    noHover?: boolean
    routeTarget?: routeType
    bindToLink?: {
        preserveScroll?: boolean
        preserveState?: boolean
    }
    url?: string
    method?: string
    body?: object
}>()


const isLoadingVisit = ref(false)
</script>

<template>
    <component
        :is="props.routeTarget || props.url ? Link : 'div'"
        :href="props.url || (props.routeTarget?.name ? route(props.routeTarget?.name, props.routeTarget?.parameters) : '#')"
        @start="() => isLoadingVisit = true"
        @finish="() => isLoadingVisit = false"
        :method="props.method || props.routeTarget?.method || undefined"
        :data="props.body ?? props.routeTarget?.body"
        v-bind="bindToLink"
    >
        <!-- Don't use v-bind make 'style' return empty object -->
        <Button
            :style="props.style"
            :size="props.size"
            :icon="props.icon"
            :iconRight="props.iconRight"
            :action="props.action"
            :label="props.label"
            :full="props.full"
            :capitalize="props.capitalize"
            :tooltip="props.tooltip"
            :loading="isLoadingVisit || props.loading"
            :type="props.type"
            :disabled="props.disabled"
            :noHover="props.noHover"
        >
            <template #loading>
                <slot name="loading" />
            </template>

            <template #icon>
                <slot name="icon" />
            </template>

            <template #label>
                <slot name="label" />
            </template>

            <template #iconRight>
                <slot name="iconRight" />
            </template>
        </Button>
    </component>
</template>