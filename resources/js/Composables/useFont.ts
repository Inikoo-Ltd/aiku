import { getActivePinia } from "pinia"

export const useFontFamilyList: { label: string, value: string, slug: string, notFor: string[] }[] = [
    {
        label: "Inter",
        value: "Inter, sans-serif",
        slug: "inter",
        notFor: []
    },
    {
        label: "Arial",
        value: "Arial, sans-serif",
        slug: "arial",
        notFor: []
    },
    {
        label: "Avenir",
        value: "Avenir, sans-serif",
        slug: "avenir",
        notFor: ["pl", "cs", "sk", "hu", "ro", "hr", "bg", "uk"]
    },
    {
        label: "Bunya",
        value: "Bunya, sans-serif",
        slug: "bunya",
        notFor: ["ro", "bg", "uk"]
    },
    {
        label: "Cardinal",
        value: "'Cardinal', serif",
        slug: "cardinal",
        notFor: ["pl", "cs", "sk", "hu", "ro", "hr", "bg", "uk"]
    },
    {
        label: "Comfortaa",
        value: "'Comfortaa', sans-serif",
        slug: "comfortaa",
        notFor: []
    },
    {
        label: "Lobster",
        value: "'Lobster', cursive",
        slug: "lobster",
        notFor: []
    },
    {
        label: "Laila",
        value: "'Laila', sans-serif",
        slug: "laila",
        notFor: ["pl", "cs", "sk", "hu", "ro", "hr", "bg", "uk"]
    },
    {
        label: "Port Lligat Slab",
        value: "'Port Lligat Slab', serif",
        slug: "port-lligat-slab",
        notFor: ["pl", "cs", "sk", "hu", "ro", "hr", "bg", "uk"]
    },
    {
        label: "Playfair",
        value: "'Playfair Display', serif",
        slug: "playfair-display",
        notFor: []
    },
    {
        label: "Raleway",
        value: "'Raleway', sans-serif",
        slug: "raleway",
        notFor: []
    },
    {
        label: "Roman Melikhov",
        value: "'Roman Melikhov', serif",
        slug: "roman-melikhov",
        notFor: ["pl", "cs", "sk", "ro", "hr", "bg", "uk"]
    },
    {
        label: "Shoemaker",
        value: "'Shoemaker', sans-serif",
        slug: "shoemaker",
        notFor: ["pl", "cs", "sk", "hu", "ro", "hr", "bg", "uk"]
    },
    {
        label: "Source Sans Pro",
        value: "'Source Sans Pro', sans-serif",
        slug: "source-sans-pro",
        notFor: []
    },
    {
        label: "Quicksand",
        value: "'Quicksand', sans-serif",
        slug: "quicksand",
        notFor: ["bg", "uk"]
    },
    {
        label: "Times New Roman",
        value: "'Times New Roman', serif",
        slug: "times-new-roman",
        notFor: []
    },
    {
        label: "Conneqt",
        value: "'Conneqt', serif",
        slug: "conneqt",
        notFor: ["pl", "cs", "sk", "ro", "hr", "bg", "uk"]
    },

]
export const fontsFor = (languageCode?: string | null) =>
    languageCode ? useFontFamilyList.filter((font) => !font.notFor.includes(languageCode)) : useFontFamilyList

export const fontsForCurrentShop = () =>
    fontsFor((getActivePinia()?.state.value as Record<string, any> | undefined)?.layout?.shopState?.language)
