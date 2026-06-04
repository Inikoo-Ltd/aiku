<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableChatConversations from '@/Components/Tables/Grp/Chat/TableChatConversations.vue'
import { capitalize } from '@/Composables/capitalize'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faComments } from '@fal'
import { PageHeadingTypes } from '@/types/PageHeading'

library.add(faComments)

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TableChatConversations :data="data" />
</template>
