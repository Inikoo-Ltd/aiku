import { trans } from "laravel-vue-i18n"

export const irisVariable = [
    {
        label: trans("Name"),
        value: "name",
    },
    {
        label: trans("Username"),
        value: "username",
    },
    {
        label: trans("Email"),
        value: "email",
    },
    {
        label: trans("Favourites count"),
        value: "favourites_count",
    },
    {
        label: trans("Cart count"),
        value: "cart_count",
    },
    {
        label: trans("Cart amount"),
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