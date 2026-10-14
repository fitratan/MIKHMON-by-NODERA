/**
 * ============================================================================
 * MIKHMON THERMAL PRINTER ENGINE (ESC/POS & RAWBT)
 * Universal Driver for 58mm & 80mm POS Printers
 * (Panda, VSC, Iware, Eppos, GOOJPRT, RPP02N, POS-5802, MPT-II, PT-210, etc.)
 * Supports Web Bluetooth (BLE) & RawBT Android Intent (Bluetooth Classic/USB)
 * ============================================================================
 */
(function(window) {
  'use strict';

  var KNOWN_SERVICES = [
    '000018f0-0000-1000-8000-00805f9b34fb', // Standard POS Print
    '49535343-fe7d-4ae5-8fa9-9fafd205e455', // ISSC Transparent Serial
    'e7810a71-73ae-499d-8c15-faa9aef0c3f2', // Vendor Serial
    '0000ae00-0000-1000-8000-00805f9b34fb', // Vendor 2
    '0000ae30-0000-1000-8000-00805f9b34fb', // Vendor 3
    '0000ff00-0000-1000-8000-00805f9b34fb', // Vendor 4
    '0000fee7-0000-1000-8000-00805f9b34fb', // Vendor 5
    '0000ffff-0000-1000-8000-00805f9b34fb'  // Generic
  ];

  var PRINTER_PREFIXES = [
    'POS', 'pos', 'Print', 'print', 'RPP', 'rpp', 'MPT', 'mpt', 'MTP', 'mtp',
    'PT-', 'pt-', 'PT', 'pt', 'VSC', 'vsc', 'Panda', 'panda', 'Iware', 'iware',
    'Eppos', 'eppos', 'Zjiang', 'zjiang', 'GOOJPRT', 'goojprt', 'Thermal', 'thermal',
    'XP-', 'xp-', 'XP', 'JP-', 'JP', 'EP-', 'EP', 'Bluetooth', 'bluetooth',
    'Mobile', 'ESC', 'SPP', '58', '80'
  ];

  function isUserCancelled(err) {
    if (!err) return false;
    var name = err.name || '';
    var msg = (err.message || '').toLowerCase();
    return name === 'NotFoundError' || name === 'AbortError' ||
           msg.indexOf('user cancelled') !== -1 ||
           msg.indexOf('cancelled') !== -1;
  }

  var MikhmonThermal = {
    device: null,
    server: null,
    characteristic: null,
    deviceName: '',
    isConnected: false,
    paperSize: localStorage.getItem('mikhmon_thermal_paper') || '58',
    printMethod: localStorage.getItem('mikhmon_thermal_method') || 'bluetooth',
    footerText: localStorage.getItem('mikhmon_thermal_footer') || 'Terima kasih atas pembelian Anda',
    autoCut: localStorage.getItem('mikhmon_thermal_autocut') !== 'no',
    listeners: [],

    isSupported: function() {
      return (typeof navigator !== 'undefined' && 'bluetooth' in navigator);
    },

    isAndroid: function() {
      return (typeof navigator !== 'undefined' && /Android/i.test(navigator.userAgent));
    },

    addListener: function(fn) {
      if (typeof fn === 'function') {
        this.listeners.push(fn);
      }
    },

    notifyListeners: function(status, msg) {
      for (var i = 0; i < this.listeners.length; i++) {
        try {
          this.listeners[i](status, msg, this);
        } catch (e) {
          console.error('Thermal listener error:', e);
        }
      }
    },

    /**
     * Connect to Bluetooth device
     * @param {string} mode - 'printers' (default, filtered) or 'all' (acceptAllDevices)
     */
    connect: async function(mode) {
      mode = mode || 'printers';
      var self = this;
      if (!self.isSupported()) {
        throw new Error('Web Bluetooth tidak didukung pada browser ini. Harap gunakan Google Chrome pada Android/PC.');
      }

      try {
        self.notifyListeners('connecting', 'Mencari printer Bluetooth di sekitar...');

        var reqOptions = {};
        if (mode === 'all') {
          reqOptions = {
            acceptAllDevices: true,
            optionalServices: KNOWN_SERVICES
          };
        } else {
          reqOptions = {
            filters: PRINTER_PREFIXES.map(function(pfx) { return { namePrefix: pfx }; }),
            optionalServices: KNOWN_SERVICES
          };
        }

        var device = await navigator.bluetooth.requestDevice(reqOptions);
        if (!device) {
          self.notifyListeners('idle', 'Tidak ada perangkat dipilih');
          return { cancelled: true };
        }

        self.device = device;
        self.deviceName = device.name || 'Printer Bluetooth (' + (device.id ? device.id.slice(0, 5) : 'POS') + ')';

        device.addEventListener('gattserverdisconnected', function() {
          self.isConnected = false;
          self.server = null;
          self.characteristic = null;
          self.notifyListeners('disconnected', 'Printer terputus');
        });

        self.notifyListeners('connecting', 'Menghubungkan ke ' + self.deviceName + '...');
        var server = await device.gatt.connect();
        self.server = server;

        var charFound = null;
        for (var i = 0; i < KNOWN_SERVICES.length; i++) {
          try {
            var service = await server.getPrimaryService(KNOWN_SERVICES[i]);
            if (service) {
              var chars = await service.getCharacteristics();
              for (var j = 0; j < chars.length; j++) {
                var c = chars[j];
                if (c.properties.write || c.properties.writeWithoutResponse) {
                  charFound = c;
                  break;
                }
              }
              if (charFound) break;
            }
          } catch (err) {}
        }

        // Fallback: iterate all services
        if (!charFound) {
          try {
            var allServices = await server.getPrimaryServices();
            for (var k = 0; k < allServices.length; k++) {
              try {
                var sChars = await allServices[k].getCharacteristics();
                for (var m = 0; m < sChars.length; m++) {
                  var sc = sChars[m];
                  if (sc.properties.write || sc.properties.writeWithoutResponse) {
                    charFound = sc;
                    break;
                  }
                }
                if (charFound) break;
              } catch (e) {}
            }
          } catch (e) {}
        }

        if (!charFound) {
          throw new Error('Gagal menemukan karakteristik cetak (write) pada printer ini.');
        }

        self.characteristic = charFound;
        self.isConnected = true;

        localStorage.setItem('mikhmon_thermal_device_name', self.deviceName);
        localStorage.setItem('mikhmon_thermal_device_id', device.id);

        self.notifyListeners('connected', 'Terhubung ke ' + self.deviceName);
        return { success: true, name: self.deviceName };
      } catch (err) {
        if (isUserCancelled(err)) {
          self.notifyListeners('idle', 'Pemindaian dibatalkan');
          return { cancelled: true };
        }
        self.isConnected = false;
        self.notifyListeners('error', err.message || 'Gagal koneksi Bluetooth');
        throw err;
      }
    },

    disconnect: function() {
      if (this.device && this.device.gatt && this.device.gatt.connected) {
        try {
          this.device.gatt.disconnect();
        } catch (e) {}
      }
      this.isConnected = false;
      this.server = null;
      this.characteristic = null;
      this.notifyListeners('disconnected', 'Printer diputuskan');
    },

    sendRawBytes: async function(bytes) {
      if (!this.isConnected || !this.characteristic) {
        throw new Error('Printer thermal belum terhubung. Silakan sambungkan terlebih dahulu.');
      }

      var chunkSize = 100;
      var uint8 = (bytes instanceof Uint8Array) ? bytes : new Uint8Array(bytes);

      for (var offset = 0; offset < uint8.length; offset += chunkSize) {
        var chunk = uint8.slice(offset, offset + chunkSize);
        if (this.characteristic.writeValueWithoutResponse) {
          await this.characteristic.writeValueWithoutResponse(chunk);
        } else {
          await this.characteristic.writeValue(chunk);
        }
        await new Promise(function(resolve) { setTimeout(resolve, 25); });
      }
      return true;
    },

    /**
     * Kirim data biner ESC/POS via RawBT Intent (Android)
     */
    printRawBT: function(uint8Bytes) {
      var bin = '';
      var bytes = (uint8Bytes instanceof Uint8Array) ? uint8Bytes : new Uint8Array(uint8Bytes);
      for (var i = 0; i < bytes.length; i++) {
        bin += String.fromCharCode(bytes[i]);
      }
      var b64 = window.btoa(bin);
      var intentUrl = 'intent:base64,' + b64 + '#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;S.browser_fallback_url=https%3A%2F%2Fplay.google.com%2Fstore%2Fapps%2Fdetails%3Fid%3Dru.a402d.rawbtprinter;end;';

      var link = document.createElement('a');
      link.href = intentUrl;
      link.style.display = 'none';
      document.body.appendChild(link);
      link.click();
      setTimeout(function() {
        if (link.parentNode) link.parentNode.removeChild(link);
      }, 1000);
      return true;
    },

    /**
     * Generate byte ESC/POS untuk Test Print
     * SESUAI REQUEST USER: HANYA SATU KALIMAT SAJA ("sukses terhubung")
     */
    buildTestPrintBytes: function() {
      var encoder = new TextEncoder();
      var textBytes = encoder.encode('sukses terhubung\n\n\n');

      var commands = [
        0x1B, 0x40,             // ESC @: Inisialisasi printer
        0x1B, 0x61, 0x01        // ESC a 1: Center text
      ];

      for (var i = 0; i < textBytes.length; i++) {
        commands.push(textBytes[i]);
      }

      if (this.autoCut) {
        commands.push(0x1D, 0x56, 0x00); // GS V 0: Full cut
      }

      return new Uint8Array(commands);
    },

    /**
     * TEST PRINT SESUAI REQUEST: HANYA SATU KALIMAT SAJA ("sukses terhubung")
     */
    testPrint: async function(forceMethod) {
      var method = forceMethod || this.printMethod;
      if (method === 'rawbt' || (!this.isConnected && this.isAndroid())) {
        return this.testPrintRawBT();
      }
      var bytes = this.buildTestPrintBytes();
      return await this.sendRawBytes(bytes);
    },

    /**
     * Test print via RawBT Android
     */
    testPrintRawBT: function() {
      var bytes = this.buildTestPrintBytes();
      return this.printRawBT(bytes);
    },

    /**
     * Generate byte ESC/POS untuk Voucher
     */
    buildVoucherBytes: function(data) {
      data = data || {};
      var encoder = new TextEncoder();
      var width = (this.paperSize === '80') ? 48 : 32;
      var sep = '';
      for (var s = 0; s < width; s++) sep += '-';

      var cmds = [];
      function add(arr) {
        for (var i = 0; i < arr.length; i++) cmds.push(arr[i]);
      }
      function addText(str) {
        var b = encoder.encode(str);
        for (var i = 0; i < b.length; i++) cmds.push(b[i]);
      }

      // Init & Center
      add([0x1B, 0x40]);
      add([0x1B, 0x61, 0x01]);

      // Header Hotspot
      if (data.hotspotName) {
        add([0x1B, 0x45, 0x01]); // Bold on
        addText(data.hotspotName + '\n');
        add([0x1B, 0x45, 0x00]); // Bold off
      }
      if (data.dnsName) {
        addText(data.dnsName + '\n');
      }

      addText(sep + '\n');

      // Left align for details
      add([0x1B, 0x61, 0x00]);
      if (data.profile) addText('Paket     : ' + data.profile + '\n');
      if (data.validity) addText('Masa Aktif: ' + data.validity + '\n');
      if (data.price) addText('Harga     : ' + data.price + '\n');

      // Center voucher box
      add([0x1B, 0x61, 0x01]);
      addText(sep + '\n');
      addText('KODE VOUCHER:\n');
      add([0x1B, 0x45, 0x01]); // Bold on
      add([0x1D, 0x21, 0x11]); // Double width & height
      addText((data.code || data.username || '------') + '\n');
      add([0x1D, 0x21, 0x00]); // Normal text
      add([0x1B, 0x45, 0x00]); // Bold off

      if (data.password && data.password !== data.username && data.password !== data.code) {
        addText('Password: ' + data.password + '\n');
      } else {
        addText('Username & Password sama\n');
      }

      addText(sep + '\n');
      if (data.warungName) {
        addText('Warung: ' + data.warungName + '\n');
      }
      if (this.footerText) {
        addText(this.footerText + '\n');
      }

      addText('\n\n\n');

      if (this.autoCut) {
        add([0x1D, 0x56, 0x00]); // Cut
      }

      return new Uint8Array(cmds);
    },

    /**
     * Cetak Struk Voucher / Transaksi (Otomatis deteksi BLE atau RawBT)
     */
    printVoucher: async function(data) {
      if (this.printMethod === 'rawbt') {
        return this.printVoucherRawBT(data);
      }
      if (this.isConnected) {
        var bytes = this.buildVoucherBytes(data);
        return await this.sendRawBytes(bytes);
      }
      if (this.isAndroid()) {
        return this.printVoucherRawBT(data);
      }
      throw new Error('Printer thermal belum terhubung.');
    },

    /**
     * Cetak Voucher via RawBT Android
     */
    printVoucherRawBT: function(data) {
      var bytes = this.buildVoucherBytes(data);
      return this.printRawBT(bytes);
    }
  };

  window.MikhmonThermal = MikhmonThermal;
})(window);
