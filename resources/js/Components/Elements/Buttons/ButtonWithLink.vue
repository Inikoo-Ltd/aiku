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

import type { IconDefinition } from '@fal'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    style?: string | object
    size?: string
    icon?: string | string[] | IconDefinition
    iconRight?: string | string[] | IconDefinition
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
    fullLoading?: boolean
    isWithError?: boolean
}>()

const emits = defineEmits<{
    (e: "finish"): void
    (e: "start"): void
    (e: "error", error: {}): void
    (e: "success"): void
}>()

const isLoadingVisit = ref(false)

const setError = (e) => {
    console.error("Error", e)
    notify({
        title: trans("Something went wrong"),
        text: trans("Please try again or contact support."),
        type: "error",
    })
}
</script>

<template>
    <component
        :is="props.routeTarget || props.url ? Link : 'div'"
        :href="props.url || (props.routeTarget?.name ? route(props.routeTarget?.name, props.routeTarget?.parameters) : '#')"
        @start="() => (isLoadingVisit = true, emits('start'))"
        @success="() => (emits('success'))"
        @error="(e) => (isWithError ? setError(e) : false, emits('error', e))"
        @finish="() => (fullLoading ? '' : isLoadingVisit = false, emits('finish'))"
        :method="props.method || props.routeTarget?.method || undefined"
        :data="props.body ?? props.routeTarget?.body"
        v-bind="bindToLink"
        :class="full ? 'w-full' : ''"
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