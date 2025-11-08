<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faCactus, faIslandTropical, faSkullCow, faFish } from "@fal"
import { faPlus } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { ref } from "vue"
import { box } from "@/Assets/Iconscout/box"

library.add(faPlus, faCactus, faIslandTropical, faSkullCow, faFish)

defineProps<{
    data?: {
        action?: {
            label: string
            route: routeType
            style?: string
            tooltip: string
            icon?: string | string[]
        }
        description?: string
        title?: string
        icons?: string[]
    }
    isNoIcon?: boolean
}>()


const isLoading = ref(false)
</script>

<template>
    <div class="text-center border-gray-200 p-14">
        <h3 v-if="data?.title" class="text-lg font-light text-[#4b525b] tracking-wide pb-2">{{ data?.title || trans("No records found") }}</h3>
        <p v-if="data?.description" class="text-sm text-gray-500 inline-block">{{ data?.description }}</p>

        <div v-if="!isNoIcon" class="mb-6 h-64 aspect-square text-indigo-600 mx-auto" v-html="box" />

        <slot name="button-empty-state">
            <Link v-if="data?.action" as="div" :href="data?.action?.route?.name ? route(data?.action.route.name, data?.action.route.parameters) : '#'" @start="() => isLoading = true" :method="data?.action?.route?.method"
                  class="mt-4 block">
                <Button :style="data?.action.style" :icon="data?.action.icon" :label="data?.action.tooltip" :loading="isLoading" />
            </Link>
        </slot>

    </div>
</template>
