<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { defineExpose, ref } from 'vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { getStyles } from '@/Composables/styles'
import { checkVisible, textReplaceVariables } from '@/Composables/Workshop'
import { iframeToParent } from '@/Composables/Workshop'
import { sendMessageToParent } from '@/Composables/Workshop'
import { Link } from '@inertiajs/vue3'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'

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

const model = defineModel<ModelTopbar1>()
const active = ref()

const isLoggedIn = inject('isPreviewLoggedIn', false)
const onLogout = inject('onLogout', true)

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', {})


</script>

<template>
    <div></div>
    <div
        id="top_bar"
        class="py-1 px-4 flex flex-col md:flex-row md:justify-between gap-x-4"
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
                :href="model?.profile?.link?.href || '#'"
                :target="model?.profile?.link?.target"
                class="space-x-1.5 whitespace-nowrap "
                :style="getStyles(model?.profile.container?.properties)"

            >
                <FontAwesomeIcon icon='fal fa-user' class='' v-tooltip="trans('Profile')" fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
            </a>

            <!-- Section: Favourite -->
            <a v-if="checkVisible(model?.favourite?.visible || null, isLoggedIn)"
                id="favorites_button"
                :href="model?.favourite?.link?.href || '#'"
                :target="model?.favourite?.link?.target"
                class="space-x-1.5 whitespace-nowrap "
                :style="getStyles(model?.favourite.container?.properties)"

            >
                <FontAwesomeIcon icon='fal fa-heart' class='' fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.favourite?.text, layout.iris_variables)" />
            </a>


            <!-- Section: Basket -->
            <ButtonWithLink
                v-if="checkVisible(model?.cart?.visible || null, isLoggedIn)"
                url="/app/basket"
                icon="fal fa-shopping-cart"
            >
                <template #label>
                    <span v-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>

            <!-- Section: Login -->
             <span class="">
                <a v-if="checkVisible(model?.login?.visible || null, isLoggedIn)"
                    :href="model?.login?.link?.href || '#'"
                    :target="model?.login?.link?.target"
                    class="space-x-1.5 cursor-pointer whitespace-nowrap"
                    id=""
                    :style="getStyles(model?.login?.container?.properties)"

                >
                    <FontAwesomeIcon icon='fal fa-sign-in' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.login?.text, layout.iris_variables)" />
                </a>
             </span>


            <!-- Section: Register -->
            <span class="">
                <a v-if="checkVisible(model?.register?.visible || null, isLoggedIn)"
                    :href="model?.register?.link?.href || '#'"
                    :target="model?.register?.link?.target"
                    class="space-x-1.5 cursor-pointer whitespace-nowrap "
                    :style="getStyles(model?.register.container?.properties)"

                >
                    <FontAwesomeIcon icon='fal fa-user-plus' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.register.text, layout.iris_variables)" />
                </a>
            </span>

            <!-- Section: Logout -->
            <ButtonWithLink
                v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                url="/app/logout"
                method="post"
                :data="{}"
                icon="fal fa-sign-out"
            >
                <template #label>
                    <span v-html="textReplaceVariables(model?.logout?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>

            <!-- <Link as="a"
                v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                @click="'()=>onLogout()'"
                href="/app/logout"
                method="post"
                :data="{}"
                class="space-x-1.5 whitespace-nowrap "
                :style="getStyles(model?.logout.container?.properties)"

            >
                <FontAwesomeIcon icon='fal fa-sign-out' v-tooltip="trans('Log out')" class='' fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.logout?.text, layout.iris_variables)" />
            </Link> -->
        </div>
    </div>
</template>

<style>



</style>
