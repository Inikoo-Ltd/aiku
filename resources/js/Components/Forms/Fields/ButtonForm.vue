<script setup lang="ts">


import { ref } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAsterisk } from "@fas"
import { faBroadcastTower } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Icon from '@/Components/Icon.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import { routeType } from '@/types/route'
import { faPowerOff } from '@far'
library.add(faAsterisk, faBroadcastTower,faPowerOff)

// const props = defineProps(['form', 'fieldName', 'fieldData'])
const props = defineProps<{
    form: any,
    fieldName: string,
    fieldData: {
        label_button: string,
        icon: string,
        type_button: string
        route: routeType
    }

}>()



</script>

<template>
    <ButtonWithLink :icon="fieldData.icon" :label="fieldData.label_button" :type="fieldData.type_button" :routeTarget="fieldData.route" />
</template>
