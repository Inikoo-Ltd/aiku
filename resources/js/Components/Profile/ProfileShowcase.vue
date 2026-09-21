<script setup lang='ts'>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faSlidersH, faChevronDown } from '@fal'
import { trans } from 'laravel-vue-i18n'
import { computed, defineAsyncComponent, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'

library.add(faSlidersH, faChevronDown)

const props = defineProps<{
    layoutVersion?: number
}>()

const EditProfile = defineAsyncComponent(() => import('@/Pages/Grp/EditProfile.vue'))

const layout = inject('layout', layoutStructure)

const fallbackAccentColor = '#4f46e5'
const desktopBreakpoint = 1024
const stackedPanelBottomPadding = 24
const cardBottomMargin = 24
const minimumCardHeight = 360

const accentColor = computed(() => layout.app?.theme?.[0] ?? fallbackAccentColor)

const _card = ref<HTMLElement | null>(null)
const isSettingsOpen = ref(false)
const hasSettingsBeenOpened = ref(false)
const cardHeight = ref<string>('auto')

const fitToViewport = () => {
    if (!_card.value || !isSettingsOpen.value || window.innerWidth < desktopBreakpoint) {
        cardHeight.value = 'auto'
        return
    }

    const available = window.innerHeight - _card.value.getBoundingClientRect().top - stackedPanelBottomPadding - cardBottomMargin
    cardHeight.value = `${Math.max(minimumCardHeight, available)}px`
}

const toggleSettings = async () => {
    isSettingsOpen.value = !isSettingsOpen.value
    if (isSettingsOpen.value) {
        hasSettingsBeenOpened.value = true
    }

    await nextTick()
    fitToViewport()
}

watch(() => props.layoutVersion, async () => {
    await nextTick()
    fitToViewport()
})

onMounted(() => {
    window.addEventListener('resize', fitToViewport)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', fitToViewport)
})
</script>

<template>
    <div class="px-4 pt-6 pb-6 sm:px-6">
        <section ref="_card" class="flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200" :style="{ height: cardHeight }">
            <button type="button" @click="toggleSettings" :aria-expanded="isSettingsOpen"
                class="flex w-full shrink-0 items-center justify-between gap-x-4 px-6 py-4 text-left transition-colors hover:bg-gray-50"
                :class="isSettingsOpen ? 'border-b border-gray-200' : ''">
                <span class="flex items-center gap-x-2 text-lg font-semibold text-gray-900">
                    <FontAwesomeIcon icon="fal fa-sliders-h" :style="{ color: accentColor }" fixed-width aria-hidden="true" />
                    {{ trans('Personal settings') }}
                </span>
                <FontAwesomeIcon icon="fal fa-chevron-down" class="text-sm text-gray-400 transition-transform duration-200"
                    :class="isSettingsOpen ? 'rotate-180' : ''" aria-hidden="true" />
            </button>

            <div v-if="hasSettingsBeenOpened" v-show="isSettingsOpen" class="min-h-0 flex-1">
                <EditProfile embedded />
            </div>
        </section>
    </div>
</template>
