package io.aiku.droid.data.cache

import android.content.ContentValues
import android.content.Context
import android.database.SQLException
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

class SqliteScreenCacheStore(context: Context) :
    SQLiteOpenHelper(context, DATABASE_NAME, null, DATABASE_VERSION),
    ScreenCacheStore {

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            "CREATE TABLE $TABLE (" +
                "$USER_ID INTEGER NOT NULL, " +
                "$WAREHOUSE_ID INTEGER NOT NULL, " +
                "$SCREEN_KEY TEXT NOT NULL, " +
                "$PAYLOAD BLOB NOT NULL, " +
                "$FETCHED_AT INTEGER NOT NULL, " +
                "$LAST_OPENED_AT INTEGER NOT NULL, " +
                "PRIMARY KEY ($USER_ID, $WAREHOUSE_ID, $SCREEN_KEY))",
        )
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        db.execSQL("DROP TABLE IF EXISTS $TABLE")
        onCreate(db)
    }

    override fun onDowngrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        onUpgrade(db, oldVersion, newVersion)
    }

    override fun read(key: ScreenCacheKey): StoredScreen? = guarded {
        readableDatabase.query(TABLE, arrayOf(PAYLOAD, FETCHED_AT, LAST_OPENED_AT), KEY_SELECTION, key.selectionArgs(), null, null, null).use { cursor ->
            if (cursor.moveToFirst()) {
                StoredScreen(key = key, payload = cursor.getBlob(0), fetchedAt = cursor.getLong(1), lastOpenedAt = cursor.getLong(2))
            } else {
                null
            }
        }
    }

    override fun write(screen: StoredScreen) {
        guarded {
            val values = ContentValues().apply {
                put(USER_ID, screen.key.userId)
                put(WAREHOUSE_ID, screen.key.warehouseId)
                put(SCREEN_KEY, screen.key.screen)
                put(PAYLOAD, screen.payload)
                put(FETCHED_AT, screen.fetchedAt)
                put(LAST_OPENED_AT, screen.lastOpenedAt)
            }
            writableDatabase.insertWithOnConflict(TABLE, null, values, SQLiteDatabase.CONFLICT_REPLACE)
        }
    }

    override fun markOpened(key: ScreenCacheKey, openedAt: Long) {
        guarded {
            val values = ContentValues().apply { put(LAST_OPENED_AT, openedAt) }
            writableDatabase.update(TABLE, values, KEY_SELECTION, key.selectionArgs())
        }
    }

    override fun keys(userId: Long): List<ScreenCacheKey> = guarded {
        readableDatabase.query(TABLE, arrayOf(WAREHOUSE_ID, SCREEN_KEY), "$USER_ID = ?", arrayOf(userId.toString()), null, null, null).use { cursor ->
            buildList {
                while (cursor.moveToNext()) {
                    add(ScreenCacheKey(userId = userId, warehouseId = cursor.getLong(0), screen = cursor.getString(1)))
                }
            }
        }
    }.orEmpty()

    override fun delete(key: ScreenCacheKey) {
        guarded { writableDatabase.delete(TABLE, KEY_SELECTION, key.selectionArgs()) }
    }

    override fun deleteOpenedBefore(openedAt: Long) {
        guarded { writableDatabase.delete(TABLE, "$LAST_OPENED_AT < ?", arrayOf(openedAt.toString())) }
    }

    override fun deleteOtherUsers(userId: Long) {
        guarded { writableDatabase.delete(TABLE, "$USER_ID != ?", arrayOf(userId.toString())) }
    }

    override fun deleteWarehousesOutside(userId: Long, warehouseIds: Set<Long>) {
        val kept = warehouseIds + CachedScreens.USER_SCOPED
        val placeholders = kept.joinToString(separator = ", ") { "?" }
        guarded {
            writableDatabase.delete(TABLE, "$USER_ID = ? AND $WAREHOUSE_ID NOT IN ($placeholders)", arrayOf(userId.toString()) + kept.map { it.toString() })
        }
    }

    override fun deleteAll() {
        guarded { writableDatabase.delete(TABLE, null, null) }
    }

    private inline fun <T> guarded(block: () -> T): T? = try {
        block()
    } catch (exception: SQLException) {
        null
    }

    private fun ScreenCacheKey.selectionArgs(): Array<String> = arrayOf(userId.toString(), warehouseId.toString(), screen)

    private companion object {
        const val DATABASE_NAME = "aiku_screen_cache.db"
        const val DATABASE_VERSION = 1
        const val TABLE = "screen_cache"
        const val USER_ID = "user_id"
        const val WAREHOUSE_ID = "warehouse_id"
        const val SCREEN_KEY = "screen_key"
        const val PAYLOAD = "payload"
        const val FETCHED_AT = "fetched_at"
        const val LAST_OPENED_AT = "last_opened_at"
        const val KEY_SELECTION = "$USER_ID = ? AND $WAREHOUSE_ID = ? AND $SCREEN_KEY = ?"
    }
}
