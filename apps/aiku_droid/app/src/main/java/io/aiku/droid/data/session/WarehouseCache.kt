package io.aiku.droid.data.session

import android.content.SharedPreferences
import androidx.core.content.edit
import io.aiku.droid.data.api.WarehouseAccess
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import java.util.Base64

interface WarehouseCache {
    fun read(userId: Long): List<WarehouseAccess>?

    fun save(userId: Long, warehouses: List<WarehouseAccess>)

    fun clear()
}

@Serializable
private data class CachedWarehouses(
    val userId: Long,
    val warehouses: List<WarehouseAccess>,
)

class DeviceWarehouseCache(
    private val preferences: SharedPreferences,
    private val cipher: ByteCipher,
) : WarehouseCache {

    private val json = Json { ignoreUnknownKeys = true }

    override fun read(userId: Long): List<WarehouseAccess>? {
        val stored = preferences.getString(WAREHOUSES_KEY, null) ?: return null

        val cached = runCatching {
            val decrypted = cipher.decrypt(Base64.getDecoder().decode(stored))
            json.decodeFromString<CachedWarehouses>(decrypted.toString(Charsets.UTF_8))
        }.getOrElse {
            clear()
            return null
        }

        return cached.warehouses.takeIf { cached.userId == userId }
    }

    override fun save(userId: Long, warehouses: List<WarehouseAccess>) {
        val plain = json.encodeToString(CachedWarehouses(userId, warehouses)).toByteArray(Charsets.UTF_8)
        val encrypted = runCatching { cipher.encrypt(plain) }.getOrElse {
            clear()
            return
        }
        preferences.edit { putString(WAREHOUSES_KEY, Base64.getEncoder().encodeToString(encrypted)) }
    }

    override fun clear() {
        preferences.edit { remove(WAREHOUSES_KEY) }
    }

    private companion object {
        const val WAREHOUSES_KEY = "cached_warehouses"
    }
}
