package io.aiku.droid.data.offline

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

class NetworkConnectivity(context: Context) {

    private val manager = context.getSystemService(ConnectivityManager::class.java)

    private val online = MutableStateFlow(hasValidatedInternet(manager?.activeNetwork?.let { manager.getNetworkCapabilities(it) }))

    val isOnline: StateFlow<Boolean> = online.asStateFlow()

    fun start() {
        val connectivity = manager ?: return
        runCatching {
            connectivity.registerDefaultNetworkCallback(
                object : ConnectivityManager.NetworkCallback() {
                    override fun onCapabilitiesChanged(network: Network, networkCapabilities: NetworkCapabilities) {
                        online.value = hasValidatedInternet(networkCapabilities)
                    }

                    override fun onLost(network: Network) {
                        online.value = false
                    }

                    override fun onUnavailable() {
                        online.value = false
                    }
                },
            )
        }
    }

    private fun hasValidatedInternet(capabilities: NetworkCapabilities?): Boolean =
        capabilities != null &&
            capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) &&
            capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED)
}
