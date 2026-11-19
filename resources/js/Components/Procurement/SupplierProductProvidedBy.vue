<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-19 09:42
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import Image from "@common/Components/Image.vue"
import Icon from "@/Components/Icon.vue"

const props = defineProps<{
    parties: {
        label: string
        icon: string
        name: string | null
        code: string | null
        image: object | null
        route: routeType
    }[]
    otherSuppliers: {
        supplier_code: string
        supplier_name: string
        image: object | null
        code: string
        is_available: boolean
        supplier_route: routeType
        route: routeType | null
    }[]
    compact?: boolean
}>()

const gridClass = props.compact ? "grid gap-2" : "grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
</script>

<template>
    <div>
        <h3 class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
            <Icon :data="{ icon: 'fal fa-person-dolly' }" />
            {{ ctrans("Provided by") }}
        </h3>
        <div :class="gridClass">
            <component :is="party.route ? Link : 'div'" v-for="party in parties" :key="party.label"
                :href="party.route ? route(party.route.name, party.route.parameters) : undefined"
                class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 transition hover:border-gray-300 hover:bg-gray-50">
                <Image v-if="party.image" :src="party.image" class="h-8 w-8 shrink-0" />
                <Icon v-else :data="{ icon: party.icon }" class="w-8 shrink-0 text-center text-gray-400" />
                <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wide text-gray-400">{{ party.label }}</div>
                    <div class="truncate text-sm font-medium text-gray-700">{{ party.name }}</div>
                </div>
                <span class="ml-auto whitespace-nowrap text-xs tabular-nums text-gray-400">{{ party.code }}</span>
            </component>
        </div>

        <template v-if="otherSuppliers.length">
            <div class="mb-2 mt-4 text-xs text-gray-500">
                {{ ctrans("Also supplied by :count other suppliers", { count: otherSuppliers.length }) }}
            </div>
            <div :class="gridClass">
                <div v-for="other in otherSuppliers" :key="other.supplier_code + other.code"
                    class="flex items-center gap-3 rounded-lg border border-dashed border-gray-200 px-3 py-2">
                    <Link :href="route(other.supplier_route.name, other.supplier_route.parameters)" class="shrink-0"
                        v-tooltip="ctrans('Open supplier')">
                        <Image v-if="other.image" :src="other.image" class="h-8 w-8" />
                        <Icon v-else :data="{ icon: 'fal fa-person-dolly' }" class="w-8 text-center text-gray-400" />
                    </Link>
                    <div class="min-w-0">
                        <Link :href="route(other.supplier_route.name, other.supplier_route.parameters)"
                            class="block truncate text-sm font-medium text-gray-700 hover:text-gray-900 hover:underline">
                            {{ other.supplier_name }}
                        </Link>
                        <div class="whitespace-nowrap text-xs text-gray-400">
                            <Link v-if="other.route" :href="route(other.route.name, other.route.parameters)"
                                class="hover:text-gray-700 hover:underline" v-tooltip="ctrans('Open supplier product')">
                                {{ other.code }}
                            </Link>
                            <span v-else>{{ other.code }}</span>
                            <span v-if="!other.is_available" class="ml-1 text-red-600">· {{ ctrans("Unavailable") }}</span>
                        </div>
                    </div>
                    <span class="ml-auto whitespace-nowrap text-xs tabular-nums text-gray-400">{{ other.supplier_code }}</span>
                </div>
            </div>
        </template>
    </div>
</template>
