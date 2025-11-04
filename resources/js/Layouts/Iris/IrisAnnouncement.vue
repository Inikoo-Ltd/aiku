<script setup lang="ts">
import { getIrisAnnouncementComponent } from "@/Composables/getIrisComponents"
import { routeType } from "@/types/route"
import { inject, provide, computed } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import MobileHeader from "@/Components/CMS/Website/Headers/MobileHeader.vue"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
    data: {
        key: string,
        data: object,
        blueprint: object
        loginRoute?: routeType
    }
}>()


const layout = inject("layout", {})
const isLoggedIn = computed(() => {
    return layout.iris?.user_auth ? true : false
})
provide("isPreviewLoggedIn", isLoggedIn)


</script>

<template>
    <!-- Section: Topbar (login, logout) -->
    <component
        v-if="data?.topBar?.data.fieldValue"
        :is="getIrisAnnouncementComponent(data?.topBar.code)"
        :fieldValue="data.topBar.data.fieldValue"
        v-model="data.topBar.data.fieldValue"
    />


</template>