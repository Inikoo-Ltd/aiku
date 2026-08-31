<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Table from '@/Components/Table/Table.vue'
import Icon from '@/Components/Icon.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Table :resource="data" class="mt-5">
        <template #cell(name)="{ item: campaign }">
            <Link class="primaryLink"
                :href="route('grp.org.shops.show.marketing.whatsapp_campaigns.workshop',
                    [route().params.organisation, route().params.shop, campaign.slug])">
                {{ campaign.name }}
            </Link>
        </template>

        <template #cell(state)="{ item: campaign }">
            <div class="flex justify-center">
                <Icon :data="campaign.state_icon" />
            </div>
        </template>
    </Table>
</template>
