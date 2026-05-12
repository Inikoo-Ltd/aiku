/**
 * Author: Vika Aqordi
 * Created on 13-10-2025-09h-37m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
 */

import axios from "axios"
import { set } from "lodash-es"
import Cookies from "js-cookie"
import { usePage } from "@inertiajs/vue3"


export const initialiseIrisVarnish = async (layoutStore) => {
  const layout = layoutStore()
  let storageIris = {}
  if (typeof window !== "undefined") {
    storageIris = JSON.parse(localStorage.getItem("iris") || "{}")  // Get layout from localStorage
    layout.iris.is_logged_in = storageIris?.is_logged_in ?? false
  }

  const isAppRoute = window.location.pathname.startsWith("/app")
  const selectedUrl = !isAppRoute ? "/json/first-hit" : "/app/json/first-hit"  // HasIrisUserData
  const currentUrl = new URL(window.location.href)
  const headers = {
    "X-Traffic-Sources": currentUrl.search?.replace(/^\?/, "") || "", //todo: review this because this headers are no set anymore use url queries instead
    "X-Original-Referer": currentUrl.origin + currentUrl.pathname, //todo: review this because this headers are no set anymore use url queries instead
    "X-Requested-With": "XMLHttpRequest"
  }

  // Fetch: auth_data (GetIrisFirstHitData)
  const getVarnishData = async () => {
    try {
      set(layout, ["iris_varnish", "isFetching"], true)
      const response = await axios.get(selectedUrl, { headers })
      set(layout, ["iris_varnish", "isFetching"], false)

      // console.log('Iris Varnish', response.data)
      return response.data
    } catch (error) {
      if (error?.status === 403) {
        localStorage.setItem("iris", JSON.stringify({
          ...storageIris,
          is_logged_in: false,
          iris_variables: null,
          offer_meters: null
        }))
        layout.iris.is_logged_in = false
      }
      // console.error('Error fetching first hit:', error)
    } finally {
      set(layout, ["iris_varnish", "isFetching"], false)
    }
  }

  const varnish = await getVarnishData()
  if (!varnish) return

  console.log("Initial Varnish Response:", varnish)

  // --- Handle logged-out ---
  if (!varnish.is_logged_in) {
    localStorage.setItem("iris", JSON.stringify({
      ...storageIris,
      is_logged_in: false,
      iris_variables: varnish?.variables ?? null,
      offer_meters: varnish?.offer_meters ?? null
    }))

    layout.user = varnish.auth?.user || null
    if (layout.user?.customerSalesChannels) {
      layout.user.customerSalesChannels = null
    }

    layout.iris.is_logged_in = false
    layout.iris.customer = null
    return
  }

  // --- Handle logged-in ---
  if (varnish.is_logged_in) {
    // Data: Iris Variables
    if (varnish?.variables) {
      layout.iris_variables = varnish.variables
    }

    // Data: Gold Reward
    if (varnish?.offer_data) {
      layout.offer_data = varnish.offer_data
    }

    if (varnish?.offer_meters) {
      layout.offer_meters = varnish.offer_meters
    }

    localStorage.setItem("iris", JSON.stringify({
      ...storageIris,
      is_logged_in: true,
      iris_variables: varnish.variables,
      offer_meters: varnish.offer_meters ?? null
    }))

    layout.user = varnish.auth?.user || null
    layout.user.gr_data = varnish.gr_data || null
    if (varnish.auth?.customerSalesChannels) {
      layout.user.customerSalesChannels = varnish.auth.customerSalesChannels
    }

    layout.iris.is_logged_in = true
    layout.iris.customer = varnish.customer ?? null
  }

  // --- Set Traffic Source Cookies ---
  if (varnish?.traffic_source_cookies) {
    for (const [key, cookieData] of Object.entries(varnish.traffic_source_cookies)) {
      if (cookieData?.value) {
        Cookies.set(key, cookieData.value, cookieData.duration)
      }
    }
  }
}


export const initialiseIrisVarnishCustomerData = async (layout) => {
  const isAppRoute = window.location.pathname.startsWith("/app")
  const selectedUrl = !isAppRoute ? "/json/ecom-customer-data" : "/json/ecom-customer-data"
  const currentUrl = new URL(window.location.href)
  const headers = {
    "X-Traffic-Sources": currentUrl.search?.replace(/^\?/, "") || "", //todo: review this because this headers are no set anymore use url queries instead
    "X-Original-Referer": currentUrl.origin + currentUrl.pathname, //todo: review this because this headers are no set anymore use url queries instead
    "X-Requested-With": "XMLHttpRequest"
  }

  // Fetch: auth_data (GetIrisFirstHitData)
  const getVarnishData = async () => {
    try {
      const response = await axios.get(selectedUrl, { headers })
      set(layout, ["iris_varnish", "isFetching"], false)

      return response.data
    } catch (error) {
      // console.error('Error fetching auth_data:', error)
    } finally {
      set(layout, ["iris_varnish", "isFetching"], false)
    }
  }

  const varnish = await getVarnishData()

  console.log("Customer Data", varnish)

  if (!varnish) {
    return
  }

  if (varnish?.offer_meters) {
    layout.offer_meters = varnish.offer_meters
  }

  if (varnish?.variables) {
    layout.iris_variables = { ...layout.iris_variable, ...varnish?.variables }
  }
}


export const recordWebsiteHit = () => {
  const isRetina = window.location.pathname.startsWith("/app")

  const headers = {
    "X-Requested-With": "XMLHttpRequest",
  }

  // Fire-and-forget request used only to record hit analytics.
  void axios.post("/models/record-hit", {
      original_route: route().current(),
      original_params: route().params,
      webpage_id: usePage().props.webpage_id,
      original_referer: document.referrer,
      analytics_webpage: usePage().props.webpage_slug,
      analytics_app: isRetina ? "retina" : "iris",
  }, { headers }).catch(() => {})
}
