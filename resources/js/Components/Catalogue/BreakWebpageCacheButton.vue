<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFragile } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import type { routeType } from "@/types/route"

const props = defineProps<{
    breakCacheRoute: routeType
}>()

const isBreaking = ref(false)
const result = ref<{ isCleared: boolean, text: string } | null>(null)

const breakCache = () => {
    router.post(route(props.breakCacheRoute.name, props.breakCacheRoute.parameters), {}, {
        preserveScroll: true,
        onStart: () => {
            isBreaking.value = true
            result.value = null
        },
        onSuccess: () => (result.value = { isCleared: true, text: ctrans("Cache cleared, you can crawl the page now") }),
        onError: (errors) => (result.value = { isCleared: false, text: Object.values(errors)[0] ?? ctrans("The cache could not be cleared, try again") }),
        onFinish: () => (isBreaking.value = false),
    })
}
</script>

<template>
    <div class="flex flex-col items-end gap-1">
        <Button
            type="tertiary"
            :loading="isBreaking"
            :tooltip="ctrans('Break cache: clears the saved copy of this page so the website shows your latest changes now, instead of up to 10 minutes after you save.')"
            @click="breakCache">
            <template #loading>
                <LoadingIcon v-if="isBreaking" />
            </template>
            <template #icon>
                <FontAwesomeIcon :icon="faFragile" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
            </template>
        </Button>
        <p v-if="result" role="status" class="text-xs" :class="result.isCleared ? 'text-green-600' : 'text-red-600'">{{ result.text }}</p>
    </div>
</template>
