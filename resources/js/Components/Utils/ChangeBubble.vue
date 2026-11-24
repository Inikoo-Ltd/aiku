<script lang="ts">
import { reactive } from "vue"

const waiting = reactive(new Map<string, number>())
const visit = reactive({ closedOne: false })
</script>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ctrans } from "@/Composables/useTrans"

library.add(faTimes)

const props = withDefaults(defineProps<{
    name: string
    text: string
    released: string
    days?: number
}>(), {
    days: 14,
})

const storageKey = `change-bubble:${props.name}`
const releasedAt = new Date(props.released).getTime()
const DAY = 86_400_000

const isOldestWaiting = computed(() => {
    const oldest = [...waiting.entries()].sort((a, b) => a[1] - b[1])[0]

    return oldest?.[0] === props.name
})
const isShown = computed(() => !visit.closedOne && isOldestWaiting.value)

const wasDismissed = (): boolean => {
    try {
        return !!localStorage.getItem(storageKey)
    } catch {
        return true
    }
}

onMounted(() => {
    if (Date.now() > releasedAt + props.days * DAY || wasDismissed()) {
        return
    }
    waiting.set(props.name, releasedAt)
})

onBeforeUnmount(() => waiting.delete(props.name))

const dismiss = () => {
    waiting.delete(props.name)
    visit.closedOne = true
    try {
        localStorage.setItem(storageKey, new Date().toISOString())
    } catch {
        return
    }
}
</script>

<template>
    <div class="relative h-full">
        <slot />
        <div
            v-if="isShown"
            role="status"
            class="absolute bottom-full right-0 z-30 mb-2 flex w-max max-w-64 items-start gap-x-2 rounded-lg bg-[var(--app-accent)] py-2 pl-3 pr-2 text-xs leading-snug text-[var(--app-accent-text)] shadow-lg">
            <span>{{ text }}</span>
            <button type="button" :aria-label="ctrans('Close')" class="-my-0.5 shrink-0 rounded px-1 opacity-80 hover:opacity-100" @click="dismiss">
                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
            </button>
            <span class="absolute -bottom-1 right-6 h-2 w-2 rotate-45 bg-[var(--app-accent)]" aria-hidden="true" />
        </div>
    </div>
</template>
