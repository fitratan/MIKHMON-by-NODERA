package com.nodera.mikhmon.admin.data

import android.content.Context
import android.content.SharedPreferences
import com.nodera.mikhmon.admin.router.RouterOsClient

data class RouterConfig(
    val host: String = "192.168.88.1",
    val port: Int = 8728,
    val username: String = "admin",
    val password: String = "",
    val hotspotName: String = "NODERA HOTSPOT",
    val dnsName: String = "hotspot.local",
    val printerAddress: String = "",
    val printerName: String = ""
)

class RouterConfigManager(context: Context) {

    private val prefs: SharedPreferences =
        context.getSharedPreferences("nodera_mikhmon_router_config", Context.MODE_PRIVATE)

    companion object {
        private const val KEY_HOST = "router_host"
        private const val KEY_PORT = "router_port"
        private const val KEY_USER = "router_user"
        private const val KEY_PASS = "router_pass"
        private const val KEY_HS_NAME = "hotspot_name"
        private const val KEY_DNS_NAME = "dns_name"
        private const val KEY_PRINTER_ADDR = "printer_address"
        private const val KEY_PRINTER_NAME = "printer_name"

        // Global shared RouterOsClient session
        @Volatile
        var sharedClient: RouterOsClient? = null
            private set

        fun getOrCreateClient(config: RouterConfig): RouterOsClient {
            val existing = sharedClient
            if (existing != null) return existing
            val client = RouterOsClient(config.host, config.port)
            sharedClient = client
            return client
        }

        fun updateClient(config: RouterConfig): RouterOsClient {
            sharedClient?.close()
            val client = RouterOsClient(config.host, config.port)
            sharedClient = client
            return client
        }
    }

    fun loadConfig(): RouterConfig {
        return RouterConfig(
            host = prefs.getString(KEY_HOST, "192.168.88.1") ?: "192.168.88.1",
            port = prefs.getInt(KEY_PORT, 8728),
            username = prefs.getString(KEY_USER, "admin") ?: "admin",
            password = prefs.getString(KEY_PASS, "") ?: "",
            hotspotName = prefs.getString(KEY_HS_NAME, "NODERA HOTSPOT") ?: "NODERA HOTSPOT",
            dnsName = prefs.getString(KEY_DNS_NAME, "hotspot.local") ?: "hotspot.local",
            printerAddress = prefs.getString(KEY_PRINTER_ADDR, "") ?: "",
            printerName = prefs.getString(KEY_PRINTER_NAME, "") ?: ""
        )
    }

    fun saveConfig(config: RouterConfig) {
        prefs.edit()
            .putString(KEY_HOST, config.host)
            .putInt(KEY_PORT, config.port)
            .putString(KEY_USER, config.username)
            .putString(KEY_PASS, config.password)
            .putString(KEY_HS_NAME, config.hotspotName)
            .putString(KEY_DNS_NAME, config.dnsName)
            .putString(KEY_PRINTER_ADDR, config.printerAddress)
            .putString(KEY_PRINTER_NAME, config.printerName)
            .apply()
    }
}
