package com.amm1981.docssalud.di

import android.content.Context
import androidx.room.Room
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

    @Provides
    @Singleton
    fun provideAppDatabase(@ApplicationContext context: Context): AppDatabase {
        return Room.databaseBuilder(
            context,
            AppDatabase::class.java,
            "docssalud_db"
        ).fallbackToDestructiveMigration().build()
    }

    @Provides
    fun provideWorkerDao(db: AppDatabase) = db.workerDao()

    @Provides
    fun provideCatalogDao(db: AppDatabase) = db.catalogDao()

    @Provides
    fun provideSyncQueueDao(db: AppDatabase) = db.syncQueueDao()
}
