import { ctrans } from "@/Composables/useTrans"

export const irisVariable = [
    {
        label: ctrans("Name"),
        value: "name",
    },
    {
        label: ctrans("Username"),
        value: "username",
    },
    {
        label: ctrans("Email"),
        value: "email",
    },
    {
        label: ctrans("Favourites count"),
        value: "favourites_count",
    },
    {
        label: ctrans("Cart count"),
        value: "cart_count",
    },
    {
        label: ctrans("Cart amount"),
        value: "cart_amount",
    },
]

export const mergetags = [
    {
        name: 'First Name',
        value: '[first-name]'
    }, {
        name: 'Last Name',
        value: '[last-name]'
    }, {
        name: 'Email',
        value: '[email]'
    }, {
        name: 'Latest order date',
        value: '[order-date]'
    }
]