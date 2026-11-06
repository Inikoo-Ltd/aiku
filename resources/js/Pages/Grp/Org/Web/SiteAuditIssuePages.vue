<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Head, router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTasks } from "@fal"

library.add(faTasks)
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SeoExportButton from "@/Components/Seo/SeoExportButton.vue"
import TableSiteAuditIssuePages from "@/Components/Tables/Grp/Org/Web/TableSiteAuditIssuePages.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    askToFixRoute: routeType | null
    openTask: number | null
    title: string
    pageHead: PageHeadingTypes
    issueType: string
    websiteSlug: string
    severity: string
    description: string
    data: object
}>()

const isAsking = ref(false)
const askError = ref("")

const askToFix = () => {
    router.post(route(props.askToFixRoute!.name, props.askToFixRoute!.parameters), {}, {
        preserveScroll: true,
        onStart: () => { isAsking.value = true; askError.value = "" },
        onError: (errors) => askError.value = Object.values(errors).join(" "),
        onFinish: () => isAsking.value = false,
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <SeoExportButton table="site_audit_issue_pages" :extra="{ issue_type: issueType }" />
            <Button
                v-if="askToFixRoute"
                type="secondary"
                icon="fal fa-tasks"
                :label="openTask ? ctrans('Remind webmasters') : ctrans('Ask webmasters to fix')"
                :tooltip="openTask ? ctrans('A task is open for this issue. This posts a reminder in it.') : ctrans('Gives the webmasters a task with the pages that have this issue. The next audit that finds none closes it.')"
                :loading="isAsking"
                @click="askToFix" />
        </template>
    </PageHeading>

    <p class="mx-4 mb-4 mt-4 max-w-3xl text-sm text-gray-600">{{ description }}</p>
    <p v-if="askError" class="mx-4 mb-4 text-sm text-red-700">{{ askError }}</p>

    <TableSiteAuditIssuePages :data="data" :issueType="issueType" :websiteSlug="websiteSlug" />
</template>
