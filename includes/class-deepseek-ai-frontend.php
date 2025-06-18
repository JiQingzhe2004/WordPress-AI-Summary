<?php
/**
 * 前端显示功能类
 *
 * @package DeepSeekAISummarizer
 * @since 3.2.0
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 防止重复声明类
if (!class_exists('DeepSeekAI_Frontend')) {

class DeepSeekAI_Frontend {
    
    private $plugin;
    
    public function __construct($plugin) {
        $this->plugin = $plugin;
        
        // 在内容前显示摘要
        add_filter('the_content', array($this, 'display_summary_before_content'), 10);
        
        // 强制显示摘要的备用机制
        add_action('wp_footer', array($this, 'force_display_summary_fallback'));
        
        // 加载前端资源
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // 添加内联配置
        add_action('wp_head', array($this, 'add_frontend_config'));
        
        // 添加SEO元数据
        add_action('wp_head', array($this, 'add_seo_meta_tags'), 1);
    }
    
    /**
     * 加载前端CSS和JavaScript资源
     */
    public function enqueue_frontend_assets() {
        // 只在需要显示摘要的页面加载资源
        if (!$this->should_load_assets()) {
            return;
        }
        
        $plugin_url = plugin_dir_url(dirname(__FILE__));
         $version = $this->get_plugin_version();
        
        // 加载CSS
        wp_enqueue_style(
            'deepseek-ai-frontend',
            $plugin_url . 'assets/css/frontend.css',
            array(),
            $version
        );
        
        // 加载JavaScript
        wp_enqueue_script(
            'deepseek-ai-frontend',
            $plugin_url . 'assets/js/frontend.js',
            array('jquery'),
            $version,
            true
        );
        
        // 本地化脚本
        wp_localize_script('deepseek-ai-frontend', 'deepseekAI', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('deepseek-ai-frontend'),
            'config' => array(
                'typewriterSpeed' => $this->get_typewriter_speed(),
                'debugMode' => $this->plugin->get_debug_setting('debug_frontend'),
                'forceDisplay' => get_option('deepseek_ai_force_display', false)
            ),
            'i18n' => array(
                'loading' => esc_html__('正在生成摘要...', 'deepseek-ai-summarizer'),
                'error' => esc_html__('摘要生成失败', 'deepseek-ai-summarizer'),
                'retry' => esc_html__('重试', 'deepseek-ai-summarizer')
            )
        ));
    }
    
    /**
     * 添加前端配置到页面头部
     */
    public function add_frontend_config() {
        if (!$this->should_load_assets()) {
            return;
        }
        
        echo '<script type="text/javascript">';
        echo 'window.deepseekAIDebug = ' . ($this->plugin->get_debug_setting('debug_frontend') ? 'true' : 'false') . ';';
        echo '</script>';
    }
    
    /**
     * 判断是否应该加载前端资源
     */
    private function should_load_assets() {
        // 在单篇文章或页面，或者启用了强制显示的情况下加载
        return (is_single() || is_page()) || get_option('deepseek_ai_force_display', false);
    }
    
    public function enqueue_frontend_scripts() {
        // 在前端页面加载脚本（包括主页、文章页、分类页等）
        if (is_front_page() || is_home() || is_single() || is_category() || is_tag() || is_archive()) {
            // 添加调试日志
            if ($this->plugin->get_debug_setting('debug_frontend')) {
                $page_type = '';
                if (is_front_page()) $page_type = '首页';
                elseif (is_home()) $page_type = '博客主页';
                elseif (is_single()) $page_type = '单篇文章';
                elseif (is_category()) $page_type = '分类页';
                elseif (is_tag()) $page_type = '标签页';
                elseif (is_archive()) $page_type = '归档页';
                $this->plugin->info_log('正在加载前端脚本 - 页面类型: ' . $page_type);
            }
            
            // 加载样式
            wp_enqueue_style('deepseek-ai-frontend', DEEPSEEK_AI_PLUGIN_URL . 'css/style.css', array(), $this->plugin->get_version());
            if ($this->plugin->get_debug_setting('debug_frontend')) {
                $this->plugin->debug_log('已加载前端样式文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'css/style.css');
            }
            
            // 加载脚本
            wp_enqueue_script('deepseek-ai-frontend', DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js', array('jquery'), $this->plugin->get_version(), true);
            if ($this->plugin->get_debug_setting('debug_frontend')) {
                $this->plugin->debug_log('已加载前端脚本文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js');
            }
            
            // 本地化脚本
            wp_localize_script('deepseek-ai-frontend', 'deepseek_ai_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('deepseek-ai-nonce'),
                'loadingText' => esc_js(__('正在生成摘要...', '爱奇吉智能摘要')),
                'errorText' => esc_js(__('生成摘要时出错，请重试', '爱奇吉智能摘要')),
                'plugin_url' => esc_url(DEEPSEEK_AI_PLUGIN_URL),
                'version' => esc_attr($this->plugin->get_version()),
                'plugin_name' => '爱奇吉智能摘要',
                'debug_enabled' => $this->plugin->get_debug_setting('debug_enabled'),
                'debug_frontend' => $this->plugin->get_debug_setting('debug_frontend')
            ));
            if ($this->plugin->get_debug_setting('debug_frontend')) {
                $this->plugin->debug_log('已本地化前端脚本数据，版本号: ' . $this->plugin->get_version());
            }
        }
    }
    
    public function display_summary_before_content($content) {
        $start_time = microtime(true);
        
        try {
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
            
            if ($this->plugin->get_debug_setting('debug_frontend')) {
                $this->plugin->debug_log('摘要显示检查 - 强制显示: ' . ($force_display ? '是' : '否') . ', 应该显示: ' . ($should_display ? '是' : '否'));
            }
            
            if ($should_display) {
                // 检查内容中是否已经包含摘要容器，避免重复显示
                if (strpos($content, 'deepseek-ai-summary-container') !== false) {
                    if ($this->plugin->get_debug_setting('debug_frontend')) {
                        $this->plugin->debug_log('内容中已存在摘要容器，跳过重复添加');
                    }
                    $this->performance_log('display_summary_before_content_skip_duplicate', $start_time);
                    return $content;
                }
                
                $post_id = get_the_ID();
                if (!$post_id) {
                    $this->handle_error('INVALID_POST_ID', '无法获取文章ID');
                    return $content;
                }
                
                // 使用缓存优化的摘要获取方法
                $summary = $this->get_summary($post_id);
                
                if ($this->plugin->get_debug_setting('debug_frontend')) {
                    $this->plugin->debug_log('摘要内容长度: ' . strlen($summary));
                }
                
                if (!empty($summary)) {
                    // 清理摘要内容
                    $summary = $this->sanitize_summary($summary);
                    $summary_html = $this->generate_summary_html($summary, $force_display);
                    $content = $summary_html . $content;
                    if ($this->plugin->get_debug_setting('debug_frontend')) {
                        $this->plugin->info_log('摘要已添加到内容前');
                    }
                    $this->performance_log('display_summary_before_content_success', $start_time);
                }
            }
            
            return $content;
            
        } catch (Exception $e) {
            $this->handle_error('DISPLAY_SUMMARY_ERROR', $e->getMessage(), array(
                'post_id' => get_the_ID(),
                'content_length' => strlen($content)
            ));
            return $content;
        }
    }
    
    private function generate_summary_html($summary, $force_display = false) {
        // 使用模板文件生成HTML，移除内联样式
        $summary_html = '<div class="deepseek-ai-summary-container deepseek-ai-loaded" data-summary="' . esc_attr($summary) . '" data-post-id="' . get_the_ID() . '">';
        $summary_html .= '<div class="deepseek-ai-summary-header">';
        $summary_html .= '<span class="deepseek-ai-icon">🤖</span>';
        $summary_html .= '<span class="deepseek-ai-title">' . esc_html__('AI 智能摘要（爱奇吉）', 'deepseek-ai-summarizer') . '</span>';
        $summary_html .= '</div>';
        // 输出空的摘要容器，等待JavaScript打字机效果填充
        $summary_html .= '<div class="deepseek-ai-summary-content deepseek-ai-content" data-original-text="' . esc_attr($summary) . '" data-typewriter-speed="' . esc_attr($this->get_typewriter_speed()) . '"></div>';
        $summary_html .= '</div>';
        
        // 强制显示模式下不需要额外的JavaScript，因为已经通过display_summary_before_content和force_display_summary_fallback处理
        
        return $summary_html;
    }
    

    
    public function force_display_summary_fallback() {
        $start_time = microtime(true);
        
        try {
            // 检查是否启用强制显示
            if (!get_option('deepseek_ai_force_display', false)) {
                return;
            }

            // 只在单篇文章页面执行
            if (!is_single()) {
                return;
            }

            $post_id = get_the_ID();
            if (!$post_id) {
                $this->handle_error('INVALID_POST_ID', '强制显示备用机制：无法获取文章ID');
                return;
            }

            // 使用缓存优化的摘要获取方法
            $summary = $this->get_summary($post_id);
            if (empty($summary)) {
                $this->performance_log('force_display_summary_fallback_no_summary', $start_time);
                return;
            }

            // 清理摘要内容
            $summary = $this->sanitize_summary($summary);
            
            // 生成nonce用于安全验证
            $nonce = wp_create_nonce('deepseek-ai-force-display');
            
            // 使用外部JavaScript文件的强制显示功能
             $summary_html = $this->generate_summary_html($summary, true);
             echo '<script type="text/javascript" id="deepseek-ai-fallback-script">';
             echo 'if (window.DeepSeekAI) {';
             echo 'window.DeepSeekAI.forceDisplaySummary(' . wp_json_encode($summary_html) . ', 1000);';
             echo '} else {';
             echo 'document.addEventListener("DOMContentLoaded", function() {';
             echo 'if (window.DeepSeekAI) {';
             echo 'window.DeepSeekAI.forceDisplaySummary(' . wp_json_encode($summary_html) . ', 1000);';
             echo '}';
             echo '});';
             echo '}';
             echo '</script>';
            
            $this->performance_log('force_display_summary_fallback_success', $start_time);
            
        } catch (Exception $e) {
            $this->handle_error('FORCE_DISPLAY_ERROR', $e->getMessage(), array(
                'post_id' => get_the_ID()
            ));
        }
    }

    public function display_summary($post_id) {
        $summary = $this->get_summary($post_id);
        if (!empty($summary)) {
            echo wp_kses_post($summary);
        }
    }

    public function get_summary($post_id) {
        // 使用缓存机制提高性能
        $cache_key = 'deepseek_summary_' . $post_id;
        $summary = wp_cache_get($cache_key, 'deepseek_ai');
        
        if (false === $summary) {
            $summary = get_post_meta($post_id, '_deepseek_ai_summary', true);
            if (!empty($summary)) {
                $summary = wp_kses_post($summary);
                // 缓存1小时
                wp_cache_set($cache_key, $summary, 'deepseek_ai', HOUR_IN_SECONDS);
            } else {
                $summary = '';
            }
        }
        
        return $summary;
    }
    
    /**
     * 获取打字机效果速度配置
     */
    private function get_typewriter_speed() {
        return get_option('deepseek_ai_typewriter_speed', 20);
    }
    
    /**
     * 验证nonce安全性
     */
    private function verify_nonce($action = 'deepseek-ai-nonce') {
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], $action)) {
            wp_die(esc_html__('安全验证失败', 'deepseek-ai-summarizer'));
        }
    }
    
    /**
     * 清理和验证摘要内容
     */
    private function sanitize_summary($summary) {
        $allowed_tags = array(
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'em' => array(),
            'span' => array('class' => array())
        );
        return wp_kses($summary, $allowed_tags);
    }
    
    /**
      * 性能监控日志
      */
     private function performance_log($action, $start_time) {
         if ($this->plugin->get_debug_setting('debug_performance')) {
             $execution_time = microtime(true) - $start_time;
             $this->plugin->debug_log(sprintf(
                 '性能监控 - %s: %.4f秒',
                 $action,
                 $execution_time
             ));
         }
     }
     
     /**
      * 统一错误处理
      */
     private function handle_error($error_code, $error_message, $context = array()) {
         if ($this->plugin->get_debug_setting('debug_enabled')) {
             $this->plugin->error_log(sprintf(
                 '[%s] %s - Context: %s',
                 $error_code,
                 $error_message,
                 wp_json_encode($context)
             ));
         }
     }
     
     /**
      * 获取插件版本号
      */
     private function get_plugin_version() {
         if (method_exists($this->plugin, 'get_version')) {
             return $this->plugin->get_version();
         }
         
         // 备用方案：从插件文件头部获取版本
         $plugin_data = get_file_data(
             dirname(dirname(__FILE__)) . '/deepseek-ai-summarizer.php',
             array('Version' => 'Version')
         );
         
         return !empty($plugin_data['Version']) ? $plugin_data['Version'] : '1.0.0';
     }
     
     /**
      * 添加SEO元数据
      */
     public function add_seo_meta_tags() {
         if (!is_single() && !is_page()) {
             return;
         }
         
         $post_id = get_the_ID();
         if (!$post_id) {
             return;
         }
         
         $summary = $this->get_summary($post_id);
         if (!empty($summary)) {
             // 清理HTML标签并截取适当长度
             $description = wp_strip_all_tags($summary);
             $description = wp_trim_words($description, 30, '...');
             
             echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
             echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
             echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
         }
     }
     
     /**
      * 清理缓存
      */
     public function clear_summary_cache($post_id = null) {
         if ($post_id) {
             $cache_key = 'deepseek_summary_' . $post_id;
             wp_cache_delete($cache_key, 'deepseek_ai');
         } else {
             // 清理所有相关缓存
             wp_cache_flush_group('deepseek_ai');
         }  
     }
}

} // 结束 class_exists 检查