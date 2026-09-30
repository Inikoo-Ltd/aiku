<!--
 Author Louis Perez
 Created on 30-09-2026-11h-04m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { ref } from "vue"
import { Link } from "@inertiajs/vue3"
import Popover from "primevue/popover"
import { FontAwesomeIcon, FontAwesomeLayers } from "@fortawesome/vue-fontawesome"
import { faCubes } from "@fal"
import { faLink, faCircle } from "@fas"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

// Heading icon for a product sold only as a complete set: hovering lists the parts it is made of
withDefaults(defineProps<{
    set: {
        product?: { code: string, name: string }
        parts: { code: string, name: string, quantity: number }[]
        route: routeType | null
    }
    note?: string
    color?: string
}>(), {
    color: 'var(--app-accent)',
})

const popover = ref<InstanceType<typeof Popover> | null>(null)
</script>

<template>
    <component
        :is="set.route ? Link : 'span'"
        :href="set.route ? route(set.route.name, set.route.parameters) : undefined"
        class="inline-flex rounded transition-opacity hover:opacity-75 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--app-accent)]"
        :style="{ color }"
        :aria-label="ctrans('Sold only as a complete set')"
        tabindex="0"
        @mouseenter="(event: Event) => popover?.show(event)"
        @mouseleave="popover?.hide()"
        @focus="(event: Event) => popover?.show(event)"
        @blur="popover?.hide()"
    >
        <FontAwesomeLayers fixed-width>
            <FontAwesomeIcon :icon="faCubes" fixed-width />
            <FontAwesomeIcon :icon="faCircle" class="text-white" transform="shrink-6 down-5 right-6" fixed-width />
            <FontAwesomeIcon :icon="faLink" transform="shrink-9 down-5 right-6" fixed-width />
        </FontAwesomeLayers>
    </component>

    <Popover ref="popover">
        <div class="max-w-xs space-y-2 text-sm font-normal tracking-normal">
            <p class="font-semibold text-gray-800">{{ ctrans('Sold only as a complete set') }}</p>
            <p v-if="set.product" class="text-gray-700">
                <span class="font-semibold" :style="{ color }">{{ set.product.code }}</span>
                {{ set.product.name }}
            </p>
            <p class="text-gray-500">
                {{ ctrans('If one part can not be picked, the warehouse puts the other parts back and the customer is refunded the whole product.') }}
            </p>
            <ul class="divide-y divide-gray-100 border-t border-gray-100">
                <li v-for="part in set.parts" :key="part.code" class="flex items-baseline gap-x-2 py-1.5">
                    <span class="tabular-nums font-semibold" :style="{ color }">{{ part.quantity }}&times;</span>
                    <span class="min-w-0">
                        <span class="block text-gray-700">{{ part.name }}</span>
                        <span class="block text-xs text-gray-400">{{ part.code }}</span>
                    </span>
                </li>
            </ul>
            <p v-if="note" class="text-xs text-gray-500">{{ note }}</p>
            <p v-if="set.route" class="text-xs text-gray-400">{{ ctrans('Click the icon to change it in Composition') }}</p>
        </div>
    </Popover>
</template>
