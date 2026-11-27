<script setup lang="ts">
import { inject } from "vue"
import WebsiteLanguageSwitch from "@/Components/Utils/WebsiteLanguageSwitch.vue"

const layout = inject("layout", {}) as any
</script>

<template>
    <WebsiteLanguageSwitch
        v-if="(layout.iris?.website_i18n?.enabled_languages?.length ?? 0) > 1"
        :languages="layout.iris.website_i18n.enabled_languages"
        :currentCode="layout.iris.locale"
        :shopLanguageCode="layout.iris.website_i18n.shop_language?.code"
        :saveToProfileUrl="layout.iris?.is_logged_in ? '/app/models/profile' : null"
        keepInUrl
        inTopBar
    />
</template>
