<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ref } from "vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFolderPlus } from "@fal"

library.add(faFolderPlus)

type Target = { id: number; code: string; name: string }

defineProps<{
    fetchRoute: routeType
    placeholder: string
    noOptionsText: string
    moveLabel: string
    pickFirstLabel: string
    createRoute?: routeType
    createLabel?: string
    loading?: boolean
}>()

const emits = defineEmits<{ (e: "move", target: Target): void }>()

const target = ref<Target | null>(null)

const submit = () => {
    if (target.value) {
        emits("move", target.value)
    }
}

defineExpose({ reset: () => (target.value = null) })
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-2 gap-y-2">
        <div class="w-72 text-gray-700">
            <PureMultiselectInfiniteScroll
                v-model="target"
                :fetchRoute="fetchRoute"
                :placeholder="placeholder"
                :noOptionsText="noOptionsText"
                valueProp="id"
                labelProp="name"
                labelAdditionalProp="code"
                fetchOnOpen
                :object="true" />
        </div>

        <Button
            :label="moveLabel"
            :loading="loading"
            :disabled="!target"
            :tooltip="target ? `${moveLabel}: ${target.name}` : pickFirstLabel"
            @click="submit" />

        <Link
            v-if="createRoute"
            :href="route(createRoute.name, createRoute.parameters)"
            class="flex items-center gap-1.5 whitespace-nowrap text-sm text-indigo-100 underline underline-offset-2 hover:text-white">
            <FontAwesomeIcon icon="fal fa-folder-plus" fixed-width aria-hidden="true" />
            {{ createLabel }}
        </Link>
    </div>
</template>
