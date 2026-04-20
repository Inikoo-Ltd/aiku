<script setup lang="ts">
import Image1 from "@/Components/Iris/Blocks/Header/Image1.vue"
import Headline1 from "@/Components/Iris/Blocks/Header/Headline1.vue"
import { faSearch, faArrowRight, faChevronDown } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import IrisSearch from "@/Components/Iris/Blocks/Header/IrisSearch.vue"

library.add(faSearch, faArrowRight, faChevronDown)

defineProps<{
    data: {
        name: string
        data: {
            imgLogo?: string
            title?: string
            description?: string
        }
    }[]
}>()
</script>

<template>
    <div class="grid grid-cols-3 h-24 items-center">
        <Image1 :data="data[0].data" />
        <div class="justify-self-center pr-7">
            <IrisSearch />
        </div>
        <Headline1 :data="data[1].data" />
    </div>
</template>