package io.aiku.droid.data.session

import android.content.SharedPreferences
import io.aiku.droid.data.api.OrganisationSummary
import io.aiku.droid.data.api.WarehouseAbilities
import io.aiku.droid.data.api.WarehouseAccess
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import java.util.Base64

class DeviceWarehouseCacheTest {

    private val preferences = InMemoryPreferences()
    private val cache = DeviceWarehouseCache(preferences, XorCipher())
    private val main = WarehouseAccess(
        id = 1,
        slug = "wh1",
        code = "WH1",
        name = "Main Warehouse",
        organisation = OrganisationSummary(id = 3, slug = "aw", name = "AW", code = "AW"),
        can = WarehouseAbilities(viewGoodsOut = true, pickAndPack = true, viewLocations = true),
    )

    @Test
    fun `saved warehouses are read back with their rights for the same user`() {
        cache.save(42, listOf(main))

        assertEquals(listOf(main), cache.read(42))
    }

    @Test
    fun `a user without warehouses is cached as an empty list`() {
        cache.save(42, emptyList())

        assertEquals(emptyList<WarehouseAccess>(), cache.read(42))
    }

    @Test
    fun `the warehouses are stored encrypted`() {
        cache.save(42, listOf(main))

        val stored = preferences.storedValues.values.single() as String
        val storedBytes = Base64.getDecoder().decode(stored).toString(Charsets.UTF_8)

        assertFalse(stored.contains("Main Warehouse"))
        assertFalse(storedBytes.contains("Main Warehouse"))
        assertFalse(storedBytes.contains("pick_and_pack"))
    }

    @Test
    fun `the warehouses of another user are never returned`() {
        cache.save(42, listOf(main))

        assertNull(cache.read(7))
    }

    @Test
    fun `clearing removes the cached warehouses from the device`() {
        cache.save(42, listOf(main))

        cache.clear()

        assertNull(cache.read(42))
        assertTrue(preferences.storedValues.isEmpty())
    }

    @Test
    fun `unreadable data is dropped`() {
        cache.save(42, listOf(main))
        val key = preferences.storedValues.keys.single()
        preferences.edit().putString(key, "not base64 at all").apply()

        assertNull(cache.read(42))
        assertTrue(preferences.storedValues.isEmpty())
    }

    private class XorCipher : ByteCipher {
        override fun encrypt(plainBytes: ByteArray): ByteArray = plainBytes.map { (it.toInt() xor KEY).toByte() }.toByteArray()

        override fun decrypt(encryptedBytes: ByteArray): ByteArray = encrypt(encryptedBytes)

        private companion object {
            const val KEY = 0x5A
        }
    }

    private class InMemoryPreferences : SharedPreferences {
        val storedValues = mutableMapOf<String, Any?>()

        override fun getAll(): MutableMap<String, *> = storedValues.toMutableMap()

        override fun getString(key: String?, defValue: String?): String? = storedValues[key] as? String ?: defValue

        override fun getStringSet(key: String?, defValues: MutableSet<String>?): MutableSet<String>? = defValues

        override fun getInt(key: String?, defValue: Int): Int = defValue

        override fun getLong(key: String?, defValue: Long): Long = defValue

        override fun getFloat(key: String?, defValue: Float): Float = defValue

        override fun getBoolean(key: String?, defValue: Boolean): Boolean = defValue

        override fun contains(key: String?): Boolean = storedValues.containsKey(key)

        override fun edit(): SharedPreferences.Editor = Editor()

        override fun registerOnSharedPreferenceChangeListener(listener: SharedPreferences.OnSharedPreferenceChangeListener?) = Unit

        override fun unregisterOnSharedPreferenceChangeListener(listener: SharedPreferences.OnSharedPreferenceChangeListener?) = Unit

        private inner class Editor : SharedPreferences.Editor {
            private val changes = mutableMapOf<String, Any?>()
            private val removals = mutableSetOf<String>()

            override fun putString(key: String?, value: String?): SharedPreferences.Editor = put(key, value)

            override fun putStringSet(key: String?, values: MutableSet<String>?): SharedPreferences.Editor = put(key, values)

            override fun putInt(key: String?, value: Int): SharedPreferences.Editor = put(key, value)

            override fun putLong(key: String?, value: Long): SharedPreferences.Editor = put(key, value)

            override fun putFloat(key: String?, value: Float): SharedPreferences.Editor = put(key, value)

            override fun putBoolean(key: String?, value: Boolean): SharedPreferences.Editor = put(key, value)

            override fun remove(key: String?): SharedPreferences.Editor {
                key?.let { removals += it }
                return this
            }

            override fun clear(): SharedPreferences.Editor {
                removals += storedValues.keys
                return this
            }

            override fun commit(): Boolean {
                apply()
                return true
            }

            override fun apply() {
                removals.forEach { storedValues.remove(it) }
                storedValues.putAll(changes)
            }

            private fun put(key: String?, value: Any?): SharedPreferences.Editor {
                key?.let { changes[it] = value }
                return this
            }
        }
    }
}
