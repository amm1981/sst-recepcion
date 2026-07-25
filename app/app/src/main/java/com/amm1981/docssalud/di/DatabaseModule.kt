package com.amm1981.docssalud.di

import android.content.Context
import androidx.room.Room
import androidx.room.migration.Migration
import androidx.sqlite.db.SupportSQLiteDatabase
import com.amm1981.docssalud.data.local.AppDatabase
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object DatabaseModule {
    private val MIGRATION_3_4 = object : Migration(3, 4) {
        override fun migrate(db: SupportSQLiteDatabase) {
            db.execSQL("ALTER TABLE catalogs ADD COLUMN code TEXT")
        }
    }

    private val MIGRATION_4_5 = object : Migration(4, 5) {
        override fun migrate(db: SupportSQLiteDatabase) {
            db.execSQL("ALTER TABLE sync_queue ADD COLUMN documentDate TEXT NOT NULL DEFAULT ''")
        }
    }

    private val MIGRATION_5_6 = object : Migration(5, 6) {
        override fun migrate(db: SupportSQLiteDatabase) {
            db.execSQL("ALTER TABLE sync_queue ADD COLUMN workerPositionSnapshot TEXT")
            db.execSQL("ALTER TABLE sync_queue ADD COLUMN workerManagementIdSnapshot INTEGER")
            db.execSQL("ALTER TABLE sync_queue ADD COLUMN workerManagementNameSnapshot TEXT")
            db.execSQL("ALTER TABLE sync_queue ADD COLUMN workerSectorIdSnapshot INTEGER")
            db.execSQL("ALTER TABLE sync_queue ADD COLUMN workerSectorNameSnapshot TEXT")
        }
    }

    @Provides
    @Singleton
    fun provideAppDatabase(@ApplicationContext context: Context): AppDatabase {
        return Room.databaseBuilder(
            context,
            AppDatabase::class.java,
            "docssalud_db"
        ).addMigrations(MIGRATION_3_4, MIGRATION_4_5, MIGRATION_5_6).build()
    }

    @Provides
    fun provideWorkerDao(db: AppDatabase) = db.workerDao()

    @Provides
    fun provideCatalogDao(db: AppDatabase) = db.catalogDao()

    @Provides
    fun provideSyncQueueDao(db: AppDatabase) = db.syncQueueDao()
}
