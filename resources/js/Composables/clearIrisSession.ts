export const clearIrisSession = (layout: any) => {
    if (layout?.iris) {
        layout.iris.is_logged_in = false
        layout.iris.customer = null
    }

    if ("user" in layout) {
        layout.user = null
    }

    if ("iris_variables" in layout) {
        layout.iris_variables = {}
    }

    if ("offer_data" in layout) {
        layout.offer_data = null
    }

    if ("offer_meters" in layout) {
        layout.offer_meters = {}
    }

    if (typeof window === "undefined" || !window.localStorage) {
        return
    }

    const storageIris = JSON.parse(localStorage.getItem("iris") || "{}")
    localStorage.setItem("iris", JSON.stringify({
        ...storageIris,
        is_logged_in: false,
        iris_variables: null,
        offer_data: null,
        offer_meters: null,
    }))
}
