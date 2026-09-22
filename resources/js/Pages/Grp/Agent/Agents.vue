<script setup lang="ts">
import { onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import AgentsTable from "@/Components/Chat/AgentsTable.vue"

defineProps<{
    title: string
    pageHeading: object
    data: any
    organisationSlug?: string
    routes?: {
        delete?: string
        restore?: string
        force_delete?: string
    }
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
