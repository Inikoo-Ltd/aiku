<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { watchEffect, reactive } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faGoogle } from "@fortawesome/free-brands-svg-icons"
import { faAbacus, faUser, faAd, faThumbsUp, faShapes, faCommentsDollar, faCircle, faCrown } from '@fal'
import { faExclamationCircle ,faCheckCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faGoogle, faAbacus, faUser, faAd, faThumbsUp, faShapes, faCommentsDollar, faCircle, faCrown, faExclamationCircle, faCheckCircle)

const props = defineProps<{
    form?: any
    fieldName: string
    options: string[] | {}
    fieldData?: {
    }
}>()

interface selectedJob {
    [key: string]: string
}

const optionsJob = {
    "admin": [
        {
            "icon": 'fal fa-crown',
            "code": "admin",
            "name": "Administrator",
            "department": "admin",
        }
    ],

    "cus": [
        {
            "icon": "fal fa-user ",
            "code": "cus-m",
            "grade": "manager",
            "department": trans("Customers Services"),
            "name": "Manager",
        }, {
            "code": "cus-c",
            "grade": "clerk",
            "department": "Customers Services",
            "name": "Worker",
        }
    ],

    "mrk": [
        {
            "icon": "fal fa-comments-dollar",
            "code": "mrk-m",
            "grade": "manager",
            "department": "marketing",
            "name": "Manager",
        }, {
            "code": "mrk-c",
            "grade": "clerk",
            "department": "marketing",
            "name": "Worker",
        }
    ],

    "web": [
        {
            "icon": 'fal fa-globe',
            "code": "web-m",
            "grade": "manager",
            "department": "Website Master",
            "name": "Manager",
        }, {
            "code": "web-c",
            "grade": "clerk",
            "department": "Website Master",
            "name": "Worker",
        }
    ],

    "seo": [
        {
            "icon": "fab fa-google",
            "code": "seo-m",
            "team": "seo",
            "department": "SEO",
            "name": "Manager",
        }, {
            "code": "seo-w",
            "team": "seo",
            "department": "SEO",
            "name": "Worker",
        }
    ],

    "ppc": [
        {
            "icon": "fal fa-ad",
            "code": "ppc-m",
            "team": "ppc",
            "department": "Ads",
            "name": "Manager",
        }, {
            "code": "ppc-w",
            "team": "ppc",
            "department": "Ads",
            "name": "Worker",
        }
    ],

    "social": [
        {
            "icon": "fal fa-thumbs-up ",
            "code": "social-m",
            "team": "social",
            "department": "Social",
            "name": "Manager",
        }, {
            "code": "social-w",
            "team": "social",
            "department": "Social",
            "name": "Worker",
        }
    ],

    "dev": [
        {
            "icon": "fal fa-shapes ",
            "code": "dev-m",
            "team": "developer",
            "department": "CaaS",
            "name": "Manager",
        }, {
            "code": "dev-w",
            "team": "developer",
            "department": "CaaS",
            "name": "Worker",
        }
    ],

    "acc": [
        {
            "icon": "fal fa-abacus",
            "code": "acc-m",
            "department": "Accounting",
            "name": "Manager",
        }, {
            "code": "acc-c",
            "department": "Accounting",
            "name": "Worker",
        }
    ],

    "hr": [
        {
            "icon": "fal fa-user-hard-hat",
            "code": "hr-m",
            "grade": "manager",
            "department": "Human Resources",
            "name": "Manager",
        }, {
            "code": "hr-c",
            "name": "Worker",
            "department": "Human Resources",
            "grade": "clerk",
        }
    ],
}

// Temporary data
const selectedBox: selectedJob = reactive({})

// To preserve on first load (so the box is selected)
for (const key in optionsJob) {
    for (const item of optionsJob[key]) {
        if ((props.form[props.fieldName].map((option: any) => option = option.code)).includes(item.code)) {
            selectedBox[key] = item.code;
        }
    }
}

