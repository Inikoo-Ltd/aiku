<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { getStyles } from '@/Composables/styles'
import { checkVisible, textReplaceVariables } from '@/Composables/Workshop'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'

library.add(faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus)

interface ModelTopbar1 {
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
}

const model = defineModel<ModelTopbar1>()

const isLoggedIn = inject('isPreviewLoggedIn', false)

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', {})

const onLogout = inject('onLogout')
const emits = defineEmits<{
    (e: 'setPanelActive', value: string | number): void
}>()



</script>

<template>
    <div></div>
    <div id="top_bar_3_iris" class="py-2 px-4 justify-between hidden md:flex"
        :style="getStyles(model?.container.properties)"
    >

        <div class="flex gap-x-2">
            <!-- Section: Profile -->
            <!-- <a v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                id="profile_button"
                :href="model?.profile?.link.href"
                :target="model?.profile?.link.target"
                class="space-x-1.5 "
                :style="getStyles(model?.profile.container.properties)"

            >
                <FontAwesomeIcon icon='fal fa-user' class='' v-tooltip="trans('Profile')" fixed-width
                    aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)"></span>
            </a> -->
            <ButtonWithLink
                v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                v-tooltip="trans('Profile')"
                url="/app/profile"
                icon="fal fa-user"
                type="transparent"
            >
                <template #label>
                    <span v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>

            <!-- Section: LogoutRetina -->
            <!-- <a v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                 @click="()=>onLogout(model?.logout?.link)"
                class="space-x-1.5 "
                :style="getStyles(model?.logout.container.properties)"

            >
                <FontAwesomeIcon icon='fal fa-sign-out' v-tooltip="trans('Log out')" class='' fixed-width
                    aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.logout?.text, iris_variables)" />
            </a> -->
            <ButtonWithLink
                v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                url="/app/logout"
                method="post"
                :data="{}"
                icon="fal fa-sign-out"
                :label="trans('Logout')"
                type="negative"
            >
                <!-- <template #label>
                    <span v-html="textReplaceVariables(model?.logout?.text, layout.iris_variables)" />
                </template> -->
            </ButtonWithLink>

            <!-- Login -->
            <!-- <span class="">
                <a v-if="checkVisible(model?.login.visible || null, isLoggedIn)"
                    :href="model?.login?.link.href"
                    :target="model?.login?.link.target"
                    class="space-x-1.5 cursor-pointer"
                    id=""

                    :style="getStyles(model?.login.container.properties)"

                >
                    <FontAwesomeIcon icon='fal fa-sign-in' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.login?.text, layout.iris_variables)" />
                </a>
            </span> -->

            <ButtonWithLink
                v-if="checkVisible(model?.login?.visible || null, isLoggedIn)"
                url="/app/login"
                icon="fal fa-sign-in"
                type="tertiary"
            >
                <template #label>
                    <span v-html="textReplaceVariables(model?.login?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>

            <!-- Register -->
            <!-- <span class="">
            <a v-if="checkVisible(model?.register.visible || null, isLoggedIn)"
                :href="model?.register?.link.href"
                :target="model?.register?.link.target"
                class="space-x-1.5 cursor-pointer "
                id=""
                :style="getStyles(model?.register.container.properties)"

            >
                <FontAwesomeIcon icon='fal fa-user-plus' class='' fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.register?.text, layout.iris_variables)" />
            </a>
            </span> -->
            <ButtonWithLink
                v-if="checkVisible(model?.register?.visible || null, isLoggedIn)"
                url="/app/register"
                icon="fal fa-user-plus"
                type="transparent"
            >
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-user-plus" class="" fixed-width aria-hidden="true" />
                </template>

                <template #label>
                    <span v-html="textReplaceVariables(model?.register.text, layout.iris_variables)" class="" />
                </template>
            </ButtonWithLink>

        </div>

        <!-- Section: Main title -->
        <!-- <div @click="()=> emits('setPanelActive', 'title')" v-if="checkVisible(model?.main_title.visible || null, isLoggedIn)" class="text-center flex items-center " v-html="model.main_title.text">
        </div> -->
        
        <div
            v-if="checkVisible(model?.main_title?.visible || null, isLoggedIn) && textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
            class="text-center flex items-center"
            v-html="textReplaceVariables(model?.main_title?.text, layout.iris_variables)"
        />

        <div class="action_buttons" style="display: flex; justify-content: flex-end; column-gap: 5px; grid-column: span 5 / span 5">

            <!-- Section: Favourites -->
            <!-- <a v-if="checkVisible(model?.favourite?.visible || null, isLoggedIn)"
                id="favorites_button"
                :href="model?.favourite?.link.href"
                :target="model?.favourite?.link.target"
                class="mx-0 space-x-1.5 "
                :style="getStyles(model?.favourite.container.properties)"

            >
                <FontAwesomeIcon icon='fal fa-heart' class='' fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.favourite?.text, layout.iris_variables)"></span>
            </a> -->

            <ButtonWithLink
                v-if="checkVisible(model?.favourite?.visible || null, isLoggedIn) && layout.retina?.type !== 'dropshipping'"
                v-tooltip="trans('Favourites')"
                url="/app/favourites"
                icon="fal fa-heart"
                type="transparent"
            >
                <template #label>
                    <span v-html="textReplaceVariables(model?.favourite?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>

            <!-- Section: Cart -->
            <!-- <a v-if="checkVisible(model?.cart?.visible || null, isLoggedIn)"
                id="header_order_totals"
                :href="model?.cart?.link.href"
                :target="model?.cart?.link.target"
                class="space-x-1.5 "
                :style="getStyles(model?.cart.container.properties)"

            >
                <FontAwesomeIcon icon='fal fa-shopping-cart' class='text-base px-[5px]' v-tooltip="trans('Cart')"
                    fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)"></span>
            </a> -->
            <ButtonWithLink
                v-if="checkVisible(model?.cart?.visible || null, isLoggedIn) && layout.retina?.type == 'b2b'"
                url="/app/basket"
                icon="fal fa-shopping-cart"
                type="transparent"
            >
                <template #label>
                    <span v-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)" />
                </template>
            </ButtonWithLink>


            <!-- <div @click="() => onClickRegister()" href="/register.sys" class="space-x-1.5">
                <FontAwesomeIcon icon='fal fa-user-plus' class='' fixed-width aria-hidden='true' />
                <span>Register</span>
            </div> -->

        </div>
    </div>

    <!-- <pre>{{model?.register}}</pre>

    ========== -->

</template>
