/**
 * 客户端信息收集器
 * 用于收集详细的浏览器、设备、环境信息
 */

const ClientInfoCollector = {
    /**
     * 收集所有客户端信息
     */
    async collect() {
        const info = {
            // 屏幕信息
            screenResolution: this.getScreenResolution(),
            windowSize: this.getWindowSize(),
            colorDepth: screen.colorDepth,
            pixelRatio: window.devicePixelRatio,
            
            // 时区和语言
            timezone: this.getTimezone(),
            timezoneOffset: new Date().getTimezoneOffset(),
            language: navigator.language,
            languages: navigator.languages,
            
            // 平台信息
            platform: navigator.platform,
            userAgent: navigator.userAgent,
            vendor: navigator.vendor,
            
            // 硬件信息
            hardwareConcurrency: navigator.hardwareConcurrency, // CPU核心数
            deviceMemory: navigator.deviceMemory, // 设备内存(GB)
            
            // 浏览器特性
            cookieEnabled: navigator.cookieEnabled,
            doNotTrack: navigator.doNotTrack,
            
            // WebGL信息
            webglVendor: this.getWebGLVendor(),
            webglRenderer: this.getWebGLRenderer(),
            
            // Canvas指纹
            canvasFingerprint: await this.getCanvasFingerprint(),
            
            // 设备指纹（综合多个因素）
            deviceFingerprint: await this.getDeviceFingerprint(),
            
            // 触摸支持
            touchSupport: this.getTouchSupport(),
            
            // 连接信息
            connectionType: this.getConnectionType(),
            
            // 插件信息
            plugins: this.getPlugins(),
            
            // 音频上下文指纹
            audioFingerprint: await this.getAudioFingerprint(),
            
            // 字体检测
            fonts: this.getInstalledFonts(),
            
            // 电池状态
            battery: await this.getBatteryInfo()
        };
        
        return info;
    },
    
    /**
     * 获取屏幕分辨率
     */
    getScreenResolution() {
        return `${screen.width}x${screen.height}`;
    },
    
    /**
     * 获取窗口大小
     */
    getWindowSize() {
        return `${window.innerWidth}x${window.innerHeight}`;
    },
    
    /**
     * 获取时区
     */
    getTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch (e) {
            return 'Unknown';
        }
    },
    
    /**
     * 获取WebGL供应商
     */
    getWebGLVendor() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (gl) {
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                return gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL);
            }
        } catch (e) {
            return 'Unknown';
        }
        return 'Unknown';
    },
    
    /**
     * 获取WebGL渲染器
     */
    getWebGLRenderer() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (gl) {
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                return gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
            }
        } catch (e) {
            return 'Unknown';
        }
        return 'Unknown';
    },
    
    /**
     * 生成Canvas指纹
     */
    async getCanvasFingerprint() {
        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const text = 'ClientFingerprint<Canvas>🎨📊';
            
            ctx.textBaseline = 'top';
            ctx.font = '14px "Arial"';
            ctx.textBaseline = 'alphabetic';
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText(text, 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText(text, 4, 17);
            
            const dataURL = canvas.toDataURL();
            return await this.hashString(dataURL);
        } catch (e) {
            return null;
        }
    },
    
    /**
     * 生成音频指纹
     */
    async getAudioFingerprint() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return null;
            
            const context = new AudioContext();
            const oscillator = context.createOscillator();
            const analyser = context.createAnalyser();
            const gainNode = context.createGain();
            const scriptProcessor = context.createScriptProcessor(4096, 1, 1);
            
            gainNode.gain.value = 0;
            oscillator.type = 'triangle';
            oscillator.connect(analyser);
            analyser.connect(scriptProcessor);
            scriptProcessor.connect(gainNode);
            gainNode.connect(context.destination);
            oscillator.start(0);
            
            const audioData = [];
            scriptProcessor.onaudioprocess = function(event) {
                const output = event.outputBuffer.getChannelData(0);
                for (let i = 0; i < output.length; i++) {
                    audioData.push(output[i]);
                }
            };
            
            await new Promise(resolve => setTimeout(resolve, 100));
            
            oscillator.stop();
            context.close();
            
            return await this.hashString(audioData.slice(0, 100).join(','));
        } catch (e) {
            return null;
        }
    },
    
    /**
     * 生成设备指纹（综合多个因素）
     */
    async getDeviceFingerprint() {
        const components = [
            navigator.userAgent,
            navigator.language,
            screen.colorDepth,
            screen.width + 'x' + screen.height,
            new Date().getTimezoneOffset(),
            navigator.hardwareConcurrency,
            navigator.deviceMemory,
            this.getPlugins(),
            await this.getCanvasFingerprint()
        ];
        
        const fingerprint = components.join('|||');
        return await this.hashString(fingerprint);
    },
    
    /**
     * 获取触摸支持信息
     */
    getTouchSupport() {
        let maxTouchPoints = 0;
        let touchEvent = false;
        
        if (typeof navigator.maxTouchPoints !== 'undefined') {
            maxTouchPoints = navigator.maxTouchPoints;
        } else if (typeof navigator.msMaxTouchPoints !== 'undefined') {
            maxTouchPoints = navigator.msMaxTouchPoints;
        }
        
        try {
            document.createEvent('TouchEvent');
            touchEvent = true;
        } catch (e) {
            touchEvent = false;
        }
        
        const touchStart = 'ontouchstart' in window;
        
        return {
            maxTouchPoints: maxTouchPoints,
            touchEvent: touchEvent,
            touchStart: touchStart
        };
    },
    
    /**
     * 获取连接类型
     */
    getConnectionType() {
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (connection) {
            return {
                effectiveType: connection.effectiveType,
                downlink: connection.downlink,
                rtt: connection.rtt,
                saveData: connection.saveData
            };
        }
        return null;
    },
    
    /**
     * 获取插件列表
     */
    getPlugins() {
        const plugins = [];
        for (let i = 0; i < navigator.plugins.length; i++) {
            plugins.push(navigator.plugins[i].name);
        }
        return plugins.sort().join(',');
    },
    
    /**
     * 检测已安装的字体
     */
    getInstalledFonts() {
        const baseFonts = ['monospace', 'sans-serif', 'serif'];
        const testFonts = [
            'Arial', 'Verdana', 'Times New Roman', 'Courier New',
            'Georgia', 'Palatino', 'Garamond', 'Bookman',
            'Comic Sans MS', 'Trebuchet MS', 'Impact'
        ];
        
        const testString = 'mmmmmmmmmmlli';
        const testSize = '72px';
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        
        const baseFontWidths = {};
        baseFonts.forEach(baseFont => {
            context.font = testSize + ' ' + baseFont;
            baseFontWidths[baseFont] = context.measureText(testString).width;
        });
        
        const detectedFonts = [];
        testFonts.forEach(font => {
            let detected = false;
            baseFonts.forEach(baseFont => {
                context.font = testSize + ' ' + font + ', ' + baseFont;
                const width = context.measureText(testString).width;
                if (width !== baseFontWidths[baseFont]) {
                    detected = true;
                }
            });
            if (detected) {
                detectedFonts.push(font);
            }
        });
        
        return detectedFonts.join(',');
    },
    
    /**
     * 获取电池信息
     */
    async getBatteryInfo() {
        try {
            if ('getBattery' in navigator) {
                const battery = await navigator.getBattery();
                return {
                    charging: battery.charging,
                    level: battery.level,
                    chargingTime: battery.chargingTime,
                    dischargingTime: battery.dischargingTime
                };
            }
        } catch (e) {
            return null;
        }
        return null;
    },
    
    /**
     * 字符串哈希（使用Web Crypto API）
     */
    async hashString(str) {
        try {
            const encoder = new TextEncoder();
            const data = encoder.encode(str);
            const hashBuffer = await crypto.subtle.digest('SHA-256', data);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
            return hashHex;
        } catch (e) {
            // 降级到简单哈希
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return hash.toString(16);
        }
    }
};

// 导出供使用
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ClientInfoCollector;
}
