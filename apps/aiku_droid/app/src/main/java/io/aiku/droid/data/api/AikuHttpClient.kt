package io.aiku.droid.data.api

import okhttp3.Dns
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.OkHttpClient
import okhttp3.tls.HandshakeCertificates
import okhttp3.tls.decodeCertificatePem
import java.net.InetAddress
import java.util.concurrent.TimeUnit

object AikuHttpClient {

    fun create(
        apiBaseUrl: String,
        appKey: String,
        developmentHostAddress: String,
        developmentCaCertificate: String,
    ): OkHttpClient {
        val builder = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .addInterceptor { chain ->
                chain.proceed(
                    chain.request().newBuilder()
                        .header("X-Aiku-App-Key", appKey)
                        .header("Accept", "application/json")
                        .build(),
                )
            }

        if (developmentHostAddress.isNotBlank()) {
            builder.dns(ApiHostDns(apiHost = apiBaseUrl.toHttpUrl().host, hostAddress = developmentHostAddress))
        }

        if (developmentCaCertificate.isNotBlank()) {
            val certificates = HandshakeCertificates.Builder()
                .addTrustedCertificate(developmentCaCertificate.decodeCertificatePem())
                .addPlatformTrustedCertificates()
                .build()
            builder.sslSocketFactory(certificates.sslSocketFactory(), certificates.trustManager)
        }

        return builder.build()
    }
}

private class ApiHostDns(private val apiHost: String, private val hostAddress: String) : Dns {
    override fun lookup(hostname: String): List<InetAddress> =
        if (hostname.equals(apiHost, ignoreCase = true)) {
            listOf(InetAddress.getByName(hostAddress))
        } else {
            Dns.SYSTEM.lookup(hostname)
        }
}
