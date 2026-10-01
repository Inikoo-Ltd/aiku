<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 02 Oct 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStar } from "@fortawesome/free-solid-svg-icons"

const props = defineProps<{
    field: string
    reviewRoute: { name: string; parameters: Record<string, unknown> }
}>()

const rating = ref(0)
const hovered = ref(0)
const isSaving = ref(false)

const rate = async (stars: number) => {
    isSaving.value = true
    try {
        await axios.post(route(props.reviewRoute.name, props.reviewRoute.parameters), { field: props.field, rating: stars })
        rating.value = stars
    } catch {
        notify({ title: ctrans("Something went wrong"), text: ctrans("The rating was not saved, please try again."), type: "error" })
    } finally {
        isSaving.value = false
    }
}
</script>

<template>
    <div class="flex items-center gap-2 text-xs text-gray-500">
        <span>{{ rating ? ctrans("Thanks, your edits to this translation are kept to teach the translator") : ctrans("How good is this machine translation?") }}</span>
        <div class="flex items-center" @mouseleave="hovered = 0">
            <button
                v-for="star in 5"
                :key="star"
                type="button"
                :disabled="isSaving"
                class="px-0.5 transition-colors disabled:opacity-50"
                :class="star <= (hovered || rating) ? 'text-amber-400' : 'text-gray-300'"
                :aria-label="ctrans(':n stars', { n: String(star) })"
                @mouseenter="hovered = star"
                @click="rate(star)"
            >
                <FontAwesomeIcon :icon="faStar" fixed-width />
            </button>
        </div>
    </div>
</template>
