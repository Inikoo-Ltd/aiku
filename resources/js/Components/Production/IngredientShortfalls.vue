<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 19 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

export type IngredientShortfall = {
    code: string
    artefact_id: number
    is_made_here: boolean
    packed_in: number
    needed_pieces: number
    not_in_stock_skos: number
    short_pieces: number
    short_skos: number
    waiting_for: string[]
}

const props = defineProps<{
    shortfalls: IngredientShortfall[]
    canCreate?: boolean
    createHref?: (shortfall: IngredientShortfall) => string
}>()

const emits = defineEmits<{ (e: "create", shortfall: IngredientShortfall): void }>()

const locale = useLocaleStore()
const canMake = (shortfall: IngredientShortfall) => props.canCreate && shortfall.is_made_here && shortfall.short_skos > 0
const create = (shortfall: IngredientShortfall) => props.createHref ? router.visit(props.createHref(shortfall)) : emits("create", shortfall)
</script>

<template>
    <div v-if="shortfalls.length" class="space-y-0.5 text-xs" @click.stop @mousedown.stop>
        <div v-for="shortfall in shortfalls" :key="shortfall.code" class="flex flex-wrap items-center gap-x-1.5" :class="shortfall.short_skos > 0 ? 'text-amber-700' : 'text-sky-700'">
            <span v-if="shortfall.short_skos > 0">
                {{ ctrans("Needs :count SKO :code, not in stock: make :code first", { count: locale.number(shortfall.short_skos), code: shortfall.code }) }}
            </span>
            <span v-else>
                {{ ctrans("Waits for :count SKO :code from :jobs", { count: locale.number(shortfall.not_in_stock_skos), code: shortfall.code, jobs: shortfall.waiting_for.join(", ") }) }}
            </span>
            <span v-if="shortfall.short_skos > 0 && shortfall.waiting_for.length" class="text-gray-500">
                ({{ ctrans("the rest comes from :jobs", { jobs: shortfall.waiting_for.join(", ") }) }})
            </span>
            <Button v-if="canMake(shortfall)" type="tertiary" size="xxs" :label="ctrans('Create :code job', { code: shortfall.code })" @click="create(shortfall)">
                <template #icon>
                    <FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>
    </div>
</template>
