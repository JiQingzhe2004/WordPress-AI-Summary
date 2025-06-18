<?php
/**
 * 主插件类
 *
 * @package DeepSeekAISummarizer
 * @since 2.1.0
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class DeepSeekAISummarizer {
    
    private $log_file;
    private $version;
    private $admin;
    private $frontend;
    private $ajax;
    private $api;
    
    public function __construct() {
        // 设置日志文件路径
        $this->log_file = DEEPSEEK_AI_PLUGIN_PATH . 'deepseek-ai-debug.log';
        // 设置版本号
        $this->version = DEEPSEEK_AI_VERSION;
        
        add_action('init', array($this, 'init'));
        register_activation_hook(DEEPSEEK_AI_PLUGIN_PATH . 'deepseek-ai-summarizer.php', array($this, 'activate'));
        register_deactivation_hook(DEEPSEEK_AI_PLUGIN_PATH . 'deepseek-ai-summarizer.php', array($this, 'deactivate'));
    }
    
    public function write_log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
        
        // 写入本地日志文件
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // 同时写入WordPress错误日志
        error_log('爱奇吉摘要: ' . $message);
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
        
        $this->write_log('插件激活：默认设置已初始化');
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