<?php
/**
 * 前端显示功能类
 *
 * @package DeepSeekAISummarizer
 * @since 3.4.5
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
         
         // 获取SEO设置
         $seo_title = get_post_meta($post_id, '_deepseek_ai_seo_title', true);
         $seo_description = get_post_meta($post_id, '_deepseek_ai_seo_description', true);
         $seo_keywords = get_post_meta($post_id, '_deepseek_ai_seo_keywords', true);
         
         // 获取社交标签设置
         $social_title = get_post_meta($post_id, '_deepseek_ai_social_title', true);
         $social_description = get_post_meta($post_id, '_deepseek_ai_social_description', true);
         $social_image = get_post_meta($post_id, '_deepseek_ai_social_image', true);
         $wechat_image = get_post_meta($post_id, '_deepseek_ai_wechat_image', true);
         
         // 控制台打印SEO数据（调试用）
         //echo '<script type="text/javascript">';
         //echo 'console.log("DeepSeek AI SEO 数据调试:");';
         //echo 'console.log("文章ID: ' . esc_js($post_id) . '");';
         //echo 'console.log("SEO标题: ' . esc_js($seo_title) . '");';
         //echo 'console.log("SEO描述: ' . esc_js($seo_description) . '");';
         //echo 'console.log("SEO关键词: ' . esc_js($seo_keywords) . '");';
         //echo '</script>';
         
         // 如果没有设置SEO描述，使用摘要作为备选
         if (empty($seo_description)) {
             $summary = $this->get_summary($post_id);
             if (!empty($summary)) {
                 // 清理HTML标签并截取适当长度
                 $seo_description = wp_strip_all_tags($summary);
                 $seo_description = wp_trim_words($seo_description, 30, '...');
             }
         }
         
         // 确定最终使用的标题和描述（优先使用社交标签设置）
         $final_title = !empty($social_title) ? $social_title : $seo_title;
         $final_description = !empty($social_description) ? $social_description : $seo_description;
         
         // 输出基础SEO标签
         if (!empty($seo_title)) {
             echo '<title>' . esc_html($seo_title) . '</title>' . "\n";
         }
         
         if (!empty($seo_description)) {
             echo '<meta name="description" content="' . esc_attr($seo_description) . '">' . "\n";
         }
         
         if (!empty($seo_keywords)) {
             echo '<meta name="keywords" content="' . esc_attr($seo_keywords) . '">' . "\n";
         }
         
         // 输出Open Graph标签（用于Facebook等）
         if (!empty($final_title)) {
             echo '<meta property="og:title" content="' . esc_attr($final_title) . '">' . "\n";
         }
         
         if (!empty($final_description)) {
             echo '<meta property="og:description" content="' . esc_attr($final_description) . '">' . "\n";
         }
         
         echo '<meta property="og:type" content="article">' . "\n";
         echo '<meta property="og:url" content="' . esc_attr(get_permalink($post_id)) . '">' . "\n";
         
         // 输出Twitter Card标签
         echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
         
         if (!empty($final_title)) {
             echo '<meta name="twitter:title" content="' . esc_attr($final_title) . '">' . "\n";
         }
         
         if (!empty($final_description)) {
             echo '<meta name="twitter:description" content="' . esc_attr($final_description) . '">' . "\n";
         }
         
         // 输出图片meta标签（优先级：社交图片 > 特色图片）
         $image_url = '';
         
         if (!empty($social_image)) {
            // 直接使用原始URL，不添加前缀
            $image_url = $social_image;
        } elseif (has_post_thumbnail($post_id)) {
            $image_url = get_the_post_thumbnail_url($post_id, 'large');
        }
         
         if ($image_url) {
             echo '<meta property="og:image" content="' . esc_attr($image_url) . '">' . "\n";
             echo '<meta name="twitter:image" content="' . esc_attr($image_url) . '">' . "\n";
         }
         
         // 微信分享专用标签
        if (!empty($wechat_image)) {
            // 直接使用原始URL，不添加前缀
            echo '<meta itemprop="image" content="' . esc_attr($wechat_image) . '">' . "\n";
            echo '<meta name="weixin:image" content="' . esc_attr($wechat_image) . '">' . "\n";
        }
         
         // 微信分享标题和描述
         if (!empty($final_title)) {
             echo '<meta name="weixin:title" content="' . esc_attr($final_title) . '">' . "\n";
         }
         
         if (!empty($final_description)) {
             echo '<meta name="weixin:description" content="' . esc_attr($final_description) . '">' . "\n";
         }
         
         // 添加结构化数据 (JSON-LD)
         $this->add_structured_data($post_id, $final_title, $final_description, $seo_keywords, $image_url);
     }
     
     /**
      * 添加结构化数据 (JSON-LD)
      */
     private function add_structured_data($post_id, $seo_title = '', $seo_description = '', $seo_keywords = '', $image_url = '') {
         $post = get_post($post_id);
         if (!$post) {
             return;
         }
         
         // 获取文章基本信息
         $title = !empty($seo_title) ? $seo_title : get_the_title($post_id);
         $description = !empty($seo_description) ? $seo_description : wp_trim_words(wp_strip_all_tags($post->post_content), 30, '...');
         $url = get_permalink($post_id);
         $date_published = get_the_date('c', $post_id);
         $date_modified = get_the_modified_date('c', $post_id);
         
         // 获取作者信息
         $author = get_userdata($post->post_author);
         $author_name = $author ? $author->display_name : get_bloginfo('name');
         
         // 获取网站信息
         $site_name = get_bloginfo('name');
         $site_url = home_url();
         
         // 构建结构化数据
         $structured_data = array(
             '@context' => 'https://schema.org',
             '@type' => 'Article',
             'headline' => $title,
             'description' => $description,
             'url' => $url,
             'datePublished' => $date_published,
             'dateModified' => $date_modified,
             'author' => array(
                 '@type' => 'Person',
                 'name' => $author_name
             ),
             'publisher' => array(
                 '@type' => 'Organization',
                 'name' => $site_name,
                 'url' => $site_url
             )
         );
         
         // 添加图片（优先使用传入的图片URL）
         if (!empty($image_url)) {
             $structured_data['image'] = array(
                 '@type' => 'ImageObject',
                 'url' => $image_url
             );
         } elseif (has_post_thumbnail($post_id)) {
             $thumbnail_url = get_the_post_thumbnail_url($post_id, 'large');
             if ($thumbnail_url) {
                 $structured_data['image'] = array(
                     '@type' => 'ImageObject',
                     'url' => $thumbnail_url
                 );
             }
         }
         
         // 添加关键词
         if (!empty($seo_keywords)) {
             $keywords_array = array_map('trim', explode(',', $seo_keywords));
             $structured_data['keywords'] = $keywords_array;
         }
         
         // 添加文章分类
         $categories = get_the_category($post_id);
         if (!empty($categories)) {
             $structured_data['articleSection'] = $categories[0]->name;
         }
         
         // 输出结构化数据
         echo '<script type="application/ld+json">' . "\n";
         echo wp_json_encode($structured_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
         echo '</script>' . "\n";
     }
     
     /**
      * 确保URL是绝对链接
      */
     private function ensure_absolute_url($url) {
         if (empty($url)) {
             return '';
         }
         
         // 如果已经是完整的URL，直接返回
         if (filter_var($url, FILTER_VALIDATE_URL)) {
             return $url;
         }
         
         // 如果是相对路径，转换为绝对路径
         if (strpos($url, '/') === 0) {
             return home_url($url);
         }
         
         // 如果是WordPress上传目录的相对路径
         $upload_dir = wp_upload_dir();
         if (strpos($url, $upload_dir['subdir']) !== false || strpos($url, basename($upload_dir['basedir'])) !== false) {
             return $upload_dir['baseurl'] . '/' . ltrim($url, '/');
         }
         
         // 默认情况下，假设是相对于网站根目录的路径
         return home_url('/' . ltrim($url, '/'));
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