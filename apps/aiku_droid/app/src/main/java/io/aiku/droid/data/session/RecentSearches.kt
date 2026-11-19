package io.aiku.droid.data.session

import android.content.SharedPreferences
import androidx.core.content.edit
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import java.util.Base64

interface RecentSearches {
    fun read(userId: Long, warehouseId: Long): List<String>

    fun remember(userId: Long, warehouseId: Long, query: String): List<String>

    fun clear()

    companion object {
        const val LIMIT = 8
    }
}

@Serializable
private data class StoredRecentSearches(
    val userId: Long,
    val warehouses: Map<Long, List<String>> = emptyMap(),
)

class DeviceRecentSearches(
    private val preferences: SharedPreferences,
    private val cipher: ByteCipher,
) : RecentSearches {

    private val json = Json { ignoreUnknownKeys = true }

    override fun read(userId: Long, warehouseId: Long): List<String> = stored(userId)?.warehouses?.get(warehouseId).orEmpty()

    override fun remember(userId: Long, warehouseId: Long, query: String): List<String> {
        val trimmed = query.trim()
        val stored = stored(userId) ?: StoredRecentSearches(userId)
        if (trimmed.isEmpty()) {
            return stored.warehouses[warehouseId].orEmpty()
        }
        val recent = (listOf(trimmed) + stored.warehouses[warehouseId].orEmpty().filterNot { it.equals(trimmed, ignoreCase = true) })
            .take(RecentSearches.LIMIT)
        save(stored.copy(warehouses = stored.warehouses + (warehouseId to recent)))
        return recent
    }

    override fun clear() {
        preferences.edit { remove(RECENT_SEARCHES_KEY) }
    }

    private fun stored(userId: Long): StoredRecentSearches? {
        val encoded = preferences.getString(RECENT_SEARCHES_KEY, null) ?: return null

        val stored = runCatching {
            val decrypted = cipher.decrypt(Base64.getDecoder().decode(encoded))
            json.decodeFromString<StoredRecentSearches>(decrypted.toString(Charsets.UTF_8))
        }.getOrElse {
            clear()
            return null
        }

        return stored.takeIf { it.userId == userId }
    }

    private fun save(stored: StoredRecentSearches) {
        val plain = json.encodeToString(stored).toByteArray(Charsets.UTF_8)
        val encrypted = runCatching { cipher.encrypt(plain) }.getOrElse {
            clear()
            return
        }
        preferences.edit { putString(RECENT_SEARCHES_KEY, Base64.getEncoder().encodeToString(encrypted)) }
    }

    private companion object {
        const val RECENT_SEARCHES_KEY = "recent_searches"
    }
}
