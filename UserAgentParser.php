<?php
/**
 * User-Agent 解析器
 * 用于解析浏览器、操作系统、设备信息
 */
class UserAgentParser {
    
    private $userAgent;
    
    public function __construct($userAgent) {
        $this->userAgent = $userAgent;
    }
    
    /**
     * 解析所有信息
     */
    public function parse() {
        return [
            'browser' => $this->getBrowser(),
            'os' => $this->getOS(),
            'device' => $this->getDevice(),
            'engine' => $this->getEngine()
        ];
    }
    
    /**
     * 获取浏览器信息
     */
    public function getBrowser() {
        $browser = [
            'name' => 'Unknown',
            'version' => 'Unknown'
        ];
        
        // Edge (新版，基于Chromium)
        if (preg_match('/Edg\/([0-9.]+)/', $this->userAgent, $matches)) {
            $browser['name'] = 'Microsoft Edge';
            $browser['version'] = $matches[1];
        }
        // Edge (旧版)
        elseif (preg_match('/Edge\/([0-9.]+)/', $this->userAgent, $matches)) {
            $browser['name'] = 'Microsoft Edge Legacy';
            $browser['version'] = $matches[1];
        }
        // Chrome
        elseif (preg_match('/Chrome\/([0-9.]+)/', $this->userAgent, $matches)) {
            $browser['name'] = 'Chrome';
            $browser['version'] = $matches[1];
        }
        // Firefox
        elseif (preg_match('/Firefox\/([0-9.]+)/', $this->userAgent, $matches)) {
            $browser['name'] = 'Firefox';
            $browser['version'] = $matches[1];
        }
        // Safari
        elseif (preg_match('/Safari\/([0-9.]+)/', $this->userAgent, $matches)) {
            if (!strpos($this->userAgent, 'Chrome')) {
                $browser['name'] = 'Safari';
                if (preg_match('/Version\/([0-9.]+)/', $this->userAgent, $versionMatches)) {
                    $browser['version'] = $versionMatches[1];
                } else {
                    $browser['version'] = $matches[1];
                }
            }
        }
        // Opera
        elseif (preg_match('/OPR\/([0-9.]+)/', $this->userAgent, $matches) || 
                preg_match('/Opera\/([0-9.]+)/', $this->userAgent, $matches)) {
            $browser['name'] = 'Opera';
            $browser['version'] = $matches[1];
        }
        // Internet Explorer
        elseif (preg_match('/MSIE ([0-9.]+)/', $this->userAgent, $matches) || 
                preg_match('/Trident\/.*rv:([0-9.]+)/', $this->userAgent, $matches)) {
            $browser['name'] = 'Internet Explorer';
            $browser['version'] = $matches[1];
        }
        // UC Browser
        elseif (preg_match('/UCBrowser\/([0-9.]+)/', $this->userAgent, $matches)) {
            $browser['name'] = 'UC Browser';
            $browser['version'] = $matches[1];
        }
        // QQ Browser
        elseif (preg_match('/QQBrowser\/([0-9.]+)/', $this->userAgent, $matches)) {
            $browser['name'] = 'QQ Browser';
            $browser['version'] = $matches[1];
        }
        // 微信内置浏览器
        elseif (preg_match('/MicroMessenger\/([0-9.]+)/', $this->userAgent, $matches)) {
            $browser['name'] = 'WeChat Browser';
            $browser['version'] = $matches[1];
        }
        // 支付宝内置浏览器
        elseif (preg_match('/AlipayClient\/([0-9.]+)/', $this->userAgent, $matches)) {
            $browser['name'] = 'Alipay Browser';
            $browser['version'] = $matches[1];
        }
        
        return $browser;
    }
    
