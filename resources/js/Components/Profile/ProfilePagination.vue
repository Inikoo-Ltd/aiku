<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft, faChevronRight, faChevronDoubleLeft, faChevronDoubleRight } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faChevronLeft, faChevronRight, faChevronDoubleLeft, faChevronDoubleRight)

const props = withDefaults(defineProps<{
    currentPage: number
    lastPage: number
    report: string
    rowsPerPageOptions?: number[]
    disabled?: boolean
}>(), {
    rowsPerPageOptions: () => [],
    disabled: false,
})

const rowsPerPage = defineModel<number>("rowsPerPage")

const emit = defineEmits<{
    (e: "page", page: number): void
}>()

const visiblePages = computed(() => {
    const maxButtons = 5
    const start = Math.max(1, Math.min(props.currentPage - Math.floor(maxButtons / 2), props.lastPage - maxButtons + 1))
    const end = Math.min(props.lastPage, start + maxButtons - 1)

    return Array.from({ length: end - start + 1 }, (_, index) => start + index)
})

const goTo = (page: number) => {
    const target = Math.min(Math.max(1, page), props.lastPage)
    if (target !== props.currentPage && !props.disabled) {
        emit("page", target)
    }
}

const arrowClass = "h-8 w-8 rounded-full flex items-center justify-center hover:bg-gray-100 disabled:opacity-30 disabled:hover:bg-transparent"
</script>

<template>
    <div class="shrink-0 flex flex-col gap-y-2 border-t border-gray-200 py-3 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between sm:gap-x-4">
        <div class="flex items-center justify-between sm:justify-start sm:gap-x-1">
            <div class="flex items-center gap-x-1">
                <button type="button" :class="arrowClass" :disabled="currentPage === 1 || disabled" :aria-label="ctrans('First page')" @click="goTo(1)">
                    <FontAwesomeIcon icon="fal fa-chevron-double-left" class="text-xs" fixed-width aria-hidden="true" />
                </button>
                <button type="button" :class="arrowClass" :disabled="currentPage === 1 || disabled" :aria-label="ctrans('Previous page')" @click="goTo(currentPage - 1)">
                    <FontAwesomeIcon icon="fal fa-chevron-left" class="text-xs" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div class="flex items-center gap-x-1">
                <button v-for="page in visiblePages" :key="page" type="button" :disabled="disabled"
                    class="h-8 min-w-8 rounded-full px-2 tabular-nums transition-colors"
                    :class="page === currentPage ? 'bg-[color:var(--app-accent)] font-semibold text-[color:var(--app-accent-text)]' : 'hover:bg-gray-100'"
                    @click="goTo(page)">
                    {{ page }}
                </button>
            </div>

            <div class="flex items-center gap-x-1">
                <button type="button" :class="arrowClass" :disabled="currentPage === lastPage || disabled" :aria-label="ctrans('Next page')" @click="goTo(currentPage + 1)">
                    <FontAwesomeIcon icon="fal fa-chevron-right" class="text-xs" fixed-width aria-hidden="true" />
                </button>
                <button type="button" :class="arrowClass" :disabled="currentPage === lastPage || disabled" :aria-label="ctrans('Last page')" @click="goTo(lastPage)">
                    <FontAwesomeIcon icon="fal fa-chevron-double-right" class="text-xs" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between gap-x-3">
            <span class="tabular-nums">{{ report }}</span>
            <label v-if="rowsPerPageOptions.length" class="flex items-center gap-x-2">
                <span class="sr-only">{{ ctrans('Rows per page') }}</span>
                <select v-model.number="rowsPerPage"
                    class="rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm focus:border-[color:var(--app-accent)] focus:ring-[color:var(--app-accent)]">
                    <option v-for="option in rowsPerPageOptions" :key="option" :value="option">{{ option }}</option>
                </select>
            </label>
        </div>
    </div>
</template>
