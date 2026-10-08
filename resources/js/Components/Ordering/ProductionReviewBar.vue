<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle, faClock, faCheck, faUndoAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { routeType } from "@/types/route"

library.add(faCheck, faUndoAlt)

const props = defineProps<{
    review: {
        reviewed_at: string | null
        reviewed_by: string | null
        can_review: boolean
        route: routeType
    }
}>()

const locale = useLocaleStore()
const isSaving = ref(false)

const setReviewed = (reviewed: boolean) => {
    router.patch(route(props.review.route.name, props.review.route.parameters), { reviewed }, {
        preserveScroll: true,
        onStart: () => { isSaving.value = true },
        onFinish: () => { isSaving.value = false },
    })
}
</script>

<template>
    <div class="mx-3 mt-2 flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
        <div class="flex items-center gap-2">
            <span class="text-gray-500">{{ ctrans("Production review") }}:</span>
            <span v-if="review.reviewed_at" class="flex items-center gap-1.5 text-green-700">
                <FontAwesomeIcon :icon="faCheckCircle" class="text-green-600" fixed-width aria-hidden="true" />
                {{ ctrans("Reviewed by :name on :date", {
                    name: review.reviewed_by ?? '-',
                    date: useFormatTime(review.reviewed_at, { localeCode: locale.language.code, formatTime: "hm" })
                }) }}
            </span>
            <span v-else class="flex items-center gap-1.5 rounded border border-gray-300 bg-white px-2 py-0.5 text-gray-600">
                <FontAwesomeIcon :icon="faClock" fixed-width aria-hidden="true" />
                {{ ctrans("Pending review") }}
            </span>
        </div>

        <template v-if="review.can_review">
            <Button v-if="review.reviewed_at" type="tertiary" icon="fal fa-undo-alt" :label="ctrans('Reset review')" size="xs"
                :loading="isSaving" @click="setReviewed(false)" />
            <Button v-else type="primary" icon="fal fa-check" :label="ctrans('Mark production reviewed')" size="xs"
                :loading="isSaving" @click="setReviewed(true)" />
        </template>
    </div>
</template>
