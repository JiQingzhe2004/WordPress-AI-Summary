<?php
/**
 * 管理后台功能类
 *
 * @package DeepSeekAISummarizer
 * @since 3.0.0
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 防止重复声明类
if (!class_exists('DeepSeekAI_Admin')) {

class DeepSeekAI_Admin {
    
    private $plugin;
    
    public function __construct($plugin) {
        $this->plugin = $plugin;
        
        // 注册设置
        add_action('admin_init', array($this, 'register_settings'));
        
        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 加载样式和脚本
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // 在文章编辑页面添加元框
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // 保存文章时的钩子
        add_action('save_post', array($this, 'save_post_meta'));
    }
    
    public function register_settings() {
        // 注册设置组
        register_setting('deepseek_ai_settings', 'deepseek_ai_provider', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'zhipu'
        ));
        
        register_setting('deepseek_ai_settings', 'deepseek_ai_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('deepseek_ai_settings', 'zhipu_ai_api_key', array(
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
        
        // 智谱清言设置
        register_setting('deepseek_ai_settings', 'zhipu_ai_model', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'glm-4-flash'
        ));
        
        register_setting('deepseek_ai_settings', 'zhipu_ai_max_tokens', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 500
        ));
        
        register_setting('deepseek_ai_settings', 'zhipu_ai_temperature', array(
            'type' => 'number',
            'sanitize_callback' => 'floatval',
            'default' => 0.7
        ));
        
        register_setting('deepseek_ai_settings', 'deepseek_ai_force_display', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));
        
        // 注册调试设置
        register_setting('deepseek_ai_settings', 'deepseek_ai_debug_enabled', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));
        
        register_setting('deepseek_ai_settings', 'deepseek_ai_debug_level', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'info'
        ));
        
        register_setting('deepseek_ai_settings', 'deepseek_ai_debug_frontend', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));
        
        register_setting('deepseek_ai_settings', 'deepseek_ai_debug_ajax', array(
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default' => false
        ));
        
        register_setting('deepseek_ai_settings', 'deepseek_ai_debug_api', array(
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
        
        add_settings_section(
            'deepseek_ai_debug_section',
            '调试设置',
            array($this, 'debug_section_callback'),
            'deepseek-ai-settings'
        );
        
        // 添加设置字段
        add_settings_field(
            'deepseek_ai_provider',
            'AI服务提供商',
            array($this, 'provider_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        // 智谱清言设置（默认推荐）
        add_settings_field(
            'zhipu_ai_api_key',
            '智谱清言 API Key',
            array($this, 'zhipu_api_key_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        add_settings_field(
            'zhipu_ai_model',
            '智谱清言 模型',
            array($this, 'zhipu_model_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        add_settings_field(
            'zhipu_ai_max_tokens',
            '智谱清言 最大Token数',
            array($this, 'zhipu_max_tokens_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        add_settings_field(
            'zhipu_ai_temperature',
            '智谱清言 Temperature',
            array($this, 'zhipu_temperature_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        // DeepSeek设置
        add_settings_field(
            'deepseek_ai_api_key',
            'DeepSeek API Key',
            array($this, 'api_key_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        add_settings_field(
            'deepseek_ai_model',
            'DeepSeek 模型',
            array($this, 'model_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        add_settings_field(
            'deepseek_ai_max_tokens',
            'DeepSeek 最大Token数',
            array($this, 'max_tokens_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_main_section'
        );
        
        add_settings_field(
            'deepseek_ai_temperature',
            'DeepSeek Temperature',
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
        
        // 添加调试设置字段
        add_settings_field(
            'deepseek_ai_debug_enabled',
            '启用调试模式',
            array($this, 'debug_enabled_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_debug_section'
        );
        
        add_settings_field(
            'deepseek_ai_debug_level',
            '调试级别',
            array($this, 'debug_level_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_debug_section'
        );
        
        add_settings_field(
            'deepseek_ai_debug_frontend',
            '前端调试',
            array($this, 'debug_frontend_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_debug_section'
        );
        
        add_settings_field(
            'deepseek_ai_debug_ajax',
            'AJAX调试',
            array($this, 'debug_ajax_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_debug_section'
        );
        
        add_settings_field(
            'deepseek_ai_debug_api',
            'API调试',
            array($this, 'debug_api_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_debug_section'
        );
        
        add_settings_field(
            'deepseek_ai_debug_log_viewer',
            '调试日志查看器',
            array($this, 'debug_log_viewer_field_callback'),
            'deepseek-ai-settings',
            'deepseek_ai_debug_section'
        );
    }
    
    public function settings_section_callback() {
        echo '<p>请配置爱奇吉摘要的API设置</p>';
    }
    
    public function debug_section_callback() {
        echo '<p>调试功能可以帮助您诊断插件问题和监控运行状态</p>';
        echo '<div class="notice notice-warning inline"><p><strong>注意：</strong>调试模式会记录详细的运行信息，可能会影响性能。建议仅在需要时启用。</p></div>';
    }
    
    public function provider_field_callback() {
        $provider = get_option('deepseek_ai_provider', 'zhipu');
        echo '<select name="deepseek_ai_provider" id="ai_provider_select">';
        echo '<option value="zhipu"' . selected($provider, 'zhipu', false) . '>智谱清言 GLM-4（推荐）</option>';
        echo '<option value="deepseek"' . selected($provider, 'deepseek', false) . '>DeepSeek</option>';
        echo '</select>';
        echo '<p class="description">选择要使用的AI服务提供商，推荐使用智谱清言GLM-4</p>';
    }
    
    public function api_key_field_callback() {
        $api_key = get_option('deepseek_ai_api_key', '');
        echo '<div class="provider-field deepseek">';
        echo '<input type="text" name="deepseek_ai_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">请输入您的DeepSeek API Key</p>';
        echo '</div>';
    }
    
    public function zhipu_api_key_field_callback() {
        $api_key = get_option('zhipu_ai_api_key', '');
        echo '<div class="provider-field zhipu">';
        echo '<input type="text" name="zhipu_ai_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">请输入您的智谱清言 API Key</p>';
        echo '</div>';
    }
    
    public function model_field_callback() {
        $model = get_option('deepseek_ai_model', 'deepseek-chat');
        echo '<div class="provider-field deepseek">';
        echo '<select name="deepseek_ai_model">';
        echo '<option value="deepseek-chat"' . selected($model, 'deepseek-chat', false) . '>deepseek-chat</option>';
        echo '<option value="deepseek-reasoner"' . selected($model, 'deepseek-reasoner', false) . '>deepseek-reasoner</option>';
        echo '</select>';
        echo '</div>';
    }
    
    public function zhipu_model_field_callback() {
        $model = get_option('zhipu_ai_model', 'glm-4-flash-250414');
        echo '<div class="provider-field zhipu">';
        echo '<select name="zhipu_ai_model">';
        echo '<option value="glm-4-flash-250414"' . selected($model, 'glm-4-flash-250414', false) . '>glm-4-flash-250414 (免费推荐)</option>';
        echo '<option value="glm-4-plus"' . selected($model, 'glm-4-plus', false) . '>glm-4-plus (收费)</option>';
        echo '<option value="glm-4-air-250414"' . selected($model, 'glm-4-air-250414', false) . '>glm-4-air-250414 (收费)</option>';
        echo '<option value="glm-4-airx"' . selected($model, 'glm-4-airx', false) . '>glm-4-airx (收费)</option>';
        echo '<option value="glm-4-long"' . selected($model, 'glm-4-long', false) . '>glm-4-long (收费)</option>';
        echo '<option value="glm-4-flashx"' . selected($model, 'glm-4-flashx', false) . '>glm-4-flashx (收费)</option>';
        echo '</select>';
        echo '<p class="description">推荐使用 glm-4-flash-250414，该模型免费且性能优秀。其他模型为收费模型。</p>';
        echo '</div>';
    }
    
    public function max_tokens_field_callback() {
        $max_tokens = get_option('deepseek_ai_max_tokens', 500);
        echo '<div class="provider-field deepseek">';
        echo '<input type="number" name="deepseek_ai_max_tokens" value="' . esc_attr($max_tokens) . '" min="100" max="2000" />';
        echo '</div>';
    }
    
    public function zhipu_max_tokens_field_callback() {
        $max_tokens = get_option('zhipu_ai_max_tokens', 500);
        echo '<div class="provider-field zhipu">';
        echo '<input type="number" name="zhipu_ai_max_tokens" value="' . esc_attr($max_tokens) . '" min="100" max="8000" />';
        echo '<p class="description">智谱清言支持更大的Token数量</p>';
        echo '</div>';
    }
    
    public function temperature_field_callback() {
        $temperature = get_option('deepseek_ai_temperature', 0.7);
        echo '<div class="provider-field deepseek">';
        echo '<input type="number" name="deepseek_ai_temperature" value="' . esc_attr($temperature) . '" min="0" max="1" step="0.1" />';
        echo '</div>';
    }
    
    public function zhipu_temperature_field_callback() {
        $temperature = get_option('zhipu_ai_temperature', 0.7);
        echo '<div class="provider-field zhipu">';
        echo '<input type="number" name="zhipu_ai_temperature" value="' . esc_attr($temperature) . '" min="0" max="1" step="0.1" />';
        echo '<p class="description">控制输出的随机性，默认0.7</p>';
        echo '</div>';
    }
    
    public function force_display_field_callback() {
        $force_display = get_option('deepseek_ai_force_display', false);
        echo '<input type="checkbox" name="deepseek_ai_force_display" value="1" ' . checked(1, $force_display, false) . ' />';
        echo '<p class="description">启用后，摘要将在所有支持的主题中强制显示，即使主题不完全兼容也能正常显示摘要内容</p>';
    }
    
    public function debug_enabled_field_callback() {
        $debug_enabled = get_option('deepseek_ai_debug_enabled', false);
        echo '<input type="checkbox" name="deepseek_ai_debug_enabled" value="1" ' . checked(1, $debug_enabled, false) . ' id="debug_enabled_checkbox" />';
        echo '<p class="description">启用调试模式将记录插件的详细运行信息</p>';
    }
    
    public function debug_level_field_callback() {
        $debug_level = get_option('deepseek_ai_debug_level', 'info');
        echo '<select name="deepseek_ai_debug_level" id="debug_level_select">';
        echo '<option value="debug"' . selected($debug_level, 'debug', false) . '>调试 (Debug) - 最详细</option>';
        echo '<option value="info"' . selected($debug_level, 'info', false) . '>信息 (Info) - 一般信息</option>';
        echo '<option value="warning"' . selected($debug_level, 'warning', false) . '>警告 (Warning) - 仅警告和错误</option>';
        echo '<option value="error"' . selected($debug_level, 'error', false) . '>错误 (Error) - 仅错误</option>';
        echo '</select>';
        echo '<p class="description">选择要记录的日志级别</p>';
    }
    
    public function debug_frontend_field_callback() {
        $debug_frontend = get_option('deepseek_ai_debug_frontend', false);
        echo '<input type="checkbox" name="deepseek_ai_debug_frontend" value="1" ' . checked(1, $debug_frontend, false) . ' class="debug_module_checkbox" />';
        echo '<p class="description">记录前端相关的调试信息</p>';
    }
    
    public function debug_ajax_field_callback() {
        $debug_ajax = get_option('deepseek_ai_debug_ajax', false);
        echo '<input type="checkbox" name="deepseek_ai_debug_ajax" value="1" ' . checked(1, $debug_ajax, false) . ' class="debug_module_checkbox" />';
        echo '<p class="description">记录AJAX请求的调试信息</p>';
    }
    
    public function debug_api_field_callback() {
        $debug_api = get_option('deepseek_ai_debug_api', false);
        echo '<input type="checkbox" name="deepseek_ai_debug_api" value="1" ' . checked(1, $debug_api, false) . ' class="debug_module_checkbox" />';
        echo '<p class="description">记录API调用的调试信息</p>';
    }
    
    public function debug_log_viewer_field_callback() {
        echo '<div id="debug-log-viewer">';
        echo '<button type="button" id="view-debug-log" class="button">查看调试日志</button> ';
        echo '<button type="button" id="clear-debug-log" class="button">清空日志</button> ';
        echo '<button type="button" id="download-debug-log" class="button">下载日志</button>';
        echo '<div id="debug-log-content" style="display:none; margin-top:10px; padding:10px; background:#f9f9f9; border:1px solid #ddd; max-height:400px; overflow-y:auto; font-family:monospace; font-size:12px; white-space:pre-wrap;"></div>';
        echo '</div>';
        echo '<p class="description">查看、清空或下载调试日志文件</p>';
    }
    
    public function add_admin_menu() {
        add_menu_page(
            '爱奇吉摘要 设置',
            '爱奇吉摘要',
            'manage_options',
            'deepseek-ai-settings',
            array($this, 'admin_page'),
            plugins_url('img/deepseek-color.svg', dirname(__FILE__)),
            25
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        // 添加调试日志
        $this->plugin->debug_log('当前页面钩子: ' . $hook);
        
        // 检查是否在文章编辑页面或设置页面
        if (in_array($hook, array('post.php', 'post-new.php', 'settings_page_deepseek-ai-settings'))) {
            // 加载样式
            wp_enqueue_style('deepseek-ai-admin', DEEPSEEK_AI_PLUGIN_URL . 'css/style.css', array(), $this->plugin->get_version());
            $this->plugin->debug_log('已加载管理页面样式文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'css/style.css');
            
            // 加载脚本
            wp_enqueue_script('deepseek-ai-admin', DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js', array('jquery'), $this->plugin->get_version(), true);
            $this->plugin->debug_log('已加载管理页面脚本文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js');
            
            // 本地化脚本
            wp_localize_script('deepseek-ai-admin', 'deepseek_ai_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('deepseek_ai_nonce'),
                'generating_text' => '正在生成中...',
                'error_text' => '生成失败，请检查API设置',
                'plugin_url' => DEEPSEEK_AI_PLUGIN_URL,
                'version' => $this->plugin->get_version(),
                'plugin_name' => 'DeepSeek AI 文章摘要生成器',
                'debug_enabled' => $this->plugin->is_debug_enabled(),
                'debug_level' => $this->plugin->get_debug_level()
            ));
            $this->plugin->debug_log('已本地化脚本数据，版本号: ' . $this->plugin->get_version());
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
    
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // 检查是否有设置更新
        if (isset($_GET['settings-updated'])) {
            // 更新插件的调试设置
            $this->plugin->update_debug_settings();
            
            add_settings_error(
                'deepseek_ai_messages',
                'deepseek_ai_message',
                '设置已保存',
                'updated'
            );
            
            $this->plugin->info_log('插件设置已更新');
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
        
        // 添加调试相关的JavaScript
        $this->add_debug_scripts();
        
        echo '</div>';
    }
    
    private function add_debug_scripts() {
        ?>
        <style type="text/css">
        .provider-field {
            display: none;
        }
        .provider-field.deepseek,
        .provider-field.zhipu {
            margin-top: 10px;
        }
        </style>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // AI服务提供商切换控制
            $('#ai_provider_select').change(function() {
                var provider = $(this).val();
                $('.provider-field').hide();
                $('.provider-field.' + provider).show();
            }).trigger('change');
            
            // 调试开关控制
            $('#debug_enabled_checkbox').change(function() {
                if ($(this).is(':checked')) {
                    $('.debug_module_checkbox').prop('disabled', false);
                    $('#debug_level_select').prop('disabled', false);
                } else {
                    $('.debug_module_checkbox').prop('disabled', true);
                    $('#debug_level_select').prop('disabled', true);
                }
            }).trigger('change');
            
            // 查看调试日志
            $('#view-debug-log').click(function() {
                var $content = $('#debug-log-content');
                if ($content.is(':visible')) {
                    $content.hide();
                    $(this).text('查看调试日志');
                } else {
                    $(this).text('正在加载...');
                    $.post(deepseek_ai_ajax.ajax_url, {
                        action: 'deepseek_ai_get_debug_log',
                        nonce: deepseek_ai_ajax.nonce
                    }, function(response) {
                        if (response.success) {
                            $content.text(response.data).show();
                            $('#view-debug-log').text('隐藏日志');
                        } else {
                            alert('获取日志失败: ' + response.data);
                            $('#view-debug-log').text('查看调试日志');
                        }
                    }).fail(function() {
                        alert('请求失败');
                        $('#view-debug-log').text('查看调试日志');
                    });
                }
            });
            
            // 清空调试日志
            $('#clear-debug-log').click(function() {
                if (confirm('确定要清空调试日志吗？此操作不可恢复。')) {
                    $.post(deepseek_ai_ajax.ajax_url, {
                        action: 'deepseek_ai_clear_debug_log',
                        nonce: deepseek_ai_ajax.nonce
                    }, function(response) {
                        if (response.success) {
                            $('#debug-log-content').text('').hide();
                            $('#view-debug-log').text('查看调试日志');
                            alert('日志已清空');
                        } else {
                            alert('清空失败: ' + response.data);
                        }
                    });
                }
            });
            
            // 下载调试日志
            $('#download-debug-log').click(function() {
                var url = deepseek_ai_ajax.ajax_url + '?action=deepseek_ai_download_debug_log&nonce=' + deepseek_ai_ajax.nonce;
                window.open(url, '_blank');
            });
        });
        </script>
        <?php
    }
}

} // 结束 class_exists 检查