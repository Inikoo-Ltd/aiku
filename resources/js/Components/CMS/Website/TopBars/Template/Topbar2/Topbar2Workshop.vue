<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref } from 'vue'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { getStyles } from '@/Composables/styles'
import { checkVisible, textReplaceVariables, dummyIrisVariables } from '@/Composables/Workshop'
import Image from '@/Components/Image.vue'

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
    container: {
        properties: {
            color: {

            }
            background: {

            }
        }
    }
    logout: {
        text: string
        visible: string
        link: string
    }
    login: {

    }
    register: {

    }
    favourite: {

    }
    cart: {

    }
    profile: {

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



</script>

<template>
    <div id="top_bar" class="py-2 px-4 grid md:grid-cols-5"
        :style="getStyles(model?.container?.properties)"
    >

        <!-- Section: Greeting -->
        <!-- <div v-if="checkVisible(model?.greeting.visible || null, isLoggedIn)" v-html="textReplaceVariables(model?.greeting?.text, layout.iris_variables)" class="flex items-center" /> -->

        <!-- Section: Main title -->
        <!-- <div v-if="checkVisible(model?.main_title.visible || null, isLoggedIn)" class="text-center flex items-center" v-html="textReplaceVariables(model.main_title.text, layout.iris_variables)" /> -->

        <div class="md:col-span-2 action_buttons flex justify-center md:justify-start ">
            <!-- Section: Profile -->
            <a v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                id="profile_button"
                class="hidden space-x-1.5 md:flex flex-nowrap items-center hover-dashed"
                :style="getStyles(model?.profile.container?.properties)"
                 @click="()=> emits('setPanelActive', 'profile')"
            >
                <!-- <i class="far fa-user fa-flip-horizontal  " title="Profile" aria-hidden="true"></i> -->
                <FontAwesomeIcon icon='fal fa-user' class='' v-tooltip="trans('Profile')" fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.profile?.text, dummyIrisVariables)" />
            </a>

            <!-- Section: Favourites -->
            <a v-if="checkVisible(model?.favourite?.visible || null, isLoggedIn)"
                id="favorites_button"
                class="space-x-1.5 flex flex-nowrap items-center hover-dashed"
                :style="getStyles(model?.favourite.container?.properties)"
                 @click="()=> emits('setPanelActive', 'favourites')"
            >
                <FontAwesomeIcon icon='fal fa-heart' class='' fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.favourite?.text, dummyIrisVariables)" />
            </a>

            <!-- Section: Cart -->
            <a v-if="checkVisible(model?.cart?.visible || null, isLoggedIn)"
                id="header_order_totals"
                class="space-x-1.5 flex flex-nowrap items-center hover-dashed"
                :style="getStyles(model?.cart.container?.properties)"
                @click="()=> emits('setPanelActive', 'cart')"
            >
                <FontAwesomeIcon icon='fal fa-shopping-cart' class='text-base px-[5px]' v-tooltip="trans('Basket')"
                    fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.cart?.text, dummyIrisVariables)" />
            </a>


            <!-- Section: Logged out (Login, Register) -->
            <!-- <template v-if="isDropshipping">
                <a href="/login.sys" class="space-x-1.5" id="">
                    <span>Call us</span>
                </a>
            </template> -->


            <!-- <div @click="() => onClickRegister()" href="/register.sys" class="space-x-1.5">
                <FontAwesomeIcon icon='fal fa-user-plus' class='' fixed-width aria-hidden='true' />
                <span>Register</span>
            </div> -->

        </div>

        <div class="row-start-1 md:row-start-auto grid grid-cols-5 justify-between md:flex md:justify-center items-center">
            <!-- Section: Profile -->
            <a v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                id="profile_button"
                class="col-span-2 md:hidden space-x-1.5 flex flex-nowrap items-center hover-dashed"
                :style="getStyles(model?.profile.container?.properties)"
                @click="()=> emits('setPanelActive', 'profile')"
            >
                <!-- <i class="far fa-user fa-flip-horizontal  " title="Profile" aria-hidden="true"></i> -->
                <FontAwesomeIcon icon='fal fa-user' class='' v-tooltip="trans('Profile')" fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.profile?.text, dummyIrisVariables)" />
            </a>

            <Image
                class="h-9 max-w-32 hover-dashed"
                :src="model?.logo?.source"
                imageCover
                @click="()=> emits('setPanelActive', 'logo')"
            />

            <!-- Section: LogoutRetina -->
            <a v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                class="col-span-2 text-right block md:hidden space-x-1.5 hover-dashed"
                :style="getStyles(model?.logout.container?.properties)"
                @click="()=> emits('setPanelActive', 'logout')"
            >
                <FontAwesomeIcon icon='fal fa-sign-out' v-tooltip="trans('Log out')" class='' fixed-width aria-hidden='true' />
                <span class="" v-html="textReplaceVariables(model?.logout?.text, dummyIrisVariables)" />
            </a>
        </div>

        <div class="md:col-span-2 flex md:justify-end gap-x-4 ">
            <!-- Section: LogoutRetina -->
            <a v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                class="hidden md:block space-x-1.5 hover-dashed"
                :style="getStyles(model?.logout.container?.properties)"
                @click="()=> emits('setPanelActive', 'logout')"
            >
                <FontAwesomeIcon icon='fal fa-sign-out' v-tooltip="trans('Log out')" class='' fixed-width aria-hidden='true' />
                <span class="hidden md:inline" v-html="textReplaceVariables(model?.logout?.text, dummyIrisVariables)" />
            </a>

            <!-- Register -->
             <span class="hover-dashed">
                <a v-if="checkVisible(model?.register?.visible || null, isLoggedIn)"
                    class="space-x-1.5 cursor-pointer"
                    id=""
                    :style="getStyles(model?.register.container?.properties)"
                    @click="()=> emits('setPanelActive', 'register')"

                >
                    <FontAwesomeIcon icon='fal fa-user-plus' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.register.text, dummyIrisVariables)" />
                </a>
             </span>


            <!-- Login -->
            <span class="hover-dashed">
                <a
                    v-if="checkVisible(model?.login?.visible || null, isLoggedIn)"
                    class="space-x-1.5 cursor-pointer"
                    id=""
                    :style="getStyles(model?.login?.container?.properties)"
                    @click="()=> emits('setPanelActive', 'login')"
                >
                    <FontAwesomeIcon icon='fal fa-sign-in' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.login?.text, dummyIrisVariables)" />
                </a>
            </span>


        </div>
    </div>

    <!-- <pre>{{model?.register}}</pre>

    ========== -->


</template>
