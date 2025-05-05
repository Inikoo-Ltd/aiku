<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { getStyles } from '@/Composables/styles'
import { checkVisible, textReplaceVariables, dummyIrisVariables } from '@/Composables/Workshop'

library.add(faHeart, faShoppingCart, faSignOut, faUser, faSignIn, faUserPlus)

interface ModelTopbar1 {
    greeting: {
        text?: string
        visible: string
    }
    main_title: {
        text?: string
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
        text?: string
        visible: string
        link: string
        container: {
            properties: {
                color: {

                }
                background: {

                }
            }
        }
    }
    login: {
        text?: string
        visible: string
        link: string
        container: {
            properties: {
                color: {

                }
                background: {

                }
            }
        }
    }
    register: {
        text?: string
        visible: string
        link: string
        container: {
            properties: {
                color: {

                }
                background: {

                }
            }
        }
    }
    favourite: {
        text?: string
        visible: string
        link: string
        container: {
            properties: {
                color: {

                }
                background: {

                }
            }
        }
    }
    cart: {
        text?: string
        visible: string
        link: string
        container: {
            properties: {
                color: {

                }
                background: {

                }
            }
        }
    }
    profile: {
        text?: string
        visible: string
        link: string
        container: {
            properties: {
                color: {

                }
                background: {

                }
            }
        }
    }
}

const model = defineModel<ModelTopbar1>()

const isLoggedIn = inject('isPreviewLoggedIn', false)

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', {})


const emits = defineEmits<{
    (e: 'setPanelActive', value: string | number): void
}>()



</script>

<template>
    <div id="top_bar" class="py-2 px-4 flex justify-between"
        :style="getStyles(model?.container.properties)"
    >
        <div class="flex gap-x-2">
            <!-- Section: Profile -->
            <a v-if="checkVisible(model?.profile?.visible || null, isLoggedIn)"
                id="profile_button"
                class="space-x-1.5 hover-dashed"
                :style="getStyles(model?.profile.container.properties)"
                @click="()=> emits('setPanelActive', 'profile')"
            >
                <!-- <i class="far fa-user fa-flip-horizontal  " title="Profile" aria-hidden="true"></i> -->
                <FontAwesomeIcon icon='fal fa-user' class='' v-tooltip="trans('Profile')" fixed-width
                    aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.profile?.text, dummyIrisVariables)"></span>
            </a>

            <!-- Section: LogoutRetina -->
            <a v-if="checkVisible(model?.logout?.visible || null, isLoggedIn)"
                class="space-x-1.5 hover-dashed"
                :style="getStyles(model?.logout.container.properties)"
                @click="()=> emits('setPanelActive', 'logout')"
            >
                <!-- <i class="far fa-flip-horizontal fa-sign-out" title="Log out" aria-hidden="true"></i> -->
                <FontAwesomeIcon icon='fal fa-sign-out' v-tooltip="trans('Log out')" class='' fixed-width
                    aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.logout?.text, iris_variables)" />
            </a>

            <!-- Login -->
            <div class="hover-dashed"
                @click="()=> emits('setPanelActive', 'login')"
            >
                <a v-if="checkVisible(model?.login.visible || null, isLoggedIn)" 
                    class="space-x-1.5 cursor-pointer"
                    id=""
                    :style="getStyles(model?.login.container.properties)"

                >
                    <FontAwesomeIcon icon='fal fa-sign-in' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.login?.text, dummyIrisVariables)" />
                </a>
            </div>

            <!-- Register -->
            <div class="hover-dashed"
                @click="()=> emits('setPanelActive', 'register')"
            >
                <a v-if="checkVisible(model?.register.visible || null, isLoggedIn)" 
                    class="space-x-1.5 cursor-pointer "
                    id=""
                    :style="getStyles(model?.register.container.properties)"
                >
                    <FontAwesomeIcon icon='fal fa-user-plus' class='' fixed-width aria-hidden='true' />
                    <span v-html="textReplaceVariables(model?.register?.text, dummyIrisVariables)" />
                </a>
            </div>

        </div>

        <!-- Section: Main title -->
        <div @click="()=> emits('setPanelActive', 'main_title')" v-if="checkVisible(model?.main_title.visible || null, isLoggedIn)" class="text-center flex items-center hover-dashed" v-html="model.main_title.text">
        </div>

        <div class="action_buttons" style="display: flex; justify-content: flex-end; column-gap: 5px; grid-column: span 5 / span 5">

            <!-- Section: Favourites -->
            <a v-if="checkVisible(model?.favourite?.visible || null, isLoggedIn)"
                id="favorites_button"
                class="mx-0 space-x-1.5 hover-dashed"
                :style="getStyles(model?.favourite.container.properties)"
                @click="()=> emits('setPanelActive', 'favourite')"
            >
                <FontAwesomeIcon icon='fal fa-heart' class='' fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.favourite?.text, dummyIrisVariables)"></span>
            </a>

            <!-- Section: Cart -->
            <a v-if="checkVisible(model?.cart?.visible || null, isLoggedIn)"
                id="header_order_totals"
                class="space-x-1.5 hover-dashed"
                :style="getStyles(model?.cart.container.properties)"
                @click="()=> emits('setPanelActive', 'cart')"
            >
                <FontAwesomeIcon icon='fal fa-shopping-cart' class='text-base px-[5px]' v-tooltip="trans('Cart')"
                    fixed-width aria-hidden='true' />
                <span v-html="textReplaceVariables(model?.cart?.text, dummyIrisVariables)"></span>
            </a>


            <!-- <div @click="() => onClickRegister()" href="/register.sys" class="space-x-1.5">
                <FontAwesomeIcon icon='fal fa-user-plus' class='' fixed-width aria-hidden='true' />
                <span>Register</span>
            </div> -->

        </div>
    </div>

    <!-- <pre>{{model?.register}}</pre>

    ========== -->

</template>
