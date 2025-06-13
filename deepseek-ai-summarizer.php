<?php
/**
 * Plugin Name: 爱奇吉摘要
 * Plugin URI: https://github.com/JiQingzhe2004/WordPress-AI-Summary
 * Description: 使用爱奇吉摘要自动生成文章摘要和SEO优化内容，支持流式输出效果
 * Version: 2.1.0
 * Author: 吉庆喆
 * License: GPL v2 or later
 * Text Domain: deepseek-ai-summarizer
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('DEEPSEEK_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DEEPSEEK_AI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DEEPSEEK_AI_VERSION', '2.1.0');

class DeepSeekAISummarizer {
    
    private $log_file;
    private $version;
    
    public function __construct() {
        // 设置日志文件路径
        $this->log_file = DEEPSEEK_AI_PLUGIN_PATH . 'deepseek-ai-debug.log';
        // 设置版本号
        $this->version = DEEPSEEK_AI_VERSION;
        
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    private function write_log($message) {
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
        
        // 注册设置
        add_action('admin_init', array($this, 'register_settings'));
        
        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 加载样式和脚本
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // 在文章编辑页面添加元框
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // 保存文章时的钩子
        add_action('save_post', array($this, 'save_post_meta'));
        
        // AJAX处理
        add_action('wp_ajax_generate_summary', array($this, 'ajax_generate_summary'));
        add_action('wp_ajax_generate_seo', array($this, 'ajax_generate_seo'));
        
        // 在文章内容前显示摘要
        add_filter('the_content', array($this, 'display_summary_before_content'));
        
        // 添加SEO元数据到头部
        add_action('wp_head', array($this, 'add_seo_meta_tags'));
        
        // 添加强制显示摘要的备用机制
        add_action('wp_footer', array($this, 'force_display_summary_fallback'));
    }
    
    public function register_settings() {
        // 注册设置组
        register_setting('deepseek_ai_settings', 'deepseek_ai_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('deepseek_ai_settings', 'deepseek_ai_model', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'deepseek-chat'
        ));
        
        register_setting('deepseek_ai_settings', 'deepseek_ai_max_tokens', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 500
        ));
        
        register_setting('deepseek_ai_settings', 'deepseek_ai_temperature', array(
            'type' => 'number',
            'sanitize_callback' => 'floatval',
            'default' => 0.7
        ));
        
        register_setting('deepseek_ai_settings', 'deepseek_ai_force_display', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));
        
        // 添加设置节
        add_settings_section(
            'deepseek_ai_main_section',
            'API 设置',
            array($this, 'settings_section_callback'),
            'deepseek-ai-settings'
        );
        
        // 添加设置字段
        add_settings_field(
            'deepseek_ai_api_key',
            'API Key',
            array($this, 'api_key_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        add_settings_field(
            'deepseek_ai_model',
            '模型',
            array($this, 'model_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        add_settings_field(
            'deepseek_ai_max_tokens',
            '最大Token数',
            array($this, 'max_tokens_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        add_settings_field(
            'deepseek_ai_temperature',
            'Temperature',
            array($this, 'temperature_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        add_settings_field(
            'deepseek_ai_force_display',
            '强制显示摘要',
            array($this, 'force_display_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
    }
    
    public function settings_section_callback() {
        echo '<p>请配置爱奇吉摘要的API设置</p>';
    }
    
    public function api_key_field_callback() {
        $api_key = get_option('deepseek_ai_api_key', '');
        echo '<input type="text" name="deepseek_ai_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">请输入您的爱奇吉API Key</p>';
    }
    
    public function model_field_callback() {
        $model = get_option('deepseek_ai_model', 'deepseek-chat');
        echo '<select name="deepseek_ai_model">';
        echo '<option value="deepseek-chat"' . selected($model, 'deepseek-chat', false) . '>deepseek-chat</option>';
        echo '<option value="deepseek-reasoner"' . selected($model, 'deepseek-reasoner', false) . '>deepseek-reasoner</option>';
        echo '</select>';
    }
    
    public function max_tokens_field_callback() {
        $max_tokens = get_option('deepseek_ai_max_tokens', 500);
        echo '<input type="number" name="deepseek_ai_max_tokens" value="' . esc_attr($max_tokens) . '" min="100" max="2000" />';
    }
    
    public function temperature_field_callback() {
        $temperature = get_option('deepseek_ai_temperature', 0.7);
        echo '<input type="number" name="deepseek_ai_temperature" value="' . esc_attr($temperature) . '" min="0" max="1" step="0.1" />';
    }
    
    public function force_display_field_callback() {
        $force_display = get_option('deepseek_ai_force_display', false);
        echo '<input type="checkbox" name="deepseek_ai_force_display" value="1" ' . checked(1, $force_display, false) . ' />';
        echo '<p class="description">启用后，摘要将在所有支持的主题中强制显示，即使主题不完全兼容也能正常显示摘要内容</p>';
    }
    
    public function add_admin_menu() {
        add_menu_page(
            '爱奇吉摘要 设置',
            '爱奇吉摘要',
            'manage_options',
            'deepseek-ai-settings',
            array($this, 'admin_page'),
            plugins_url('img/deepseek-color.svg', __FILE__),
            25
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        // 添加调试日志
        $this->write_log('当前页面钩子: ' . $hook);
        
        // 检查是否在文章编辑页面或设置页面
        if (in_array($hook, array('post.php', 'post-new.php', 'settings_page_deepseek-ai-settings'))) {
            // 加载样式
            wp_enqueue_style('deepseek-ai-admin', DEEPSEEK_AI_PLUGIN_URL . 'css/style.css', array(), $this->version);
            $this->write_log('已加载管理页面样式文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'css/style.css');
            
            // 加载脚本
            wp_enqueue_script('deepseek-ai-admin', DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js', array('jquery'), $this->version, true);
            $this->write_log('已加载管理页面脚本文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js');
            
            // 本地化脚本
            wp_localize_script('deepseek-ai-admin', 'deepseek_ai_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('deepseek_ai_nonce'),
                'generating_text' => '正在生成中...',
                'error_text' => '生成失败，请检查API设置',
                'plugin_url' => DEEPSEEK_AI_PLUGIN_URL,
                'version' => $this->version,
                'plugin_name' => 'DeepSeek AI 文章摘要生成器'
            ));
            $this->write_log('已本地化脚本数据，版本号: ' . $this->version);
        }
    }
    
    public function enqueue_frontend_scripts() {
        if (is_single()) {
            // 添加调试日志
            $this->write_log('正在加载前端脚本');
            
            // 加载样式
            wp_enqueue_style('deepseek-ai-frontend', DEEPSEEK_AI_PLUGIN_URL . 'css/style.css', array(), $this->version);
            $this->write_log('已加载前端样式文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'css/style.css');
            
            // 加载脚本
            wp_enqueue_script('deepseek-ai-frontend', DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js', array('jquery'), $this->version, true);
            $this->write_log('已加载前端脚本文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js');
            
            // 本地化脚本
            wp_localize_script('deepseek-ai-frontend', 'deepseek_ai_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('deepseek_ai_nonce'),
                'plugin_url' => DEEPSEEK_AI_PLUGIN_URL,
                'version' => $this->version,
                'plugin_name' => 'DeepSeek AI 文章摘要生成器'
            ));
            $this->write_log('已本地化前端脚本数据，版本号: ' . $this->version);
        }
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'aiqiji-summary-generator',
            '爱奇吉摘要 内容生成器',
            array($this, 'meta_box_callback'),
            'post',
            'normal',
            'high'  // 提高优先级
        );
    }
    
    public function meta_box_callback($post) {
        wp_nonce_field('deepseek_ai_meta_box', 'deepseek_ai_meta_box_nonce');
        
        // 优先使用WordPress原生摘要
        $summary = get_the_excerpt($post);
        
        // 如果原生摘要为空，则尝试使用自定义字段
        if (empty($summary)) {
            $summary = get_post_meta($post->ID, '_deepseek_ai_summary', true);
        }
        
        $seo_title = get_post_meta($post->ID, '_deepseek_ai_seo_title', true);
        $seo_description = get_post_meta($post->ID, '_deepseek_ai_seo_description', true);
        $seo_keywords = get_post_meta($post->ID, '_deepseek_ai_seo_keywords', true);
        
        echo '<div class="deepseek-ai-meta-box">';
        
        // 摘要部分
        echo '<div class="deepseek-ai-section">';
        echo '<h3><i class="dashicons dashicons-format-aside"></i> 文章摘要</h3>';
        echo '<div class="deepseek-ai-controls">';
        echo '<button type="button" class="deepseek-ai-btn summary" id="generate-summary">生成摘要</button>';
        echo '<div class="deepseek-ai-loading" id="summary-loading" style="display:none;">正在生成摘要...</div>';
        echo '</div>';
        echo '<textarea name="deepseek_ai_summary" id="deepseek-ai-summary" rows="4" style="width:100%;">' . esc_textarea($summary) . '</textarea>';
        echo '<p class="description">摘要将同步到WordPress原生摘要字段，兼容所有主题</p>';
        echo '</div>';
        
        // SEO部分
        echo '<div class="deepseek-ai-section">';
        echo '<h3><i class="dashicons dashicons-search"></i> SEO 优化</h3>';
        echo '<div class="deepseek-ai-controls">';
        echo '<button type="button" class="deepseek-ai-btn summary" id="generate-seo">生成SEO内容</button>';
        echo '<div class="deepseek-ai-loading" id="seo-loading" style="display:none;">正在生成SEO内容...</div>';
        echo '</div>';
        
        echo '<div class="deepseek-ai-seo-fields">';
        echo '<p><label for="deepseek-ai-seo-title">SEO标题:</label></p>';
        echo '<input type="text" name="deepseek_ai_seo_title" id="deepseek-ai-seo-title" value="' . esc_attr($seo_title) . '" style="width:100%;" />';
        echo '<p class="description">用于搜索引擎显示的标题，建议包含关键词</p>';
        
        echo '<p><label for="deepseek-ai-seo-description">SEO描述:</label></p>';
        echo '<textarea name="deepseek_ai_seo_description" id="deepseek-ai-seo-description" rows="3" style="width:100%;">' . esc_textarea($seo_description) . '</textarea>';
        echo '<p class="description">用于搜索引擎显示的描述，建议150字以内</p>';
        
        echo '<p><label for="deepseek-ai-seo-keywords">关键词:</label></p>';
        echo '<input type="text" name="deepseek_ai_seo_keywords" id="deepseek-ai-seo-keywords" value="' . esc_attr($seo_keywords) . '" style="width:100%;" placeholder="关键词1, 关键词2, 关键词3" />';
        echo '<p class="description">用逗号分隔的关键词列表，建议3-5个关键词</p>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }
    
    public function save_post_meta($post_id) {
        if (!isset($_POST['deepseek_ai_meta_box_nonce']) || !wp_verify_nonce($_POST['deepseek_ai_meta_box_nonce'], 'deepseek_ai_meta_box')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // 保存摘要
        if (isset($_POST['deepseek_ai_summary'])) {
            $summary = sanitize_textarea_field($_POST['deepseek_ai_summary']);
            // 更新WordPress原生摘要字段
            wp_update_post(array(
                'ID' => $post_id,
                'post_excerpt' => $summary
            ));
            // 更新自定义字段（使用update_post_meta确保只保存一次）
            update_post_meta($post_id, '_deepseek_ai_summary', $summary);
        }
        
        // 保存SEO数据
        if (isset($_POST['deepseek_ai_seo_title'])) {
            update_post_meta($post_id, '_deepseek_ai_seo_title', sanitize_text_field($_POST['deepseek_ai_seo_title']));
        }
        
        if (isset($_POST['deepseek_ai_seo_description'])) {
            update_post_meta($post_id, '_deepseek_ai_seo_description', sanitize_textarea_field($_POST['deepseek_ai_seo_description']));
        }
        
        if (isset($_POST['deepseek_ai_seo_keywords'])) {
            update_post_meta($post_id, '_deepseek_ai_seo_keywords', sanitize_text_field($_POST['deepseek_ai_seo_keywords']));
        }
    }
    
    public function ajax_generate_summary() {
        // 记录开始日志
        $this->write_log('开始生成摘要');
        
        try {
            check_ajax_referer('deepseek_ai_nonce', 'nonce');
            $this->write_log('Nonce验证通过');
            
            $post_id = intval($_POST['post_id']);
            $this->write_log('文章ID = ' . $post_id);
            
            $post = get_post($post_id);
            
            if (!$post) {
                $this->write_log('文章不存在，ID = ' . $post_id);
                wp_send_json_error('文章不存在');
                return;
            }
            
            $content = wp_strip_all_tags($post->post_content);
            $this->write_log('文章内容长度 = ' . strlen($content));
            
            if (empty($content)) {
                $this->write_log('文章内容为空');
                wp_send_json_error('文章内容为空，无法生成摘要');
                return;
            }
            
            $summary = $this->generate_ai_content($content, 'summary');
            
            if ($summary) {
                // 更新WordPress原生摘要字段
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_excerpt' => $summary
                ));
                
                // 更新自定义字段（使用update_post_meta确保只保存一次）
                update_post_meta($post_id, '_deepseek_ai_summary', $summary);
                
                $this->write_log('摘要生成成功，长度 = ' . strlen($summary));
                wp_send_json_success(array('summary' => $summary));
            } else {
                $this->write_log('摘要生成失败');
                wp_send_json_error('生成摘要失败，请检查API配置');
            }
        } catch (Exception $e) {
            $this->write_log('异常错误 - ' . $e->getMessage());
            wp_send_json_error('系统错误：' . $e->getMessage());
        }
    }
    
    public function ajax_generate_seo() {
        // 记录开始日志
        $this->write_log('开始生成SEO内容');
        
        try {
            check_ajax_referer('deepseek_ai_nonce', 'nonce');
            $this->write_log('Nonce验证通过');
            
            $post_id = intval($_POST['post_id']);
            $this->write_log('文章ID = ' . $post_id);
            
            $post = get_post($post_id);
            
            if (!$post) {
                $this->write_log('文章不存在，ID = ' . $post_id);
                wp_send_json_error('文章不存在');
                return;
            }
            
            $content = wp_strip_all_tags($post->post_content);
            $title = $post->post_title;
            
            $this->write_log('文章标题 = ' . $title);
            $this->write_log('文章内容长度 = ' . strlen($content));
            
            if (empty($content)) {
                $this->write_log('文章内容为空');
                wp_send_json_error('文章内容为空，无法生成SEO内容');
                return;
            }
            
            if (empty($title)) {
                $this->write_log('文章标题为空');
                wp_send_json_error('文章标题为空，无法生成SEO内容');
                return;
            }
            
            $seo_data = $this->generate_ai_content($content, 'seo', $title);
            
            if ($seo_data) {
                // 使用update_post_meta确保只保存一次
                update_post_meta($post_id, '_deepseek_ai_seo_title', $seo_data['title']);
                update_post_meta($post_id, '_deepseek_ai_seo_description', $seo_data['description']);
                update_post_meta($post_id, '_deepseek_ai_seo_keywords', $seo_data['keywords']);
                
                $this->write_log('SEO内容生成成功');
                wp_send_json_success($seo_data);
            } else {
                $this->write_log('SEO内容生成失败');
                wp_send_json_error('生成SEO内容失败，请检查API配置');
            }
        } catch (Exception $e) {
            $this->write_log('异常错误 - ' . $e->getMessage());
            wp_send_json_error('系统错误：' . $e->getMessage());
        }
    }
    
    private function generate_ai_content($content, $type, $title = '') {
        $this->write_log('开始调用API，类型 = ' . $type);
        
        $api_key = get_option('deepseek_ai_api_key', '');
        
        if (empty($api_key)) {
            $this->write_log('API Key未配置');
            return false;
        }
        
        $this->write_log('API Key已配置，长度 = ' . strlen($api_key));
        $this->write_log('API Key前10位 = ' . substr($api_key, 0, 10) . '...');
        
        $model = get_option('deepseek_ai_model', 'deepseek-chat');
        $max_tokens = get_option('deepseek_ai_max_tokens', 500);
        $temperature = get_option('deepseek_ai_temperature', 0.7);
        
        $this->write_log('模型 = ' . $model . ', 最大令牌 = ' . $max_tokens . ', 温度 = ' . $temperature);
        
        if ($type === 'summary') {
            $prompt = "请为以下文章内容生成一个简洁、准确的摘要，控制在150字以内：\n\n" . $content;
        } else {
            $prompt = "请为以下文章生成SEO优化内容：\n标题：" . $title . "\n内容：" . $content . "\n\n请返回JSON格式：{\"title\": \"SEO优化标题\", \"description\": \"SEO描述(150字内)\", \"keywords\": \"关键词1,关键词2,关键词3\"}";
        }
        
        $this->write_log('提示词长度 = ' . strlen($prompt));
        
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => intval($max_tokens),
            'temperature' => floatval($temperature)
        );
        
        $this->write_log('准备发送API请求到: https://api.deepseek.com/chat/completions');
        
        $response = wp_remote_post('https://api.deepseek.com/chat/completions', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->write_log('API请求错误 - ' . $error_message);
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $this->write_log('API响应状态码 = ' . $response_code);
        
        $body = wp_remote_retrieve_body($response);
        $this->write_log('API响应内容长度 = ' . strlen($body));
        $this->write_log('API响应内容前500字符 = ' . substr($body, 0, 500));
        
        if ($response_code !== 200) {
            $this->write_log('API响应错误，状态码 = ' . $response_code . ', 内容 = ' . $body);
            return false;
        }
        
        $result = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->write_log('JSON解析错误 - ' . json_last_error_msg());
            return false;
        }
        
        if (!isset($result['choices'][0]['message']['content'])) {
            $this->write_log('API响应格式错误，缺少content字段');
            $this->write_log('完整响应 = ' . print_r($result, true));
            return false;
        }
        
        $ai_response = $result['choices'][0]['message']['content'];
        $this->write_log('AI响应内容长度 = ' . strlen($ai_response));
        $this->write_log('AI响应内容 = ' . $ai_response);
        
        if ($type === 'summary') {
            $summary = trim($ai_response);
            $this->write_log('摘要生成完成');
            return $summary;
        } else {
            $this->write_log('尝试解析SEO JSON数据');
            
            // 清理AI响应中的Markdown代码块标记
            $ai_response = preg_replace('/^```json\s*|\s*```$/s', '', $ai_response);
            $ai_response = trim($ai_response);
            
            $this->write_log('清理后的JSON内容 = ' . $ai_response);
            
            $seo_data = json_decode($ai_response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->write_log('SEO JSON解析错误 - ' . json_last_error_msg());
                $this->write_log('清理后的原始响应 = ' . $ai_response);
                return false;
            }
            
            if ($seo_data && isset($seo_data['title'])) {
                $this->write_log('SEO内容解析成功');
                return $seo_data;
            } else {
                $this->write_log('SEO数据格式错误，缺少title字段');
                $this->write_log('解析结果 = ' . print_r($seo_data, true));
                return false;
            }
        }
    }
    
    public function display_summary_before_content($content) {
        // 获取强制显示设置
        $force_display = get_option('deepseek_ai_force_display', false);
        
        // 检查显示条件：单篇文章页面，或者强制显示模式
        $should_display = false;
        
        if ($force_display) {
            // 强制显示模式：在所有单篇文章页面显示，不受主题限制
            $should_display = is_single();
        } else {
            // 标准模式：只在主循环中显示
            $should_display = is_single() && in_the_loop() && is_main_query();
        }
        
        if ($should_display) {
            // 优先使用WordPress原生摘要
            $summary = get_the_excerpt();
            
            // 如果原生摘要为空，则尝试使用自定义字段
            if (empty($summary)) {
                $summary = get_post_meta(get_the_ID(), '_deepseek_ai_summary', true);
            }
            
            if (!empty($summary)) {
                $summary_html = '<div class="deepseek-ai-summary-container" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007cba; border-radius: 4px;">';
                $summary_html .= '<div class="deepseek-ai-summary-header" style="display: flex; align-items: center; margin-bottom: 10px; font-weight: bold; color: #007cba;">';
                $summary_html .= '<span class="deepseek-ai-icon"></span>';
                $summary_html .= '<span class="deepseek-ai-title">AI 智能摘要（爱奇吉）</span>';
                $summary_html .= '</div>';
                $summary_html .= '<div class="deepseek-ai-summary-content" style="line-height: 1.6; color: #333;">' . wp_kses_post($summary) . '</div>';
                $summary_html .= '</div>';
                
                // 在强制显示模式下，使用更高优先级的方式插入内容
                if ($force_display) {
                    // 使用JavaScript确保摘要显示
                    $summary_html .= '<script>document.addEventListener("DOMContentLoaded", function() {
                        var summaryContainer = document.querySelector(".deepseek-ai-summary-container");
                        if (summaryContainer && !summaryContainer.parentNode.querySelector(".entry-content, .post-content, .content")) {
                            var contentArea = document.querySelector(".entry-content, .post-content, .content, article .content, .single-post .content");
                            if (contentArea && !contentArea.querySelector(".deepseek-ai-summary-container")) {
                                contentArea.insertBefore(summaryContainer, contentArea.firstChild);
                            }
                        }
                    });</script>';
                }
                
                $content = $summary_html . $content;
            }
        }
        
        return $content;
    }
    
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // 检查是否有设置更新
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'deepseek_ai_messages',
                'deepseek_ai_message',
                '设置已保存',
                'updated'
            );
        }
        
        // 显示设置错误/更新消息
        settings_errors('deepseek_ai_messages');
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<form action="options.php" method="post">';
        
        settings_fields('deepseek_ai_settings');
        do_settings_sections('deepseek-ai-settings');
        submit_button('保存设置');
        
        echo '</form>';
        echo '</div>';
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
    
    public function add_seo_meta_tags() {
        if (is_single()) {
            $post_id = get_the_ID();
            $seo_title = get_post_meta($post_id, '_deepseek_ai_seo_title', true);
            $seo_description = get_post_meta($post_id, '_deepseek_ai_seo_description', true);
            $seo_keywords = get_post_meta($post_id, '_deepseek_ai_seo_keywords', true);
            
            if (!empty($seo_title)) {
                echo '<meta name="title" content="' . esc_attr($seo_title) . '" />' . "\n";
            }
            
            if (!empty($seo_description)) {
                echo '<meta name="description" content="' . esc_attr($seo_description) . '" />' . "\n";
            }
            
            if (!empty($seo_keywords)) {
                echo '<meta name="keywords" content="' . esc_attr($seo_keywords) . '" />' . "\n";
            }
        }
    }
    
    public function force_display_summary_fallback() {
        // 只在启用强制显示且为单篇文章页面时执行
        if (!get_option('deepseek_ai_force_display', false) || !is_single()) {
            return;
        }
        
        // 获取摘要内容
        $summary = get_the_excerpt();
        if (empty($summary)) {
            $summary = get_post_meta(get_the_ID(), '_deepseek_ai_summary', true);
        }
        
        if (empty($summary)) {
            return;
        }
        
        // 输出JavaScript代码，在页面加载完成后强制插入摘要
        echo '<script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            // 检查是否已经存在摘要容器
            if (document.querySelector(".deepseek-ai-summary-container")) {
                return;
            }
            
            // 创建摘要HTML
            var summaryHtml = `<div class="deepseek-ai-summary-container" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007cba; border-radius: 4px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
                <div class="deepseek-ai-summary-header" style="display: flex; align-items: center; margin-bottom: 10px; font-weight: bold; color: #007cba;">
                    <span class="deepseek-ai-title">AI 智能摘要（爱奇吉）</span>
                </div>
                <div class="deepseek-ai-summary-content" style="line-height: 1.6; color: #333;">' . wp_kses_post($summary) . '</div>
            </div>`;
            
            // 尝试多种方式找到内容区域并插入摘要
            var contentSelectors = [
                ".entry-content",
                ".post-content", 
                ".content",
                "article .content",
                ".single-post .content",
                "main article",
                ".post-body",
                ".entry",
                "article",
                ".post",
                "main"
            ];
            
            var inserted = false;
            for (var i = 0; i < contentSelectors.length && !inserted; i++) {
                var contentArea = document.querySelector(contentSelectors[i]);
                if (contentArea) {
                    // 创建临时div来解析HTML
                    var tempDiv = document.createElement("div");
                    tempDiv.innerHTML = summaryHtml;
                    var summaryElement = tempDiv.firstChild;
                    
                    // 插入到内容区域的开头
                    contentArea.insertBefore(summaryElement, contentArea.firstChild);
                    inserted = true;
                    console.log("AI摘要已强制显示在: " + contentSelectors[i]);
                }
            }
            
            if (!inserted) {
                console.log("未找到合适的内容区域来显示AI摘要");
            }
        });
        </script>';
    }
}

// 初始化插件
new DeepSeekAISummarizer();
?>