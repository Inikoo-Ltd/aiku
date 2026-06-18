<script setup lang="ts">
import { inject, ref, computed} from "vue"
import { getStyles } from "@/Composables/styles"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

interface ModelTopbar2 {
    main_title?: {
        text: string
        visible?: boolean
    }
    container?: {
        properties?: any
    }
    information?: {
        text1: string
        text2: string
        text3: string
        text4: string
    }
}

const model = defineModel<ModelTopbar2>()

const isLoggedIn = inject<boolean>('isPreviewLoggedIn', false)

const locale = inject('locale', aikuLocaleStructure)
const layout = inject<Record<string, any> | null>('layout', null)
const onLogout = inject<() => void | undefined>('onLogout')




const screenType = inject("screenType", "desktop")
const informationItems = computed(() => {
    const items = [
        model.value?.information?.text1,
        model.value?.information?.text2,
        model.value?.information?.text3,
        model.value?.information?.text4,
    ]

    return items.filter((item): item is string => typeof item === 'string' && item.trim().length > 0)
})



</script>
<template>
    <div id="top_bar_2_iris" class="
        py-2 px-4

        flex flex-col gap-2

        md:grid md:grid-cols-5
        md:items-center
    " :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        margin: 0,
        ...getStyles(model.container?.properties, screenType)
    }">
        <!-- Left -->
        <div class="
            col-span-2

            flex justify-center
            md:justify-start

            text-center
            md:text-left

            text-[10px]
            lg:text-[11px]
            2xl:text-xs

            leading-relaxed
        " v-html="model.main_title?.text">

        </div>

        <!-- Center -->
        <div class="
            hidden
            md:flex

            justify-center
            items-center
        ">
        </div>

        <!-- Right -->
        <div class="
        col-span-2
        flex
        flex-wrap
        justify-center
        md:flex-nowrap
        md:justify-end
        text-[10px]
        lg:text-[11px]
        2xl:text-xs
    ">
            <span v-for="(item, index) in informationItems" :key="index" :class="[
                'px-3 whitespace-nowrap',
                index < informationItems.length - 1
                    ? 'border-r border-white/30'
                    : ''
            ]">
                {{ item }}
            </span>
        </div>
    </div>
</template>
