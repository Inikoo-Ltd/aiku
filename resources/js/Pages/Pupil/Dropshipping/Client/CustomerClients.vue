    <!--
    -  Author: Raul Perusquia <raul@inikoo.com>
    -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
    -  Copyright (c) 2022, Raul A Perusquia Flores
    -->

    <script setup  lang="ts">
    import {Head} from '@inertiajs/vue3';
    import PageHeading from '@/Components/Headings/PageHeading.vue';
    import TableCustomerClients from '@/Components/Tables/Grp/Org/CRM/TableCustomerClients.vue';
    import { capitalize } from "@/Composables/capitalize"
    import PupilTableCustomerClients from "@/Components/Tables/Retina/PupilTableCustomerClients.vue";

    const props = defineProps<{
        data: {}
        title: string
        pageHead: {}
    }>()

    </script>

    <template>
        <Head :title="capitalize(title)"/>
        <PageHeading :data="pageHead"></PageHeading>
        <PupilTableCustomerClients :data="data"  />
    </template>

