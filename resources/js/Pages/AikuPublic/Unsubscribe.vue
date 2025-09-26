<script setup>
// import { ref } from 'vue'
// import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
// import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faPlus, faMinus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Head } from '@inertiajs/vue3'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faGithub } from '@fortawesome/free-brands-svg-icons'
library.add(faCheck, faPlus, faMinus, faGithub)

const features = [
    {
        name: 'Customized Configurations',
        description: 'Tailor your ERP system to fit your unique requirements.'
    },
    {
        name: 'Robust Security Measures',
        description: 'Rest easy knowing your data is protected by our advanced security protocols.'
    },
    {
        name: '24/7 Accessibility',
        description: 'Access your ERP data anytime, anywhere for seamless business operations.'
    },
    {
        name: 'Data Integrity Assurance',
        description: 'Ensure the accuracy and integrity of your business data with our comprehensive management solutions.'
    },
    {
        name: 'Efficient Workflow Management',
        description: 'Streamline your business processes with our intuitive workflow tools.'
    },
    {
        name: 'Seamless Integration',
        description: 'Effortlessly integrate your ERP software with existing systems for enhanced efficiency.'
    },
];

const pricing = [
    {
        name: 'Basic',
        id: 'tier-basic',
        href: '#',
        priceMonthly: '$99',
        description: "Ideal for small businesses or startups with moderate storage needs.",
        features: ['100 sq. ft. storage space', '24/7 access', 'Basic security features'],
        featured: false,
    },
    {
        name: 'Pro',
        id: 'tier-pro',
        href: '#',
        priceMonthly: '$249',
        description: 'Perfect for growing businesses with increased storage demands.',
        features: [
            '250 sq. ft. storage space',
            'Climate-controlled environment',
            'Advanced security system',
            'Inventory management software',
        ],
        featured: true,
    },
]

const faqs = [
    {
        question: "What sizes of storage spaces do you offer?",
        answer:
            "We offer a range of storage space sizes to accommodate various needs, from small businesses to large-scale operations. Our options include spaces starting from 100 square feet up to customizable areas exceeding 500 square feet.",
    },
    {
        question: "How secure are your warehouse facilities?",
        answer:
            "Security is a top priority for us. Our warehouse facilities are equipped with state-of-the-art security systems, including surveillance cameras, access control measures, and on-site personnel. Additionally, we implement customized security protocols to ensure the safety and integrity of your stored items.",
    },
]

</script>

<template >
    <Head title="Free ERP Online Software" />


    <span class="text-red-500 mt-10">xxx</span>



</template>