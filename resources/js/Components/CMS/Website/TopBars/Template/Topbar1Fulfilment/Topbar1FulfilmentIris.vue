<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'
import { getStyles } from '@/Composables/styles'
import { checkVisible, textReplaceVariables } from '@/Composables/Workshop'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSignIn, faHeart, faShoppingCart, faSignOut, faUser, faUserPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'

import {TopbarFulfilmentTypes} from '@/types/TopbarFulfilment'

library.add(faSignIn, faHeart, faShoppingCart, faSignOut, faUser, faUserPlus)

const model = defineModel<TopbarFulfilmentTypes>()
const isLoggedIn = inject('isPreviewLoggedIn', false)

const onLogout = inject('onLogout')
const layout = inject('layout', {})


</script>

<template>
    <div></div>
    <div
        id="top_bar"
        class="py-1 px-4 flex flex-col md:flex-row md:justify-between gap-x-4 hidden md:flex"
        :style="getStyles(model?.container.properties)"
    >
        <div class="flex-shrink flex flex-col md:flex-row items-center justify-between w-full "  >
            <!-- Section: Main title -->
            <div
                v-if="checkVisible(model?.main_title?.visible || null, isLoggedIn) && textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
                class="text-center flex items-center"
                v-html="textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
            />
        </div>

        <div class="action_buttons flex justify-between md:justify-start items-center gap-x-1 flex-wrap md:flex-nowrap">

            <!-- Section: Profile -->
            <a v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                id="profile_button"
                  :href="'/app/profile'"
                :target="model?.profile?.link?.target"
                class="space-x-1.5 whitespace-nowrap flex flex-nowrap items-center "
                :style="getStyles(model?.profile.container?.properties)"

            >
                <FontAwesomeIcon icon='fal fa-user' class='' v-tooltip="trans('Profile')" fixed-width aria-hidden='true' />
                <div v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
            </a>


            <!-- Section: Login -->
             <span class="">
                <a v-if="checkVisible(model?.login?.visible || null, isLoggedIn)"
                    :href="model?.login?.link?.href"
                    :target="model?.login?.link?.target"
                    class="space-x-1.5 cursor-pointer whitespace-nowrap"
                    id=""
                    :style="getStyles(model?.login?.container?.properties)"

                >
                    <FontAwesomeIcon :icon='faSignIn' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.login?.text, layout.iris_variables)" />
                </a>
             </span>

             <span class="">
                <a v-if="checkVisible(model?.register?.visible || null, isLoggedIn)"
                    :href="model?.register?.link?.href"
                    :target="model?.register?.link?.target"
                    class="space-x-1.5 cursor-pointer whitespace-nowrap "
                    :style="getStyles(model?.register.container?.properties)"

                >
                    <FontAwesomeIcon :icon='faUserPlus' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.register.text, layout.iris_variables)" />
                </a>
            </span>


            <!-- Section: LogoutRetina -->
            <a v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                @click="()=>onLogout()"
                class="space-x-1.5 whitespace-nowrap "
                :style="getStyles(model?.logout.container?.properties)"

            >
                <FontAwesomeIcon :icon='faSignOut' v-tooltip="trans('Log out')" class='' fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.logout?.text, layout.iris_variables)" />
            </a>
        </div>
    </div>
</template>

<style>



</style>
