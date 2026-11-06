plugins {
    id("com.android.application")
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.darmaltoon.businessos_pharmacy"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        isCoreLibraryDesugaringEnabled = true
    }

    defaultConfig {
        applicationId = "com.darmaltoon.businessos_pharmacy"
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    // Signing credentials are provided only at release time, never committed.
    val releaseStorePath = providers.environmentVariable("DARMALTOON_ANDROID_KEYSTORE").orNull
    val releaseStorePassword = providers.environmentVariable("DARMALTOON_ANDROID_STORE_PASSWORD").orNull
    val releaseKeyAlias = providers.environmentVariable("DARMALTOON_ANDROID_KEY_ALIAS").orNull
    val releaseKeyPassword = providers.environmentVariable("DARMALTOON_ANDROID_KEY_PASSWORD").orNull
    val releaseSigningReady = listOf(
        releaseStorePath, releaseStorePassword, releaseKeyAlias, releaseKeyPassword
    ).all { !it.isNullOrBlank() }

    signingConfigs {
        if (releaseSigningReady) {
            create("darmaltoonRelease") {
                storeFile = file(releaseStorePath!!)
                storePassword = releaseStorePassword
                keyAlias = releaseKeyAlias
                keyPassword = releaseKeyPassword
            }
        }
    }

    buildTypes {
        release {
            // An unsigned release may be compiled for verification, but cannot
            // be distributed until all four signing variables are supplied.
            if (releaseSigningReady) {
                signingConfig = signingConfigs.getByName("darmaltoonRelease")
            }
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.5")
}

flutter {
    source = "../.."
}
