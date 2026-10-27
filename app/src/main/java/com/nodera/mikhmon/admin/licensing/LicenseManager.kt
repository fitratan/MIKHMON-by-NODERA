package com.nodera.mikhmon.admin.licensing

import android.annotation.SuppressLint
import android.content.Context
import android.content.SharedPreferences
import android.os.Build
import android.provider.Settings
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.nio.charset.StandardCharsets
import java.security.MessageDigest
import java.util.Locale
import java.util.concurrent.TimeUnit
import javax.crypto.Mac
import javax.crypto.spec.SecretKeySpec

enum class LicenseState {
    ACTIVE_LIFETIME,
    ACTIVE_SUBSCRIPTION,
    ACTIVE_TRIAL,
    EXPIRED,
    UNLICENSED,
    CLOCK_TAMPERED
}

data class LicenseInfo(
    val state: LicenseState,
    val hwid: String,
    val licenseKey: String,
    val expiresAt: Long = 0L, // 0 means lifetime
    val remainingDays: Int = 0,
    val message: String = ""
)

class LicenseManager(private val context: Context) {

    private val prefs: SharedPreferences =
        context.getSharedPreferences("nodera_mikhmon_license_vault", Context.MODE_PRIVATE)

    private val httpClient = OkHttpClient.Builder()
        .connectTimeout(8, TimeUnit.SECONDS)
        .readTimeout(8, TimeUnit.SECONDS)
        .build()

    companion object {
        private const val SECRET_SALT = "NODERA_OFFLINE_SECRET_2026"
        private const val PRIMARY_API = "https://panel.dgtlnetsolution.com/api/desktop-license"
        private const val FALLBACK_API = "https://digitalnet.dgtlnetsolution.com/api/desktop-license"

        private const val KEY_HWID = "ndr_cached_hwid"
        private const val KEY_LICENSE_KEY = "ndr_license_key"
        private const val KEY_EXPIRES_AT = "ndr_expires_at"
        private const val KEY_LICENSE_TYPE = "ndr_license_type"
        private const val KEY_LOCAL_SIG = "ndr_local_sig"
        private const val KEY_LAST_CLOCK = "ndr_last_clock"
        private const val KEY_TRIAL_ACTIVATED = "ndr_trial_activated"
        private const val KEY_TRIAL_EXPIRES = "ndr_trial_expires"
        private const val KEY_TRIAL_SIG = "ndr_trial_sig"

        const val ADMIN_WHATSAPP_NUMBER = "6287816666191"
    }

    /**
     * Unique Hardware ID generated from ANDROID_ID and device hardware descriptors.
     * Formatted as NDR-AND-XXXX-XXXX-XXXX
     */
    @SuppressLint("HardwareIds")
    fun getHardwareId(): String {
        val cached = prefs.getString(KEY_HWID, null)
        if (!cached.isNullOrEmpty()) return cached

        val androidId = try {
            Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID) ?: "UNKNOWN_DEV"
        } catch (e: Exception) {
            "UNKNOWN_DEV"
        }

        val raw = "NODERA-ANDROID-LIC:$androidId:${Build.BOARD}:${Build.BRAND}:${Build.MODEL}"
        val sha = sha256(raw)
        val part1 = sha.substring(0, 4).uppercase(Locale.US)
        val part2 = sha.substring(4, 8).uppercase(Locale.US)
        val part3 = sha.substring(8, 12).uppercase(Locale.US)
        val hwid = "NDR-HWID-$part1-$part2-$part3"

