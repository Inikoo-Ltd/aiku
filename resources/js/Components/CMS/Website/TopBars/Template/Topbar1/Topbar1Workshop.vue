<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { defineExpose, ref } from 'vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { getStyles } from '@/Composables/styles'
import { checkVisible, textReplaceVariables, dummyIrisVariables } from '@/Composables/Workshop'
import { iframeToParent } from '@/Composables/Workshop'
import { sendMessageToParent } from '@/Composables/Workshop'

library.add(faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus)

interface ModelTopbar1 {
    greeting: {
        text: string
    }
    main_title: {
        text: string
        visible: string // 'all'
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

const emits = defineEmits<{
    (e: 'setPanelActive', value: string | number): void
}>()

const model = defineModel<ModelTopbar1>()
const active = ref()

const isLoggedIn = inject('isPreviewLoggedIn', false)


const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', {})


</script>

<template>
    <div
        id="top_bar"
        class="py-1 px-4 flex flex-col md:flex-row md:justify-between gap-x-4"
        :style="getStyles(model?.container.properties)"
    >
        <div class="flex-shrink flex flex-col md:flex-row items-center justify-between w-full hover-dashed"  @click="()=> emits('setPanelActive', 'main_title')">
            <!-- Section: greeting -->
           <!--  <div
                v-if="checkVisible(model?.greeting?.visible || null, isLoggedIn) && textReplaceVariables(model?.greeting?.text, layout.iris_variables)"
                class="flex items-center"
                v-html="textReplaceVariables(model?.greeting?.text, layout.iris_variables)"

            /> -->
            
            <!-- Section: Main title -->
            <div
                v-if="checkVisible(model?.main_title?.visible || null, isLoggedIn) && textReplaceVariables(model?.main_title?.text, dummyIrisVariables)"
                class="text-center flex items-center"
                v-html="textReplaceVariables(model?.main_title?.text, dummyIrisVariables)"
            />
        </div>


        <div class="action_buttons flex justify-between md:justify-start items-center gap-x-1 flex-wrap md:flex-nowrap">

            <!-- Section: Profile -->
            <a v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                id="profile_button"
                class="space-x-1.5 whitespace-nowrap hover-dashed"
                :style="getStyles(model?.profile.container?.properties)"
                 @click="()=> emits('setPanelActive', 'profile')"
            >
                <FontAwesomeIcon icon='fal fa-user' class='' v-tooltip="trans('Profile')" fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.profile?.text, dummyIrisVariables)" />
            </a>

            <!-- Section: Favourite -->
            <a v-if="checkVisible(model?.favourite?.visible || null, isLoggedIn)"
                id="favorites_button"
                class="space-x-1.5 whitespace-nowrap hover-dashed"
                :style="getStyles(model?.favourite.container?.properties)"
                @click="()=> emits('setPanelActive', 'favourite')"
            >
                <FontAwesomeIcon icon='fal fa-heart' class='' fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.favourite?.text, dummyIrisVariables)" />
            </a>

            <!-- Section: Cart -->
            <a v-if="checkVisible(model?.cart?.visible || null, isLoggedIn)"
                id="header_order_totals"
                class="space-x-1.5 flex items-center whitespace-nowrap hover-dashed"
                :style="getStyles(model?.cart.container?.properties)"
                @click="()=> emits('setPanelActive', 'cart')"
            >
                <FontAwesomeIcon icon='fal fa-shopping-cart' class='text-base px-[5px]' v-tooltip="trans('Basket')" fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.cart?.text, dummyIrisVariables)" />
            </a>

            <!-- Section: Login -->
             <span @click="()=> emits('setPanelActive', 'login')" class="hover-dashed">
                <a v-if="checkVisible(model?.login?.visible || null, isLoggedIn)"
                    class="space-x-1.5 cursor-pointer whitespace-nowrap"
                    id=""
                    :style="getStyles(model?.login?.container?.properties)"
                >
                    <FontAwesomeIcon icon='fal fa-sign-in' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.login?.text, dummyIrisVariables)" />
                </a>
             </span>


            <!-- Section: Register -->
            <span @click="()=> emits('setPanelActive', 'register')" class="hover-dashed">
                <a v-if="checkVisible(model?.register?.visible || null, isLoggedIn)"
                    class="space-x-1.5 cursor-pointer whitespace-nowrap hover-dashed"
                    :style="getStyles(model?.register.container?.properties)"
                >
                    <FontAwesomeIcon icon='fal fa-user-plus' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.register.text, dummyIrisVariables)" />
                </a>
            </span>

            <!-- Section: LogoutRetina -->
            <a v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                
                class="space-x-1.5 whitespace-nowrap hover-dashed"
                :style="getStyles(model?.logout.container?.properties)"
                @click="()=> emits('setPanelActive', 'logout')"
            >
                <FontAwesomeIcon icon='fal fa-sign-out' v-tooltip="trans('Log out')" class='' fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.logout?.text, dummyIrisVariables)" />
            </a>
        </div>
    </div>
</template>

<style>



</style>
