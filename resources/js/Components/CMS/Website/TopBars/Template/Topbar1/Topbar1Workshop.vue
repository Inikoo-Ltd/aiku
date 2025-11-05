<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import { onMounted, ref } from 'vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject, computed, watch } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { getStyles, useDynamicCssVars } from '@/Composables/styles'
import { checkVisible, textReplaceVariables, dummyIrisVariables } from '@/Composables/Workshop'
import Button from "@/Components/Elements/Buttons/Button.vue"

library.add(faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus)

interface ModelTopbar1 {
    screenType?: "mobile" | "tablet" | "desktop"
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

const props = defineProps<{
    screenType: "desktop" | "mobile" | "tablet"
}>()

const emits = defineEmits<{
    (e: 'setPanelActive', value: string | number): void
}>()

const model = defineModel<ModelTopbar1>()
const isLoggedIn = inject('isPreviewLoggedIn', false)
const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', {})



const buttonClass = ref(getStyles(model.value?.button?.container?.properties, props.screenType, false))
const buttonHoverClass = ref(getStyles(model.value?.button?.hover?.container?.properties, props.screenType,false))

watch(
  () => model.value?.button,
  () => {
    buttonClass.value = getStyles(model.value?.button?.container?.properties, props.screenType,false)
    buttonHoverClass.value = getStyles(model.value?.button?.hover?.container?.properties, props.screenType,false)
  },
  { deep: true }
)

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
                    <span class="text-white button"> {{ trans('My account') }}</span>
                </template>
            </Button>

            <!-- Section: Profile -->
            <Button v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                v-tooltip="trans('Profile')"  icon="fal fa-user" type="transparent" class="button">
                 <template #icon>
                    <FontAwesomeIcon icon="fal fa-user" class="button" fixed-width
                        aria-hidden="true" />
                </template>
                <template #label>
                    <span class="text-white button"
                        v-html="textReplaceVariables(model?.profile?.text, layout.iris_variables)" />
                </template>
            </Button>

            <!-- Section: Favourite -->
            <Button
                v-if="checkVisible(model?.favourite?.visible || null, isLoggedIn) && layout.retina?.type !== 'dropshipping'"
                v-tooltip="trans('Favourites')"  icon="fal fa-heart" :type="'transparent'" class="button">
                 <template #icon>
                    <FontAwesomeIcon icon="fal fa-heart" fixed-width
                        aria-hidden="true" class="button"/>
                </template>
                <template #label>
                    <span v-if="model?.favourite?.text === `{{ favourites_count }}`"
                        v-html="textReplaceVariables(model?.favourite?.text, layout.iris_variables)" class="button"/>
                    <span v-else-if="model?.favourite?.text === `{{ favourites_count }} favourites`" class="button">
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
                icon="fal fa-user-plus" type="transparent" class="button">
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-user-plus" class="button" fixed-width
                        aria-hidden="true" />
                </template>

                <template #label>
                    <span class="text-white button">
                        {{ trans("Register") }}
                    </span>
                </template>
            </Button>

            <!-- Section: Login -->
            <Button v-if="checkVisible(model?.login?.visible || null, isLoggedIn)" 
                icon="fal fa-sign-in" type="transparent" class="button" >
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-sign-in" :style="{ color: 'white' }" class="button" fixed-width
                        aria-hidden="true" />
                </template>
                <template #label>
                    <span class="text-white button">
                        {{ trans("Login") }}
                    </span>
                </template>
            </Button>

            <!-- Section: Logout -->
            <Button v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)" 
                 icon="fal fa-sign-out" type="transparent" class="button">
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-sign-out" class="button" fixed-width
                        aria-hidden="true" />
                </template>
                <template #label>
                    <span class="text-white button">
                        {{ trans("Logout") }}
                    </span>
                </template>
            </Button>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.button {
  background: v-bind('buttonClass?.background || null') !important;
  color: v-bind('buttonClass?.color || null') !important;
  font-family: v-bind('buttonClass?.fontFamily || null') !important;
  font-size: v-bind('buttonClass?.fontSize || null') !important;

  &:hover {
    background: v-bind('buttonHoverClass?.background || null') !important;
    color: v-bind('buttonHoverClass?.color || null') !important;
    font-family: v-bind('buttonHoverClass?.fontFamily || null') !important;
    font-size: v-bind('buttonHoverClass?.fontSize || null') !important;
  }
}
</style>