// When the box is clicked
const handleClickBox = (jobGroupName: string, jobCode: any) => {
    if(selectedBox[jobGroupName] == 'admin'){  // If the box clicked is 'admin'
        if(selectedBox[jobGroupName] == jobCode) {  // When active box clicked
            selectedBox[jobGroupName] = ""  // Deselect value
        } else {
            selectedBox[jobGroupName] = jobCode
        }
    } else { // If the box clicked is not 'admin'
        if(selectedBox[jobGroupName] == jobCode && props.form[props.fieldName].length > 1) {  // When active box clicked
            selectedBox[jobGroupName] = ""  // Deselect value
        } else {
            selectedBox[jobGroupName] = jobCode
        }
    }
    props.form.errors[props.fieldName] = ''
}

// To save the temporary data (selectedBox) to props.form
watchEffect(() => {
    const tempObject = {...selectedBox}
    selectedBox.admin ? '' : delete tempObject.admin
    props.form[props.fieldName] = selectedBox.admin ? [selectedBox.admin] : Object.values(tempObject).filter(item=> item)
})

</script>

<template>
    <div class="relative">
        <div class="flex flex-col text-xs divide-y-[1px]">
            <div v-for="(jobGroup, keyJob) in optionsJob" class="grid grid-cols-3 gap-x-1.5 px-2 items-center">
                <!-- The box -->
                <div class="flex items-center capitalize gap-x-1.5">
                    <FontAwesomeIcon v-if="jobGroup[0].icon" :icon="jobGroup[0].icon" class='text-gray-400' aria-hidden='true' />
                    {{ jobGroup[0].department }}
                </div>

                <!-- The Clickable area -->
                <button v-for="job in jobGroup"
                    @click.prevent="handleClickBox(keyJob, job.code)"
                    class="group h-full cursor-pointer flex items-center justify-start even:pl-10 odd:justify-center rounded-md py-3 px-3 font-medium capitalize disabled:text-gray-400 disabled:cursor-not-allowed disabled:ring-0 disabled:active:active:ring-offset-0"
                    :class="[
                        selectedBox[keyJob] == job.code ? 'text-lime-500' : ' text-gray-600'
                    ]"
                    :disabled="selectedBox.admin && job.code != 'admin'? true : false"
                >
                    <span class="relative">
                        <FontAwesomeIcon v-if="selectedBox[keyJob] == 'admin'" icon='fas fa-check-circle' class='absolute -left-1 -translate-x-full top-1/2 -translate-y-1/2' aria-hidden='true' />
                        <FontAwesomeIcon v-else-if="selectedBox[keyJob] == job.code && !selectedBox.admin" icon='fas fa-check-circle' class='absolute -left-1 -translate-x-full top-1/2 -translate-y-1/2' aria-hidden='true' />
                        <FontAwesomeIcon v-else icon='fal fa-circle' class='absolute -left-1 -translate-x-full top-1/2 -translate-y-1/2' aria-hidden='true' />
                        <span :class="[
                            selectedBox.admin && selectedBox[keyJob] != 'admin' ? 'text-gray-300' : ' text-gray-500 group-hover:text-gray-800'
                        ]">
                            {{job.name}}
                        </span>
                    </span>
                </button>
            </div>
        </div>

        <!-- State: error icon & error description -->
        <div v-if="form.errors[fieldName] || form.recentlySuccessful " class="mt-1 flex items-center gap-x-1.5 pointer-events-none">
            <FontAwesomeIcon icon="fas fa-exclamation-circle" v-if="form.errors[fieldName]" class="h-5 w-5 text-red-500" aria-hidden="true" />
            <FontAwesomeIcon icon="fas fa-check-circle" v-if="form.recentlySuccessful" class="h-5 w-5 text-green-500" aria-hidden="true"/>
            <p v-if="form.errors[fieldName]" class="text-sm text-red-600 ">{{ form.errors[fieldName] }}</p>
        </div>

    </div>
</template>
