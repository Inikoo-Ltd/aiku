package io.aiku.droid.data.images

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.util.Log
import android.util.LruCache
import kotlinx.coroutines.CoroutineDispatcher
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.HttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.OkHttpClient
import okhttp3.Request
import java.io.IOException

interface ImageSource {
    fun cached(url: String): Bitmap?

    suspend fun load(url: String): Bitmap?
}

class RemoteImages(
    private val apiClient: OkHttpClient,
    apiBaseUrl: String,
    private val ioDispatcher: CoroutineDispatcher = Dispatchers.IO,
) : ImageSource {

    private val apiHost = apiBaseUrl.toHttpUrlOrNull()?.host
    private val externalClient = apiClient.newBuilder().apply { interceptors().clear() }.build()
    private val cache = LruCache<String, Bitmap>(MAX_CACHED_IMAGES)

    override fun cached(url: String): Bitmap? = cache.get(url)

    override suspend fun load(url: String): Bitmap? {
        cache.get(url)?.let { return it }
        val imageUrl = url.toHttpUrlOrNull() ?: return null

        return withContext(ioDispatcher) { download(imageUrl) }?.also { cache.put(url, it) }
    }

    private fun download(imageUrl: HttpUrl): Bitmap? {
        val client = if (imageUrl.host.equals(apiHost, ignoreCase = true)) apiClient else externalClient
        return try {
            client.newCall(Request.Builder().url(imageUrl).get().build()).execute().use { response ->
                if (response.isSuccessful) BitmapFactory.decodeStream(response.body.byteStream()) else null
            }
        } catch (exception: IOException) {
            Log.w(LOG_TAG, "GET $imageUrl failed", exception)
            null
        }
    }

    private companion object {
        const val LOG_TAG = "AikuImages"
        const val MAX_CACHED_IMAGES = 200
    }
}
