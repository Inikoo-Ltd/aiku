<script setup lang="ts">
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft, faChevronRight } from "@fal"
import { faSpinnerThird } from "@fad"
import { faCircle } from "@fas"
import { trans } from "laravel-vue-i18n"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import type { Navigation } from "@/types/Tabs"

library.add(faChevronLeft, faChevronRight, faSpinnerThird, faCircle)

const props = defineProps<{
    navigation: Navigation
    current: string | number
}>()

const emits = defineEmits<{
    (e: "update:tab", value: string): void
}>()

const layout = inject("layout", layoutStructure)
const locale = inject("locale", aikuLocaleStructure)

const scrollTolerance = 2
const scrollStepRatio = 0.7
const fallbackAccentColor = "#4f46e5"

const _scroller = ref<HTMLElement | null>(null)
const currentTab = ref<string | number>(props.current)
const loadingTab = ref<string | null>(null)
const canScrollLeft = ref(false)
const canScrollRight = ref(false)

const accentColor = computed(() => layout.app?.theme?.[0] ?? fallbackAccentColor)

const orderedTabs = computed(() => {
    const tabs = Object.entries(props.navigation ?? {}).map(([slug, tab]) => ({ slug, tab }))

    return [
        ...tabs.filter(({ tab }) => tab.align !== "right"),
        ...tabs.filter(({ tab }) => tab.align === "right"),
    ]
})

const updateScrollIndicators = () => {
    const scroller = _scroller.value
    if (!scroller) {
        return
    }

    canScrollLeft.value = scroller.scrollLeft > scrollTolerance
    canScrollRight.value = scroller.scrollLeft + scroller.clientWidth < scroller.scrollWidth - scrollTolerance
}

const scrollTabs = (direction: 1 | -1) => {
    const scroller = _scroller.value
    if (!scroller) {
        return
    }

    scroller.scrollBy({ left: direction * scroller.clientWidth * scrollStepRatio, behavior: "smooth" })
}

const revealCurrentTab = async () => {
    await nextTick()
    _scroller.value?.querySelector('[aria-current="page"]')?.scrollIntoView({ block: "nearest", inline: "center" })
    updateScrollIndicators()
}

const onChangeTab = (tabSlug: string) => {
    if (tabSlug === currentTab.value) {
        return
    }

    loadingTab.value = tabSlug
    emits("update:tab", tabSlug)
}

watch(() => props.current, (newCurrent) => {
    currentTab.value = newCurrent
    loadingTab.value = null
    revealCurrentTab()
})

watch(() => props.navigation, revealCurrentTab)

onMounted(() => {
    window.addEventListener("resize", updateScrollIndicators)
    revealCurrentTab()
})

onBeforeUnmount(() => {
    window.removeEventListener("resize", updateScrollIndicators)
})
</script>

<template>
    <div class="relative border-b border-gray-200" :style="{ '--tabs-accent': accentColor }">
        <button v-if="canScrollLeft" type="button" @click="scrollTabs(-1)" :aria-label="trans('Scroll tabs left')"
            class="absolute left-0 inset-y-0 z-10 flex w-10 items-center justify-start pl-2 text-gray-500 bg-gradient-to-r from-white via-white/90 to-transparent">
            <FontAwesomeIcon icon="fal fa-chevron-left" class="text-sm" fixed-width aria-hidden="true" />
        </button>

        <button v-if="canScrollRight" type="button" @click="scrollTabs(1)" :aria-label="trans('Scroll tabs right')"
            class="absolute right-0 inset-y-0 z-10 flex w-10 items-center justify-end pr-2 text-gray-500 bg-gradient-to-l from-white via-white/90 to-transparent">
            <FontAwesomeIcon icon="fal fa-chevron-right" class="text-sm" fixed-width aria-hidden="true" />
        </button>

        <nav ref="_scroller" @scroll.passive="updateScrollIndicators" aria-label="Tabs"
            class="tabsScroller -mb-px flex gap-x-6 overflow-x-auto overflow-y-hidden px-4 whitespace-nowrap">
            <button v-for="{ slug, tab } in orderedTabs" :key="slug" type="button" @click="onChangeTab(slug)"
                :aria-current="slug === currentTab ? 'page' : undefined"
                v-tooltip="tab.type === 'icon' ? tab.title : undefined"
                class="relative flex shrink-0 items-center gap-x-2 border-b-2 px-1 py-2 text-sm font-medium transition-colors md:text-base focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                :class="[
                    slug === currentTab ? 'tabActive' : 'tabIdle',
                    tab.align === 'right' ? 'sm:ml-auto' : '',
                ]">
                <FontAwesomeIcon v-if="loadingTab === slug" icon="fad fa-spinner-third" class="animate-spin h-5 w-5" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else-if="tab.icon" :icon="tab.icon" class="h-5 w-5" fixed-width aria-hidden="true" />

                <span :class="tab.type === 'icon' ? 'sm:sr-only' : ''">{{ tab.title }}</span>

                <span v-if="typeof tab.number === 'number'"
                    class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium tabular-nums text-gray-600">
                    {{ locale.number(tab.number || 0) }}
                </span>

                <FontAwesomeIcon v-if="tab.indicator" icon="fas fa-circle" class="animate-pulse absolute top-2 -right-1 text-blue-500 text-[6px]" fixed-width aria-hidden="true" />
            </button>
        </nav>
    </div>
</template>

<style lang="scss" scoped>
.tabsScroller {
    scrollbar-width: none;

    &::-webkit-scrollbar {
        display: none;
    }
}

.tabIdle {
    border-color: transparent;
    color: #6b7280;

    &:hover {
        border-color: color-mix(in srgb, var(--tabs-accent) 60%, transparent);
        color: color-mix(in srgb, var(--tabs-accent) 80%, black);
    }
}

.tabActive {
    border-color: var(--tabs-accent);
    color: var(--tabs-accent);
}
</style>
