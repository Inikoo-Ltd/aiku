package io.aiku.droid.data.session

import android.content.SharedPreferences
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import java.util.Base64

class DeviceRecentSearchesTest {

    private val preferences = InMemoryPreferences()
    private val recent = DeviceRecentSearches(preferences, XorCipher())

    @Test
    fun `the newest query comes first and a repeated one moves to the front`() {
        recent.remember(42, 1, "lav")
        recent.remember(42, 1, "AR24817")
        val remembered = recent.remember(42, 1, " LAV ")

        assertEquals(listOf("LAV", "AR24817"), remembered)
        assertEquals(listOf("LAV", "AR24817"), recent.read(42, 1))
    }

    @Test
    fun `only the last 8 are kept`() {
        (1..10).forEach { recent.remember(42, 1, "query $it") }

        assertEquals((10 downTo 3).map { "query $it" }, recent.read(42, 1))
    }

    @Test
    fun `each warehouse keeps its own recent searches`() {
        recent.remember(42, 1, "lav")
        recent.remember(42, 2, "A-03")

        assertEquals(listOf("lav"), recent.read(42, 1))
        assertEquals(listOf("A-03"), recent.read(42, 2))
        assertTrue(recent.read(42, 3).isEmpty())
    }

    @Test
    fun `a blank query is not remembered`() {
        recent.remember(42, 1, "lav")

        assertEquals(listOf("lav"), recent.remember(42, 1, "  "))
    }

    @Test
    fun `another user never sees them and replaces them`() {
        recent.remember(42, 1, "lav")

        assertTrue(recent.read(7, 1).isEmpty())

        recent.remember(7, 1, "rose")

        assertEquals(listOf("rose"), recent.read(7, 1))
        assertTrue(recent.read(42, 1).isEmpty())
    }

    @Test
    fun `they are stored encrypted`() {
        recent.remember(42, 1, "AR24817")

        val stored = preferences.storedValues.values.single() as String
        val storedBytes = Base64.getDecoder().decode(stored).toString(Charsets.UTF_8)

        assertFalse(stored.contains("AR24817"))
        assertFalse(storedBytes.contains("AR24817"))
    }

    @Test
    fun `clearing removes them from the device`() {
        recent.remember(42, 1, "lav")

        recent.clear()

        assertTrue(recent.read(42, 1).isEmpty())
        assertTrue(preferences.storedValues.isEmpty())
    }

    @Test
    fun `unreadable data is dropped`() {
        recent.remember(42, 1, "lav")
        val key = preferences.storedValues.keys.single()
        preferences.edit().putString(key, "not base64 at all").apply()

        assertTrue(recent.read(42, 1).isEmpty())
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
