<?php
/**
 * 管理后台功能类
 *
 * @package DeepSeekAISummarizer
 * @since 2.1.0
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

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
            plugins_url('img/deepseek-color.svg', dirname(__FILE__)),
            25
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        // 添加调试日志
        $this->plugin->write_log('当前页面钩子: ' . $hook);
        
        // 检查是否在文章编辑页面或设置页面
        if (in_array($hook, array('post.php', 'post-new.php', 'settings_page_deepseek-ai-settings'))) {
            // 加载样式
            wp_enqueue_style('deepseek-ai-admin', DEEPSEEK_AI_PLUGIN_URL . 'css/style.css', array(), $this->plugin->get_version());
            $this->plugin->write_log('已加载管理页面样式文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'css/style.css');
            
            // 加载脚本
            wp_enqueue_script('deepseek-ai-admin', DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js', array('jquery'), $this->plugin->get_version(), true);
            $this->plugin->write_log('已加载管理页面脚本文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js');
            
            // 本地化脚本
            wp_localize_script('deepseek-ai-admin', 'deepseek_ai_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('deepseek_ai_nonce'),
                'generating_text' => '正在生成中...',
                'error_text' => '生成失败，请检查API设置',
                'plugin_url' => DEEPSEEK_AI_PLUGIN_URL,
                'version' => $this->plugin->get_version(),
                'plugin_name' => 'DeepSeek AI 文章摘要生成器'
            ));
            $this->plugin->write_log('已本地化脚本数据，版本号: ' . $this->plugin->get_version());
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
}