import axios from "axios"
import { ref, Ref } from "vue"
import { AddressOptions } from "@/types/PureComponent/Address"

type CountriesAddressData = AddressOptions["countriesAddressData"]

const countriesAddressData = ref<CountriesAddressData | null>(null)
let request: Promise<void> | null = null

export const useCountriesAddressData = (): Ref<CountriesAddressData | null> => {
    if (!request) {
        request = axios.get(route("grp.json.countries_address_data"))
            .then(({ data }) => {
                countriesAddressData.value = data
            })
            .catch(() => {
                request = null
            })
    }

    return countriesAddressData
}
