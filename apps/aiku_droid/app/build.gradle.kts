import com.google.firebase.appdistribution.gradle.firebaseAppDistribution
import java.util.Properties

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.compose)
    alias(libs.plugins.kotlin.serialization)
}

val firebaseConfigured: Boolean = listOf("google-services.json", "src/debug/google-services.json", "src/release/google-services.json")
    .any { file(it).exists() }

if (firebaseConfigured) {
    apply(plugin = libs.plugins.google.services.get().pluginId)
    apply(plugin = libs.plugins.firebase.appdistribution.get().pluginId)
    extensions.configure<com.google.gms.googleservices.GoogleServicesPlugin.GoogleServicesPluginConfig> {
        missingGoogleServicesStrategy = com.google.gms.googleservices.GoogleServicesPlugin.MissingGoogleServicesStrategy.WARN
    }
}

val localProperties = Properties().apply {
    rootProject.file("local.properties").takeIf { it.exists() }?.inputStream()?.use { load(it) }
}

fun localProperty(name: String, fallback: String = ""): String = localProperties.getProperty(name, fallback).trim()

fun localOrGradleProperty(name: String, fallback: String): String =
    localProperty(name)
        .ifEmpty { providers.gradleProperty(name).orNull.orEmpty().trim() }
        .ifEmpty { fallback }

val appVersionCode: Int = localOrGradleProperty("aiku.versionCode", "1").let { configured ->
    configured.toIntOrNull()?.takeIf { it > 0 }
        ?: throw GradleException("aiku.versionCode must be a positive whole number, not \"$configured\".")
}

val appVersionName: String = localOrGradleProperty("aiku.versionName", "0.1.0")

val releaseSigningKeys = listOf("aiku.releaseStoreFile", "aiku.releaseStorePassword", "aiku.releaseKeyAlias", "aiku.releaseKeyPassword")

val releaseStoreFile: File? = localProperty("aiku.releaseStoreFile").takeIf { it.isNotEmpty() }?.let { file(it) }

val releaseSigningProblem: String? = releaseSigningKeys.filter { localProperty(it).isEmpty() }.let { missingKeys ->
    when {
        missingKeys.isNotEmpty() -> "local.properties has no ${missingKeys.joinToString(", ")}"
        releaseStoreFile?.isFile != true -> "aiku.releaseStoreFile in local.properties points to no file"
        else -> null
    }
}

fun String.asBuildConfigString(): String =
    "\"" + replace("\\", "\\\\").replace("\"", "\\\"").replace("\r", "").replace("\n", "\\n") + "\""

val developmentHostAddress: String = localProperty("aiku.devHostAddress", "10.0.2.2").let { configured ->
    if (configured != "wsl") {
        configured
    } else {
        providers.exec { commandLine("wsl.exe", "hostname", "-I") }
            .standardOutput.asText.get()
            .trim()
            .split(Regex("\\s+"))
            .first()
    }
}

val developmentCaCertificate: String = localProperty("aiku.devCaCertPath")
    .takeIf { it.isNotEmpty() }
    ?.let { file(it) }
    ?.takeIf { it.isFile }
    ?.readText()
    .orEmpty()

android {
    namespace = "io.aiku.droid"
    compileSdk = 37

    defaultConfig {
        applicationId = "io.aiku.droid"
        minSdk = 26
        targetSdk = 37
        versionCode = appVersionCode
        versionName = appVersionName

        buildConfigField("String", "AIKU_APP_KEY", localProperty("aiku.appKey").asBuildConfigString())
        buildConfigField("String", "SENTRY_DSN", localProperty("aiku.sentryDsn").asBuildConfigString())
    }

    signingConfigs {
        if (releaseSigningProblem == null) {
            create("release") {
                storeFile = releaseStoreFile
                storePassword = localProperty("aiku.releaseStorePassword")
                keyAlias = localProperty("aiku.releaseKeyAlias")
                keyPassword = localProperty("aiku.releaseKeyPassword")
            }
        }
    }

    buildTypes {
        debug {
            buildConfigField("String", "AIKU_API_BASE_URL", localProperty("aiku.apiBaseUrl", "https://app.aiku.test").asBuildConfigString())
            buildConfigField("String", "DEVELOPMENT_HOST_ADDRESS", developmentHostAddress.asBuildConfigString())
            buildConfigField("String", "DEVELOPMENT_CA_CERTIFICATE", developmentCaCertificate.asBuildConfigString())
        }
        release {
            signingConfig = signingConfigs.findByName("release")
            buildConfigField("String", "AIKU_API_BASE_URL", "https://app.aiku.io".asBuildConfigString())
            buildConfigField("String", "DEVELOPMENT_HOST_ADDRESS", "".asBuildConfigString())
            buildConfigField("String", "DEVELOPMENT_CA_CERTIFICATE", "".asBuildConfigString())

            if (firebaseConfigured) {
                firebaseAppDistribution {
                    artifactType = "APK"
                    groups = localProperty("aiku.appDistributionGroups").ifEmpty { "warehouse-pilot" }
                    localProperty("aiku.appDistributionCredentials").takeIf { it.isNotEmpty() }?.let { serviceCredentialsFile = it }
                    file("release-notes.txt").takeIf { it.isFile }?.let { releaseNotesFile = it.path }
                }
            }
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }

    bundle {
        language {
            enableSplit = false
        }
    }
}

releaseSigningProblem?.let { problem ->
    val unsignedReleaseMessage = "Release signing is off ($problem): the release APK is unsigned, so it cannot be installed or sent to App Distribution. See Release in CLAUDE.md."
    tasks.named { it == "assembleRelease" }.configureEach {
        doLast { logger.warn(unsignedReleaseMessage) }
    }
    tasks.named { it == "appDistributionUploadRelease" }.configureEach {
        doFirst { throw GradleException(unsignedReleaseMessage) }
    }
}

dependencies {
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.activity.compose)
    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.compose.material3)
    implementation(libs.androidx.compose.ui.tooling.preview)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.navigation.compose)
    implementation(libs.okhttp)
    implementation(libs.okhttp.tls)
    implementation(libs.kotlinx.serialization.json)
    implementation(libs.androidx.camera.core)
    implementation(libs.androidx.camera.camera2)
    implementation(libs.androidx.camera.lifecycle)
    implementation(libs.androidx.camera.view)
    implementation(libs.androidx.camera.mlkit.vision)
    implementation(libs.mlkit.barcode.scanning)
    implementation(libs.zxing.core)
    implementation(libs.sentry.android)
    implementation(platform(libs.firebase.bom))
    implementation(libs.firebase.messaging)
    implementation(libs.androidx.work.runtime.ktx)
    debugImplementation(libs.androidx.compose.ui.tooling)

    testImplementation(libs.junit)
    testImplementation(libs.kotlinx.coroutines.test)
}
