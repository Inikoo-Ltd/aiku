<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref, computed, inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { getStyles } from '@/Composables/styles'


library.add(faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus)

interface ModelTopbar2 {
    greeting: {
        text: string
        visible: string
    }
    main_title: {
        text: string
        visible: string // 'all'
    }
    infomation: {
        text1: string
        text2: string
        text3: string
        text4: string
    }
    container: {
        properties: {
            color: {

            }
            background: {

            }
        }
    }

}



const model = defineModel<ModelTopbar2>()

const isLoggedIn = inject('isPreviewLoggedIn', false)


const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', {})

const isModalOpen = ref(false)

const emits = defineEmits<{
    (e: 'setPanelActive', value: string | number): void
}>()


const screenType = inject("screenType", "desktop")
const informationItems = computed(() =>
    [
        model.value?.information?.text1,
        model.value?.information?.text2,
        model.value?.information?.text3,
        model.value?.information?.text4,
    ].filter(item => item && item.trim())
)

console.log('informationItems', model.value.information.text1)

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
