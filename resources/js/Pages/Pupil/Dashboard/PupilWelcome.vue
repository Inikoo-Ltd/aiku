<script setup lang='ts'>
import { routeType } from '@/types/route'
import { faSearch, faThLarge, faListUl, faStar as falStar } from '@fal'
import { faStar } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import Intro from '../Intro.vue'
import WelcomeShop from '../WelcomeShop.vue'
library.add(faSearch, faThLarge, faListUl, faStar, falStar)

declare global {
    interface Window {
        sessionToken: string; // or the correct type if it's not a string
    }
}

const props = defineProps<{
    user: {}
    data: {}
    shop: string
    shopUrl: string
    showIntro: boolean
    shops: object
    routes: {
        products: routeType
        store_product: routeType
        get_started: routeType
    }
}>()

</script>

<template>
    <Intro v-if="props.showIntro" :shops :routes/>
    <WelcomeShop v-else-if="props.shop" :shop :shopUrl :data />
</template>
