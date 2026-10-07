<script setup lang="ts">
import { onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AgentsTable from "@/Components/Chat/AgentsTable.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHeadset } from "@fal"
library.add(faHeadset)

defineProps<{
    title: string
    pageHeading: object
    data: any
    organisationSlug?: string
}>()

const waitEchoReady = (callback: Function) => {
    if (window.Echo?.connector?.pusher) {
        callback()
        return
    }
    const interval = setInterval(() => {
        if (window.Echo?.connector?.pusher) {
            clearInterval(interval)
            callback()
        }
    }, 300)
}

onMounted(() => {
    waitEchoReady(() => {
        window.Echo.join("chat-list").listen(".chatlist", () => {
            router.reload({ only: ["data"] })
        })
    })
})
</script>

<template>
    <Head :title="title" />
    <PageHeading v-if="pageHeading?.title" :data="pageHeading" />

    <AgentsTable :data="data" name="agents" />
</template>
