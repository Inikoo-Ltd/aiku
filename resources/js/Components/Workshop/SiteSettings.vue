<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, provide } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'

import { blueprint } from '@/Components/Workshop/BlueprintSiteSettings'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { Root, Daum } from '@/types/webBlockTypes'
import { Root as RootWebpage } from '@/types/webpageTypes'


const props = defineProps<{
    webpage: RootWebpage
    webBlockTypes: Root
}>()

const value = ref({
    button: {
        container: {
            properties: {
                text: {
                    color: "rgba(0, 0, 0, 1)",
                    fontFamily: null
                },
                background: {
                    type: "color",
                    color: "#ffffff",
                    image: {
                        original: null
                    }
                },
                padding: {
                    unit: "px",
                    top: { value: 10 },
                    left: { value: 10 },
                    right: { value: 10 },
                    bottom: { value: 10 }
                },
                margin: {
                    unit: "px",
                    top: { value: 30 },
                    left: { value: 0 },
                    right: { value: 0 },
                    bottom: { value: 0 }
                },
                border: {
                    color: "#000000",
                    unit: "px",
                    rounded: {
                        unit: "px",
                        topright: { value: 10 },
                        topleft: { value: 10 },
                        bottomright: { value: 10 },
                        bottomleft: { value: 10 }
                    },
                    top: { value: 0 },
                    left: { value: 0 },
                    right: { value: 0 },
                    bottom: { value: 0 }
                }
            }
        }
    }
})

console.log(props.webpage)

const onSaveWorkshopFromId = (blockId: number, from?: string) => {
    console.log(blockId, from, value.value)
};
provide("onSaveWorkshopFromId", onSaveWorkshopFromId);
</script>

<template>
    <SideEditor v-model="value" :blueprint="blueprint" />
</template>

<style scoped></style>