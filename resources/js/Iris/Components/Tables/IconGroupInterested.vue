<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheckCircle, faTimesCircle } from '@fal'
import { faCircle } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCheckCircle as fasCheckCircle } from '@fas'
library.add(faCheckCircle, faTimesCircle, faCircle, fasCheckCircle)

const props = defineProps<{
    columnValue?: string
}>()
</script>

<template>
    <div>
        <FontAwesomeIcon v-if="columnValue == 'customer'" icon="fas fa-check-circle" class="text-green-500" title="Paid" />
        <FontAwesomeIcon v-else-if="columnValue == 'interested'" icon="fal fa-check-circle" class="text-green-500" title="Interested"/>
        <FontAwesomeIcon v-else-if="columnValue == 'not_interested'" icon="fal fa-times-circle" class="text-red-500" title="Not interested"/>
        <FontAwesomeIcon v-else icon="far fa-circle" class="text-gray-500" title="Not sure" />
    </div>
</template>
