<?php
/**
 * 主插件类
 *
 * @package DeepSeekAISummarizer
 * @since 3.5.4
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 防止重复声明类
if (!class_exists('DeepSeekAISummarizer')) {

class DeepSeekAISummarizer {
    
    private $log_file;
    private $version;
    private $admin;
    private $frontend;
    private $ajax;
    private $api;
    private $debug_enabled;
    private $debug_level;
    
    public function __construct() {
        // 设置日志文件路径
        $this->log_file = DEEPSEEK_AI_PLUGIN_PATH . 'deepseek-ai-debug.log';
        // 设置版本号
        $this->version = DEEPSEEK_AI_VERSION;
        // 初始化调试设置
        $this->debug_enabled = get_option('deepseek_ai_debug_enabled', false);
        $this->debug_level = get_option('deepseek_ai_debug_level', 'info');
        
        add_action('init', array($this, 'init'));
        register_activation_hook(DEEPSEEK_AI_PLUGIN_PATH . 'deepseek-ai-summarizer.php', array($this, 'activate'));
        register_deactivation_hook(DEEPSEEK_AI_PLUGIN_PATH . 'deepseek-ai-summarizer.php', array($this, 'deactivate'));
        
        // 添加调试相关的钩子
        add_action('wp_ajax_deepseek_ai_clear_debug_log', array($this, 'clear_debug_log'));
        add_action('wp_ajax_deepseek_ai_download_debug_log', array($this, 'download_debug_log'));
        add_action('wp_ajax_deepseek_ai_get_debug_log', array($this, 'get_debug_log'));
    }
    
    public function write_log($message, $level = 'info') {
        // 如果调试未启用，只记录错误级别的日志
        if (!$this->debug_enabled && $level !== 'error') {
            return;
        }
        
        // 检查日志级别
        $levels = array('debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3);
        $current_level = isset($levels[$this->debug_level]) ? $levels[$this->debug_level] : 1;
        $message_level = isset($levels[$level]) ? $levels[$level] : 1;
        
        if ($message_level < $current_level) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $level_upper = strtoupper($level);
        $log_entry = "[{$timestamp}] [{$level_upper}] {$message}" . PHP_EOL;
        
        // 写入本地日志文件
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // 同时写入WordPress错误日志（仅错误和警告）
        if (in_array($level, array('error', 'warning'))) {
            error_log('爱奇吉摘要 [' . $level_upper . ']: ' . $message);
        }
    }
    
    public function debug_log($message) {
        $this->write_log($message, 'debug');
    }
    
    public function info_log($message) {
        $this->write_log($message, 'info');
    }
    
    public function warning_log($message) {
        $this->write_log($message, 'warning');
    }
    
    public function error_log($message) {
        $this->write_log($message, 'error');
    }
    
    public function is_debug_enabled() {
        return $this->debug_enabled;
    }
    
    public function get_debug_level() {
        return $this->debug_level;
    }
    
    public function update_debug_settings() {
        $this->debug_enabled = get_option('deepseek_ai_debug_enabled', false);
        $this->debug_level = get_option('deepseek_ai_debug_level', 'info');
    }
    
    public function get_debug_setting($setting_name) {
        switch ($setting_name) {
            case 'debug_enabled':
                return get_option('deepseek_ai_debug_enabled', false);
            case 'debug_level':
                return get_option('deepseek_ai_debug_level', 'info');
            case 'debug_frontend':
                return get_option('deepseek_ai_debug_frontend', false);
            case 'debug_ajax':
                return get_option('deepseek_ai_debug_ajax', false);
            case 'debug_api':
                return get_option('deepseek_ai_debug_api', false);
            default:
                return false;
        }
    }
    
    public function get_debug_log_content($lines = 100) {
        if (!file_exists($this->log_file)) {
            return '日志文件不存在';
        }
        
        $content = file_get_contents($this->log_file);
        if (empty($content)) {
            return '日志文件为空';
        }
        
        $log_lines = explode("\n", $content);
        $log_lines = array_filter($log_lines); // 移除空行
        
        if ($lines > 0) {
            $log_lines = array_slice($log_lines, -$lines);
        }
        
        return implode("\n", $log_lines);
    }
    
    public function clear_debug_log() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'deepseek_ai_nonce')) {
            wp_die('安全验证失败');
        }
        
        // 检查权限
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        
        // 清空日志文件
        file_put_contents($this->log_file, '');
        $this->info_log('调试日志已被清空');
        
        wp_send_json_success('日志已清空');
    }
    
    public function download_debug_log() {
        // 验证nonce
        if (!wp_verify_nonce($_GET['nonce'], 'deepseek_ai_nonce')) {
            wp_die('安全验证失败');
        }
        
        // 检查权限
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }
        
        if (!file_exists($this->log_file)) {
            wp_die('日志文件不存在');
        }
        
        $filename = 'deepseek-ai-debug-' . date('Y-m-d-H-i-s') . '.log';
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($this->log_file));
        
        readfile($this->log_file);
        exit;
    }
    
    public function get_debug_log() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'deepseek_ai_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        // 检查权限
        if (!current_user_can('manage_options')) {
            wp_send_json_error('权限不足');
        }
        
        $log_content = $this->get_debug_log_content(200); // 获取最后200行
        wp_send_json_success($log_content);
    }
    
    public function init() {
        // 加载文本域
        load_plugin_textdomain('deepseek-ai-summarizer', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // 加载依赖类
        $this->load_dependencies();
        
        // 初始化各个模块
        $this->admin = new DeepSeekAI_Admin($this);
        $this->frontend = new DeepSeekAI_Frontend($this);
        $this->ajax = new DeepSeekAI_Ajax($this);
        $this->api = new DeepSeekAI_API($this);
    }
    
    private function load_dependencies() {
        require_once DEEPSEEK_AI_PLUGIN_PATH . 'includes/class-deepseek-ai-admin.php';
        require_once DEEPSEEK_AI_PLUGIN_PATH . 'includes/class-deepseek-ai-frontend.php';
        require_once DEEPSEEK_AI_PLUGIN_PATH . 'includes/class-deepseek-ai-ajax.php';
        require_once DEEPSEEK_AI_PLUGIN_PATH . 'includes/class-deepseek-ai-api.php';
    }
    
    public function get_version() {
        return $this->version;
    }
    
    public function get_api() {
        return $this->api;
    }
    
    public function activate() {
        // 初始化默认设置
        add_option('deepseek_ai_api_key', '');
        add_option('deepseek_ai_model', 'deepseek-chat');
        add_option('deepseek_ai_max_tokens', 500);
        add_option('deepseek_ai_temperature', 0.7);
        add_option('deepseek_ai_force_display', false);
        
        // 初始化调试设置
        add_option('deepseek_ai_debug_enabled', false);
        add_option('deepseek_ai_debug_level', 'info');
        add_option('deepseek_ai_debug_frontend', false);
        add_option('deepseek_ai_debug_ajax', false);
        add_option('deepseek_ai_debug_api', false);
        
        $this->write_log('插件激活：默认设置已初始化', 'info');
    }
    
    public function deactivate() {
        // 可以选择是否在停用时删除设置
        // delete_option('deepseek_ai_api_key');
        // delete_option('deepseek_ai_model');
        // delete_option('deepseek_ai_max_tokens');
        // delete_option('deepseek_ai_temperature');
        
        $this->write_log('插件停用：清理完成');
    }
}

} // 结束 class_exists 检查