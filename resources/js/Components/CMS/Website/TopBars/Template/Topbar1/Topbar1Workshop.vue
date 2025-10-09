<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { ref } from 'vue'

import SwitchLanguage from "@/Components/Iris/SwitchLanguage.vue"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { getStyles } from '@/Composables/styles'
import { checkVisible, textReplaceVariables, dummyIrisVariables } from '@/Composables/Workshop'
import Button from "@/Components/Elements/Buttons/Button.vue"

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
        id="top_bar_1_workshop"
        class="py-1 px-4 flex flex-col md:flex-row md:justify-between gap-x-4"
         :style="{
			...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
			  margin : 0,
			...getStyles(model.container?.properties, screenType)
		}"
    >
        <div class="flex-shrink flex flex-col md:flex-row items-center justify-between w-full hover-dashed"  @click="()=> emits('setPanelActive', 'main_title')">
            <!-- Section: Main title -->
            <div
                v-if="checkVisible(model?.main_title?.visible || null, isLoggedIn) && textReplaceVariables(model?.main_title?.text, dummyIrisVariables)"
                class="text-center flex items-center"
                v-html="textReplaceVariables(model?.main_title?.text, dummyIrisVariables)"
            />
        </div>


        <div class="action_buttons flex justify-between md:justify-start items-center gap-x-1 flex-wrap md:flex-nowrap">
           <!--  <SwitchLanguage /> -->
            <!-- Section: My account -->
            <Button type="transparent"
                v-if="checkVisible(model?.profile?.visible || null, isLoggedIn) && layout.retina?.type == 'dropshipping'"
                v-tooltip="trans('My account')" url="/app/dashboard">
                <template #label>
                    <span class="text-white"> {{ trans('My account') }}</span>
                </template>
            </Button>

            <!-- Section: Profile -->
            <Button v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                v-tooltip="trans('Profile')"  icon="fal fa-user" type="transparent">
                 <template #icon>
                    <FontAwesomeIcon icon="fal fa-user" :style="{ color: 'white' }" fixed-width
                        aria-hidden="true" />
                </template>
                <template #label>
                    <span class="text-white"
                        v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
                </template>
            </Button>

            <!-- Section: Favourite -->
            <Button
                v-if="checkVisible(model?.favourite?.visible || null, isLoggedIn) && layout.retina?.type !== 'dropshipping'"
                v-tooltip="trans('Favourites')"  icon="fal fa-heart" :type="'transparent'">
                 <template #icon>
                    <FontAwesomeIcon icon="fal fa-heart" :style="{ color: 'white' }" fixed-width
                        aria-hidden="true" />
                </template>
                <template #label>
                    <span v-if="model?.favourite?.text === `{{ favourites_count }}`"
                        v-html="textReplaceVariables(model?.favourite?.text, layout.iris_variables)" />
                    <span v-else-if="model?.favourite?.text === `{{ favourites_count }} favourites`">
                        {{ layout.iris_variables?.favourites_count }} {{ layout.iris_variables?.favourites_count > 1 ?
                            trans("favourites") : trans("favourite") }}
                    </span>
                </template>
            </Button>


            <!-- Section: Basket (cart) -->
            <Button
                v-if="checkVisible(model?.cart?.visible || null, isLoggedIn) && layout.retina?.type == 'b2b'"
                 icon="fal fa-shopping-cart">
                <template #label>
                    <span v-html="textReplaceVariables(model?.cart?.text, layout.iris_variables)" />
                </template>
            </Button>

            <!-- Section: Register -->
            <Button v-if="checkVisible(model?.register?.visible || null, isLoggedIn)" 
                icon="fal fa-user-plus" type="transparent">
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-user-plus" :style="{ color: 'white' }" fixed-width
                        aria-hidden="true" />
                </template>

                <template #label>

                    <span class="text-white">
                        {{ trans("Register") }}
                    </span>
                </template>
            </Button>

            <!-- Section: Login -->
            <Button v-if="checkVisible(model?.login?.visible || null, isLoggedIn)" 
                icon="fal fa-sign-in" type="transparent" >
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-sign-in" :style="{ color: 'white' }" fixed-width
                        aria-hidden="true" />
                </template>
                <template #label>
                    <span class="text-white">
                        {{ trans("Login") }}
                    </span>
                </template>
            </Button>

            <!-- Section: Logout -->
            <Button v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)" 
                 icon="fal fa-sign-out" type="transparent">
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-sign-out" :style="{ color: 'white' }" fixed-width
                        aria-hidden="true" />
                </template>
                <template #label>
                    <span class="text-white">
                        {{ trans("Logout") }}
                    </span>
                </template>
            </Button>
        </div>
    </div>
</template>

<style>



</style>
