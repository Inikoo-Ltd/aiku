<script setup lang='ts'>
import { getIrisComponent } from '@/Iris/Composables/getIrisComponents'
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
const appVersion = ref<string | null>(null)
const shopName = ref<string | null>(null)

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
        appVersion.value = data.version ?? null
        shopName.value = data.shop_name ?? null
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
        <div v-if="appVersion" class="hidden lg:flex w-full bg-black justify-center items-center h-4">
            <span class="text-slate-400 text-[10px] leading-none tabular-nums">
                <template v-if="shopName">{{ shopName }} · </template>
                <a href="https://aiku.io/" target="_blank" rel="noopener" aria-label="Made with love using aiku.io" class="hover:text-white">{{ appVersion }}</a>
            </span>
        </div>
    </div>
</template>
