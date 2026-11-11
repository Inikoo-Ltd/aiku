import java.util.Properties

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.compose)
    alias(libs.plugins.kotlin.serialization)
}

val localProperties = Properties().apply {
    rootProject.file("local.properties").takeIf { it.exists() }?.inputStream()?.use { load(it) }
}

fun localProperty(name: String, fallback: String = ""): String = localProperties.getProperty(name, fallback).trim()

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
        versionCode = 1
        versionName = "0.1.0"

        buildConfigField("String", "AIKU_APP_KEY", localProperty("aiku.appKey").asBuildConfigString())
    }

    buildTypes {
        debug {
            buildConfigField("String", "AIKU_API_BASE_URL", localProperty("aiku.apiBaseUrl", "https://app.aiku.test").asBuildConfigString())
            buildConfigField("String", "DEVELOPMENT_HOST_ADDRESS", developmentHostAddress.asBuildConfigString())
            buildConfigField("String", "DEVELOPMENT_CA_CERTIFICATE", developmentCaCertificate.asBuildConfigString())
        }
        release {
            buildConfigField("String", "AIKU_API_BASE_URL", "https://app.aiku.io".asBuildConfigString())
            buildConfigField("String", "DEVELOPMENT_HOST_ADDRESS", "".asBuildConfigString())
            buildConfigField("String", "DEVELOPMENT_CA_CERTIFICATE", "".asBuildConfigString())
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
    debugImplementation(libs.androidx.compose.ui.tooling)

    testImplementation(libs.junit)
    testImplementation(libs.kotlinx.coroutines.test)
}