    /**
     * 获取操作系统信息
     */
    public function getOS() {
        $os = [
            'name' => 'Unknown',
            'version' => 'Unknown'
        ];
        
        // Windows
        if (preg_match('/Windows NT ([0-9.]+)/', $this->userAgent, $matches)) {
            $os['name'] = 'Windows';
            $version = $matches[1];
            
            // Windows版本映射
            $windowsVersions = [
                '10.0' => '10/11',
                '6.3' => '8.1',
                '6.2' => '8',
                '6.1' => '7',
                '6.0' => 'Vista',
                '5.2' => 'XP x64',
                '5.1' => 'XP'
            ];
            
            $os['version'] = $windowsVersions[$version] ?? $version;
        }
        // macOS
        elseif (preg_match('/Mac OS X ([0-9_]+)/', $this->userAgent, $matches)) {
            $os['name'] = 'macOS';
            $os['version'] = str_replace('_', '.', $matches[1]);
        }
        // iOS
        elseif (preg_match('/iPhone OS ([0-9_]+)/', $this->userAgent, $matches) || 
                preg_match('/iPad.*OS ([0-9_]+)/', $this->userAgent, $matches)) {
            $os['name'] = 'iOS';
            $os['version'] = str_replace('_', '.', $matches[1]);
        }
        // Android
        elseif (preg_match('/Android ([0-9.]+)/', $this->userAgent, $matches)) {
            $os['name'] = 'Android';
            $os['version'] = $matches[1];
        }
        // Linux
        elseif (preg_match('/Linux/', $this->userAgent)) {
            $os['name'] = 'Linux';
            if (preg_match('/Ubuntu/', $this->userAgent)) {
                $os['name'] = 'Ubuntu';
            } elseif (preg_match('/Fedora/', $this->userAgent)) {
                $os['name'] = 'Fedora';
            }
        }
        // Chrome OS
        elseif (preg_match('/CrOS/', $this->userAgent)) {
            $os['name'] = 'Chrome OS';
        }
        
        return $os;
    }
    
    /**
     * 获取设备信息
     */
    public function getDevice() {
        $device = [
            'type' => 'desktop',
            'vendor' => 'Unknown',
            'model' => 'Unknown'
        ];
        
        // 判断设备类型
        if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $this->userAgent)) {
            if (preg_match('/iPad|Tablet|PlayBook/i', $this->userAgent)) {
                $device['type'] = 'tablet';
            } else {
                $device['type'] = 'mobile';
            }
        }
        
        // iPhone
        if (preg_match('/iPhone/', $this->userAgent)) {
            $device['vendor'] = 'Apple';
            $device['model'] = 'iPhone';
        }
        // iPad
        elseif (preg_match('/iPad/', $this->userAgent)) {
            $device['vendor'] = 'Apple';
            $device['model'] = 'iPad';
        }
        // Samsung
        elseif (preg_match('/SAMSUNG|SM-|GT-/', $this->userAgent)) {
            $device['vendor'] = 'Samsung';
            if (preg_match('/(SM-[A-Z0-9]+|GT-[A-Z0-9]+)/', $this->userAgent, $matches)) {
                $device['model'] = $matches[1];
            }
        }
        // Huawei
        elseif (preg_match('/HUAWEI|HW-|Honor/', $this->userAgent)) {
            $device['vendor'] = 'Huawei';
            if (preg_match('/(HUAWEI [A-Z0-9-]+|HW-[A-Z0-9]+|Honor [0-9A-Z]+)/i', $this->userAgent, $matches)) {
                $device['model'] = $matches[1];
            }
        }
        // Xiaomi
        elseif (preg_match('/Mi |MI |Redmi|POCO/', $this->userAgent)) {
            $device['vendor'] = 'Xiaomi';
            if (preg_match('/(Mi [A-Z0-9 ]+|Redmi [A-Z0-9 ]+|POCO [A-Z0-9 ]+)/i', $this->userAgent, $matches)) {
                $device['model'] = trim($matches[1]);
            }
        }
        // Oppo
        elseif (preg_match('/OPPO/', $this->userAgent)) {
            $device['vendor'] = 'Oppo';
            if (preg_match('/OPPO ([A-Z0-9]+)/', $this->userAgent, $matches)) {
                $device['model'] = $matches[1];
            }
        }
        // Vivo
        elseif (preg_match('/vivo/', $this->userAgent)) {
            $device['vendor'] = 'Vivo';
            if (preg_match('/vivo ([A-Z0-9]+)/', $this->userAgent, $matches)) {
                $device['model'] = $matches[1];
            }
        }
        
        return $device;
    }
    
    /**
     * 获取浏览器引擎
     */
    public function getEngine() {
        if (preg_match('/Trident/', $this->userAgent)) {
            return 'Trident';
        } elseif (preg_match('/WebKit/', $this->userAgent)) {
            if (preg_match('/Blink/', $this->userAgent)) {
                return 'Blink';
            }
            return 'WebKit';
        } elseif (preg_match('/Gecko/', $this->userAgent)) {
            return 'Gecko';
        } elseif (preg_match('/Presto/', $this->userAgent)) {
            return 'Presto';
        }
        return 'Unknown';
    }
    
    /**
     * 判断是否为爬虫/机器人
     */
    public function isBot() {
        $botPatterns = [
            'bot', 'crawl', 'spider', 'slurp', 'search', 'scraper'
        ];
        
        $ua = strtolower($this->userAgent);
        foreach ($botPatterns as $pattern) {
            if (strpos($ua, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
}
?>
