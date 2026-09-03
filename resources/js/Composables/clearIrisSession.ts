export const clearIrisSession = (layout: any) => {
    const hasStorage = typeof window !== "undefined" && !!window.localStorage

    const snapshot = {
        iris: layout?.iris ? { is_logged_in: layout.iris.is_logged_in, customer: layout.iris.customer } : null,
        user: "user" in layout ? layout.user : undefined,
        iris_variables: "iris_variables" in layout ? layout.iris_variables : undefined,
        offer_data: "offer_data" in layout ? layout.offer_data : undefined,
        offer_meters: "offer_meters" in layout ? layout.offer_meters : undefined,
        storage: hasStorage ? localStorage.getItem("iris") : null,
    }

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

    if (hasStorage) {
        const storageIris = JSON.parse(snapshot.storage || "{}")
        localStorage.setItem("iris", JSON.stringify({
            ...storageIris,
            is_logged_in: false,
            iris_variables: null,
            offer_data: null,
            offer_meters: null,
        }))
    }

    /* The logout that triggered this can still fail, and it is cleared before the request so that
       the page it navigates to never paints logged in chrome. Restoring puts the visitor back. */
    return () => {
        if (layout?.iris && snapshot.iris) {
            layout.iris.is_logged_in = snapshot.iris.is_logged_in
            layout.iris.customer = snapshot.iris.customer
        }

        if (snapshot.user !== undefined) {
            layout.user = snapshot.user
        }

        if (snapshot.iris_variables !== undefined) {
            layout.iris_variables = snapshot.iris_variables
        }

        if (snapshot.offer_data !== undefined) {
            layout.offer_data = snapshot.offer_data
        }

        if (snapshot.offer_meters !== undefined) {
            layout.offer_meters = snapshot.offer_meters
        }

        if (hasStorage && snapshot.storage !== null) {
            localStorage.setItem("iris", snapshot.storage)
        }
    }
}
