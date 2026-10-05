<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { useForm } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStar } from "@fortawesome/free-solid-svg-icons"
import { faChevronDown } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"

const props = defineProps<{
    rating: number | null
    ratingComment: string | null
    canRate: boolean
    rateRoute?: { name: string; parameters: Record<string, unknown> }
}>()

const readOpenState = (): boolean => {
    try {
        return localStorage.getItem("ticket_rating_open") !== "closed"
    } catch {
        return true
    }
}

const isOpen = ref(readOpenState())

const toggle = () => {
    isOpen.value = !isOpen.value
    try {
        localStorage.setItem("ticket_rating_open", isOpen.value ? "open" : "closed")
    } catch {
        return
    }
}

const hovered = ref(0)
const form = useForm<{ rating: number; comment: string }>({ rating: 0, comment: "" })

const submit = () => {
    if (!props.rateRoute || !form.rating) return
    form.post(route(props.rateRoute.name, props.rateRoute.parameters), { preserveScroll: true })
}
</script>

<template>
    <div v-if="rating" class="rounded-lg border border-green-200 bg-green-50 p-4">
        <p class="text-xs text-gray-500 mb-1">{{ ctrans("Satisfaction") }}</p>
        <div class="flex items-center gap-1 text-amber-400">
            <FontAwesomeIcon v-for="star in 5" :key="star" :icon="faStar" :class="star <= rating ? '' : 'text-gray-300'" fixed-width />
            <span class="ml-2 text-sm font-medium text-gray-700">{{ rating }}/5</span>
        </div>
        <p v-if="ratingComment" class="mt-2 text-sm whitespace-pre-wrap">{{ ratingComment }}</p>
    </div>

    <form v-else-if="canRate" class="rounded-lg border border-[--app-accent-muted] bg-[--app-accent-soft]" @submit.prevent="submit">
        <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left" @click="toggle">
            <span class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ ctrans("Rate this ticket") }}</span>
            <FontAwesomeIcon :icon="faChevronDown" fixed-width class="text-gray-400 transition-transform duration-200" :class="!isOpen && '-rotate-90'" />
        </button>
        <div v-show="isOpen" class="space-y-3 border-t border-[--app-accent-muted] p-4">
            <p class="text-sm font-medium">{{ ctrans("This ticket is resolved. How did we do?") }}</p>
            <div class="flex items-center gap-1 text-2xl" @mouseleave="hovered = 0">
                <button
                    v-for="star in 5"
                    :key="star"
                    type="button"
                    class="transition-colors"
                    :class="star <= (hovered || form.rating) ? 'text-amber-400' : 'text-gray-300'"
                    :aria-label="ctrans(':n stars', { n: String(star) })"
                    @mouseenter="hovered = star"
                    @click="form.rating = star"
                >
                    <FontAwesomeIcon :icon="faStar" fixed-width />
                </button>
            </div>
            <textarea v-model="form.comment" rows="2" class="w-full rounded-md border-gray-300 text-sm focus:border-gray-500 focus:ring-0" :placeholder="ctrans('Anything we could do better? (optional)')" />
            <p v-if="form.errors.rating" class="text-xs text-red-600">{{ form.errors.rating }}</p>
            <div class="flex justify-end">
                <Button :label="ctrans('Send rating')" :loading="form.processing" :disabled="!form.rating" @click="submit" />
            </div>
        </div>
    </form>
</template>
