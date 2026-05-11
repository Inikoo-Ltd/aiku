<script setup lang='ts'>
import { getIrisComponent } from '@/Composables/getIrisComponents'
import { Root } from '@/types/Website/Website/footer1'
import { checkScreenType } from '@/Composables/useWindowSize'
import { computed, inject, onMounted, ref } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { isArray } from 'lodash-es'
import axios from 'axios'

defineProps<{
    colorThemed: object
}>()

const layout = inject('layout', retinaLayoutStructure) as typeof retinaLayoutStructure & {
    iris: {
        footer?: Root | unknown
        isFooterLoaded?: boolean
        isFooterLoading?: boolean
        [key: string]: unknown
    }
}
const screenType = ref<'mobile' | 'tablet' | 'desktop'>('desktop')
const isFetching = ref(false)

const footerData = computed<Root | null>(() => {
    const f = layout.iris?.footer
    if (!f || isArray(f)) return null
    return f as Root
})

const fetchFooterOnce = async () => {
    if (layout.iris?.isFooterLoaded || isFetching.value) return
    isFetching.value = true

    try {
        layout.iris.isFooterLoading = true
        const url = `${window.location.origin}/json/footer`
        const { data } = await axios.get(url)
        layout.iris.footer = data.footer
        layout.iris.isFooterLoaded = true
    } catch (e) {
        console.error('[IrisFooter] fetch failed', e)
    } finally {
        layout.iris.isFooterLoading = false
        isFetching.value = false
    }
}

onMounted(() => {
    screenType.value = checkScreenType()
    void fetchFooterOnce()
})
</script>

<template>
    <div>
        <component
            v-if="footerData"
            :is="getIrisComponent(footerData.code)"
            v-model="footerData.data.fieldValue"
            :keyTemplate="footerData.code"
            :screenType
            :previewMode="true"
            :colorThemed="colorThemed"
        />
        <div
            v-else
            class="w-full h-64 md:h-80 animate-pulse bg-gray-200/40 dark:bg-gray-800/40"
            aria-hidden="true"
        />
    </div>
</template>