        prefs.edit().putString(KEY_HWID, hwid).apply()
        return hwid
    }

    /**
     * Get the WhatsApp link to order or verify license with admin
     */
    fun getAdminWhatsAppUrl(): String {
        val hwid = getHardwareId()
        val text = "Halo Admin Nodera, saya ingin order/aktivasi lisensi MIKHMON Android Admin.%0AHWID: $hwid"
        return "https://wa.me/$ADMIN_WHATSAPP_NUMBER?text=$text"
    }

    /**
     * Check current license state with clock anti-rollback verification
     */
    fun checkLicense(): LicenseInfo {
        val hwid = getHardwareId()
        val nowSec = System.currentTimeMillis() / 1000L

        // Anti-rollback check
        val lastClock = prefs.getLong(KEY_LAST_CLOCK, 0L)
        if (lastClock > 0 && nowSec < (lastClock - 3600)) {
            return LicenseInfo(
                state = LicenseState.CLOCK_TAMPERED,
                hwid = hwid,
                licenseKey = "",
                message = "Waktu perangkat dimundurkan! Harap sesuaikan tanggal & jam sistem."
            )
        }
        prefs.edit().putLong(KEY_LAST_CLOCK, nowSec).apply()

        val licenseKey = prefs.getString(KEY_LICENSE_KEY, "") ?: ""
        val type = prefs.getString(KEY_LICENSE_TYPE, "") ?: ""
        val expiresAt = prefs.getLong(KEY_EXPIRES_AT, 0L)
        val storedSig = prefs.getString(KEY_LOCAL_SIG, "") ?: ""

        // Check if there is an active permanent or subscription license
        if (licenseKey.isNotEmpty() && storedSig.isNotEmpty()) {
            val expectedSig = hmacSha256("$hwid:$licenseKey:$type:$expiresAt", SECRET_SALT)
            if (expectedSig.equals(storedSig, ignoreCase = true)) {
                if (type == "LIFETIME") {
                    return LicenseInfo(
                        state = LicenseState.ACTIVE_LIFETIME,
                        hwid = hwid,
                        licenseKey = licenseKey,
                        expiresAt = 0L,
                        message = "Lisensi Seumur Hidup (Lifetime) Terverifikasi"
                    )
                } else if (expiresAt > nowSec) {
                    val remainingDays = ((expiresAt - nowSec) / 86400L).toInt()
                    return LicenseInfo(
                        state = LicenseState.ACTIVE_SUBSCRIPTION,
                        hwid = hwid,
                        licenseKey = licenseKey,
                        expiresAt = expiresAt,
                        remainingDays = remainingDays,
                        message = "Lisensi Berlangganan Aktif ($remainingDays hari tersisa)"
                    )
                } else {
                    return LicenseInfo(
                        state = LicenseState.EXPIRED,
                        hwid = hwid,
                        licenseKey = licenseKey,
                        expiresAt = expiresAt,
                        message = "Masa aktif lisensi telah habis!"
                    )
                }
            }
        }

        // Check Trial mode
        val trialActivated = prefs.getBoolean(KEY_TRIAL_ACTIVATED, false)
        if (trialActivated) {
            val trialExpires = prefs.getLong(KEY_TRIAL_EXPIRES, 0L)
            val trialSig = prefs.getString(KEY_TRIAL_SIG, "") ?: ""
            val expectedTrialSig = hmacSha256("$hwid:TRIAL:$trialExpires", SECRET_SALT)

            if (trialSig.equals(expectedTrialSig, ignoreCase = true)) {
                if (trialExpires > nowSec) {
                    val remainingDays = Math.max(1, ((trialExpires - nowSec) / 86400L).toInt())
                    return LicenseInfo(
                        state = LicenseState.ACTIVE_TRIAL,
                        hwid = hwid,
                        licenseKey = "TRIAL-MODE",
                        expiresAt = trialExpires,
                        remainingDays = remainingDays,
                        message = "Trial Gratis Aktif ($remainingDays hari lagi)"
                    )
                } else {
                    return LicenseInfo(
                        state = LicenseState.EXPIRED,
                        hwid = hwid,
                        licenseKey = "TRIAL-EXPIRED",
                        expiresAt = trialExpires,
                        message = "Masa uji coba (Trial 3 Hari) telah berakhir. Silakan beli lisensi resmi."
                    )
                }
            }
        }

        return LicenseInfo(
            state = LicenseState.UNLICENSED,
            hwid = hwid,
            licenseKey = "",
            message = "Aplikasi belum terlisensi. Masukkan Serial Key atau aktifkan Trial."
        )
    }

    /**
     * Activate trial if eligible (validated with Cloud Panel)
     */
    suspend fun activateTrial(): Pair<Boolean, String> = withContext(Dispatchers.IO) {
        val hwid = getHardwareId()
        val already = prefs.getBoolean(KEY_TRIAL_ACTIVATED, false)
        if (already) {
            return@withContext Pair(false, "Perangkat ini sudah pernah menggunakan uji coba (Trial)!")
        }

        // Online Cloud API Trial Verification
        val endpoints = listOf(PRIMARY_API + "/trial", FALLBACK_API + "/trial")
        val payload = JSONObject().apply {
            put("action", "trial")
            put("hwid", hwid)
            put("device_name", "${Build.MANUFACTURER} ${Build.MODEL}")
            put("os_info", "Android ${Build.VERSION.RELEASE} (API ${Build.VERSION.SDK_INT})")
        }

        val mediaType = "application/json; charset=utf-8".toMediaType()
        val body = payload.toString().toRequestBody(mediaType)

        for (url in endpoints) {
            try {
                val request = Request.Builder()
                    .url(url)
                    .post(body)
                    .header("User-Agent", "MikhmonAndroidAdmin/3.4.0")
                    .build()

                httpClient.newCall(request).execute().use { response ->
                    val respBody = response.body?.string() ?: ""
                    if (respBody.isNotEmpty()) {
                        val json = JSONObject(respBody)
                        val success = json.optBoolean("success", false) || json.optBoolean("valid", false)
                        val code = json.optString("code", "")
                        val msg = json.optString("message", "")

                        if (success) {
                            val data = json.optJSONObject("data")
                            val expSec = data?.optLong("expires_at_timestamp") ?: (System.currentTimeMillis() / 1000L + (7 * 86400L))
                            val trialSig = hmacSha256("$hwid:TRIAL:$expSec", SECRET_SALT)

                            prefs.edit()
                                .putBoolean(KEY_TRIAL_ACTIVATED, true)
                                .putLong(KEY_TRIAL_EXPIRES, expSec)
                                .putString(KEY_TRIAL_SIG, trialSig)
                                .apply()

                            return@withContext Pair(true, "Masa percobaan gratis berhasil diaktifkan!")
                        } else if (response.code == 403 || code == "TRIAL_ALREADY_USED" || code == "LICENSE_EXPIRED") {
                            prefs.edit()
                                .putBoolean(KEY_TRIAL_ACTIVATED, true)
                                .putLong(KEY_TRIAL_EXPIRES, 1L)
                                .apply()
                            return@withContext Pair(false, if (msg.isNotEmpty()) msg else "Perangkat ini sudah pernah menggunakan masa percobaan / lisensi telah berakhir.")
                        }
                    }
                }
            } catch (e: Exception) {
                // Try next endpoint
            }
        }

        return@withContext Pair(false, "Aktivasi masa percobaan (Trial) memerlukan koneksi internet untuk validasi perangkat.")
    }

    /**
     * Activate using License Key (Offline HMAC check + Cloud Sync fallback)
     */
    suspend fun activateLicense(inputKey: String): Pair<Boolean, String> = withContext(Dispatchers.IO) {
        val cleanKey = inputKey.trim().uppercase(Locale.US)
        if (cleanKey.isEmpty()) {
            return@withContext Pair(false, "Serial Key tidak boleh kosong!")
        }

        val hwid = getHardwareId()

        // 1. Offline Verification Check
        // Format: NDR-LIC-XXXXXXXXXXXXXXXX (16 hex chars HMAC of HWID + ":LIFETIME")
        val expectedOfflineLifetimeSig = hmacSha256("$hwid:LIFETIME", SECRET_SALT).take(16).uppercase(Locale.US)
        val expectedOfflineKey = "NDR-LIC-$expectedOfflineLifetimeSig"

        if (cleanKey == expectedOfflineKey || cleanKey == expectedOfflineLifetimeSig) {
            saveLicense(cleanKey, "LIFETIME", 0L, hwid)
            return@withContext Pair(true, "Aktivasi Lisensi Offline Lifetime Berhasil!")
        }

        // Check if key is formatted as NDR-EXP-<timestamp>-<sig>
        if (cleanKey.startsWith("NDR-EXP-")) {
            val parts = cleanKey.split("-")
            if (parts.size >= 4) {
                val expSec = parts[2].toLongOrNull()
                val givenSig = parts[3]
                if (expSec != null && expSec > (System.currentTimeMillis() / 1000L)) {
                    val expectedSubSig = hmacSha256("$hwid:EXP:$expSec", SECRET_SALT).take(12).uppercase(Locale.US)
                    if (givenSig.equals(expectedSubSig, ignoreCase = true)) {
                        saveLicense(cleanKey, "SUBSCRIPTION", expSec, hwid)
                        return@withContext Pair(true, "Aktivasi Lisensi Berlangganan Berhasil!")
                    }
                }
            }
        }

        // 2. Online Cloud API Verification (panel.dgtlnetsolution.com)
        val cloudResult = verifyWithCloud(hwid, cleanKey)
        if (cloudResult.first) {
            return@withContext cloudResult
        }

        Pair(false, cloudResult.second)
    }

    private fun verifyWithCloud(hwid: String, licenseKey: String): Pair<Boolean, String> {
        val endpoints = listOf(PRIMARY_API, FALLBACK_API)
        val payload = JSONObject().apply {
            put("action", "activate")
            put("hwid", hwid)
            put("license_key", licenseKey)
            put("app", "mikhmon_android_admin")
            put("version", "3.2.0")
            put("platform", "Android " + Build.VERSION.RELEASE)
        }

        val mediaType = "application/json; charset=utf-8".toMediaType()
        val body = payload.toString().toRequestBody(mediaType)

        for (url in endpoints) {
            try {
                val request = Request.Builder()
                    .url(url)
                    .post(body)
                    .header("User-Agent", "MikhmonAndroidAdmin/3.2.0")
                    .build()

                httpClient.newCall(request).execute().use { response ->
                    val respBody = response.body?.string() ?: ""
                    if (response.isSuccessful && respBody.isNotEmpty()) {
                        val json = JSONObject(respBody)
                        val success = json.optBoolean("success", false) || json.optBoolean("valid", false)
                        if (success) {
                            val type = json.optString("type", "LIFETIME").uppercase(Locale.US)
                            val expiresAt = json.optLong("expires_at", 0L)
                            saveLicense(licenseKey, type, expiresAt, hwid)
                            return Pair(true, "Aktivasi Cloud Berhasil!")
                        } else {
                            val msg = json.optString("message", "Serial Key tidak valid untuk perangkat ini!")
                            return Pair(false, msg)
                        }
                    }
                }
            } catch (e: Exception) {
                // Continue to next endpoint or return network error
            }
        }

        return Pair(false, "Gagal menghubungi server lisensi dan Serial Key offline tidak cocok.")
    }

    private fun saveLicense(licenseKey: String, type: String, expiresAt: Long, hwid: String) {
        val sig = hmacSha256("$hwid:$licenseKey:$type:$expiresAt", SECRET_SALT)
        prefs.edit()
            .putString(KEY_LICENSE_KEY, licenseKey)
            .putString(KEY_LICENSE_TYPE, type)
            .putLong(KEY_EXPIRES_AT, expiresAt)
            .putString(KEY_LOCAL_SIG, sig)
            .apply()
    }

    private fun sha256(input: String): String {
        val md = MessageDigest.getInstance("SHA-256")
        val bytes = md.digest(input.toByteArray(StandardCharsets.UTF_8))
        return bytes.joinToString("") { "%02x".format(it) }
    }

    private fun hmacSha256(data: String, key: String): String {
        val algorithm = "HmacSHA256"
        val mac = Mac.getInstance(algorithm)
        val secretKeySpec = SecretKeySpec(key.toByteArray(StandardCharsets.UTF_8), algorithm)
        mac.init(secretKeySpec)
        val rawHmac = mac.doFinal(data.toByteArray(StandardCharsets.UTF_8))
        return rawHmac.joinToString("") { "%02x".format(it) }
    }
}
