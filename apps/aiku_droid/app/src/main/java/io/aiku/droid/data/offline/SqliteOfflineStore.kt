package io.aiku.droid.data.offline

import android.content.ContentValues
import android.content.Context
import android.database.Cursor
import android.database.SQLException
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

class SqliteOfflineStore(context: Context) :
    SQLiteOpenHelper(context, DATABASE_NAME, null, DATABASE_VERSION),
    OfflineStore {

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            "CREATE TABLE $DOWNLOADS (" +
                "$USER_ID INTEGER NOT NULL, " +
                "$RECORD_TYPE TEXT NOT NULL, " +
                "$RECORD_ID INTEGER NOT NULL, " +
                "$PAYLOAD BLOB NOT NULL, " +
                "$SEEN_AT INTEGER NOT NULL, " +
                "$DOWNLOADED_AT INTEGER NOT NULL, " +
                "$LAST_OPENED_AT INTEGER NOT NULL, " +
                "PRIMARY KEY ($USER_ID, $RECORD_TYPE, $RECORD_ID))",
        )
        db.execSQL(
            "CREATE TABLE $QUEUE (" +
                "$LOCAL_ID INTEGER PRIMARY KEY AUTOINCREMENT, " +
                "$USER_ID INTEGER NOT NULL, " +
                "$WAREHOUSE_ID INTEGER NOT NULL, " +
                "$OPERATION TEXT, " +
                "$RECORD_TYPE TEXT, " +
                "$RECORD_ID INTEGER, " +
                "$PAYLOAD BLOB, " +
                "$CLIENT_REQUEST_ID TEXT NOT NULL, " +
                "$PERFORMED_AT INTEGER NOT NULL, " +
                "$TIME_SOURCE_SERVER INTEGER NOT NULL, " +
                "$SEEN_AT INTEGER, " +
                "$SEQUENCE INTEGER, " +
                "$STATE TEXT NOT NULL, " +
                "$RESULT BLOB, " +
                "$SYNCED_AT INTEGER, " +
                "$CREATED_AT INTEGER NOT NULL)",
        )
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) = Unit

    override fun readDownload(key: DownloadKey): StoredDownload? = guarded {
        readableDatabase.query(DOWNLOADS, arrayOf(PAYLOAD, SEEN_AT, DOWNLOADED_AT, LAST_OPENED_AT), DOWNLOAD_SELECTION, key.selectionArgs(), null, null, null)
            .use { cursor ->
                if (cursor.moveToFirst()) {
                    StoredDownload(key = key, payload = cursor.getBlob(0), seenAt = cursor.getLong(1), downloadedAt = cursor.getLong(2), lastOpenedAt = cursor.getLong(3))
                } else {
                    null
                }
            }
    }

    override fun writeDownload(download: StoredDownload) {
        guarded {
            val values = ContentValues().apply {
                put(USER_ID, download.key.userId)
                put(RECORD_TYPE, download.key.type.name)
                put(RECORD_ID, download.key.id)
                put(PAYLOAD, download.payload)
                put(SEEN_AT, download.seenAt)
                put(DOWNLOADED_AT, download.downloadedAt)
                put(LAST_OPENED_AT, download.lastOpenedAt)
            }
            writableDatabase.insertWithOnConflict(DOWNLOADS, null, values, SQLiteDatabase.CONFLICT_REPLACE)
        }
    }

    override fun markDownloadOpened(key: DownloadKey, openedAt: Long) {
        guarded { writableDatabase.update(DOWNLOADS, ContentValues().apply { put(LAST_OPENED_AT, openedAt) }, DOWNLOAD_SELECTION, key.selectionArgs()) }
    }

    override fun deleteDownload(key: DownloadKey) {
        guarded { writableDatabase.delete(DOWNLOADS, DOWNLOAD_SELECTION, key.selectionArgs()) }
    }

    override fun deleteDownloadsOpenedBefore(openedAt: Long) {
        guarded { writableDatabase.delete(DOWNLOADS, "$LAST_OPENED_AT < ?", arrayOf(openedAt.toString())) }
    }

    override fun deleteDownloadsOfOtherUsers(userId: Long) {
        guarded { writableDatabase.delete(DOWNLOADS, "$USER_ID != ?", arrayOf(userId.toString())) }
    }

    override fun deleteAllDownloads() {
        guarded { writableDatabase.delete(DOWNLOADS, null, null) }
    }

    override fun actions(): List<StoredAction> = guarded {
        readableDatabase.query(QUEUE, QUEUE_COLUMNS, null, null, null, null, "$LOCAL_ID ASC").use { cursor ->
            buildList {
                while (cursor.moveToNext()) {
                    add(cursor.toAction())
                }
            }
        }
    }.orEmpty()

    override fun insertAction(action: StoredAction): Long = writableDatabase.insertOrThrow(QUEUE, null, action.values())

    override fun updateAction(action: StoredAction) {
        guarded { writableDatabase.update(QUEUE, action.values(), "$LOCAL_ID = ?", arrayOf(action.localId.toString())) }
    }

    override fun deleteAction(localId: Long) {
        guarded { writableDatabase.delete(QUEUE, "$LOCAL_ID = ?", arrayOf(localId.toString())) }
    }

    private fun StoredAction.values(): ContentValues = ContentValues().apply {
        put(USER_ID, userId)
        put(WAREHOUSE_ID, warehouseId)
        put(OPERATION, operation)
        put(RECORD_TYPE, recordType)
        put(RECORD_ID, recordId)
        put(PAYLOAD, payload)
        put(CLIENT_REQUEST_ID, clientRequestId)
        put(PERFORMED_AT, performedAt)
        put(TIME_SOURCE_SERVER, if (timeSourceIsServer) 1 else 0)
        put(SEEN_AT, seenAt)
        put(SEQUENCE, sequence)
        put(STATE, state)
        put(RESULT, result)
        put(SYNCED_AT, syncedAt)
        put(CREATED_AT, createdAt)
    }

    private fun Cursor.toAction(): StoredAction = StoredAction(
        localId = getLong(0),
        userId = getLong(1),
        warehouseId = getLong(2),
        operation = stringOrNull(3),
        recordType = stringOrNull(4),
        recordId = longOrNull(5),
        payload = if (isNull(6)) null else getBlob(6),
        clientRequestId = getString(7),
        performedAt = getLong(8),
        timeSourceIsServer = getInt(9) == 1,
        seenAt = longOrNull(10),
        sequence = longOrNull(11),
        state = getString(12),
        result = if (isNull(13)) null else getBlob(13),
        syncedAt = longOrNull(14),
        createdAt = getLong(15),
    )

    private fun Cursor.stringOrNull(index: Int): String? = if (isNull(index)) null else getString(index)

    private fun Cursor.longOrNull(index: Int): Long? = if (isNull(index)) null else getLong(index)

    private inline fun <T> guarded(block: () -> T): T? = try {
        block()
    } catch (exception: SQLException) {
        null
    }

    private fun DownloadKey.selectionArgs(): Array<String> = arrayOf(userId.toString(), type.name, id.toString())

    private companion object {
        const val DATABASE_NAME = "aiku_offline.db"
        const val DATABASE_VERSION = 1
        const val DOWNLOADS = "downloads"
        const val QUEUE = "queue"
        const val LOCAL_ID = "local_id"
        const val USER_ID = "user_id"
        const val WAREHOUSE_ID = "warehouse_id"
        const val OPERATION = "operation"
        const val RECORD_TYPE = "record_type"
        const val RECORD_ID = "record_id"
        const val PAYLOAD = "payload"
        const val CLIENT_REQUEST_ID = "client_request_id"
        const val PERFORMED_AT = "performed_at"
        const val TIME_SOURCE_SERVER = "time_source_server"
        const val SEEN_AT = "seen_at"
        const val SEQUENCE = "sequence"
        const val STATE = "state"
        const val RESULT = "result"
        const val SYNCED_AT = "synced_at"
        const val CREATED_AT = "created_at"
        const val DOWNLOADED_AT = "downloaded_at"
        const val LAST_OPENED_AT = "last_opened_at"
        const val DOWNLOAD_SELECTION = "$USER_ID = ? AND $RECORD_TYPE = ? AND $RECORD_ID = ?"
        val QUEUE_COLUMNS = arrayOf(
            LOCAL_ID, USER_ID, WAREHOUSE_ID, OPERATION, RECORD_TYPE, RECORD_ID, PAYLOAD, CLIENT_REQUEST_ID,
            PERFORMED_AT, TIME_SOURCE_SERVER, SEEN_AT, SEQUENCE, STATE, RESULT, SYNCED_AT, CREATED_AT,
        )
    }
}
