<?php
/**
 * 前端显示功能类
 *
 * @package DeepSeekAISummarizer
 * @since 2.1.0
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

class DeepSeekAI_Frontend {
    
    private $plugin;
    
    public function __construct($plugin) {
        $this->plugin = $plugin;
        
        // 加载前端样式和脚本
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // 在文章内容前显示摘要
        add_filter('the_content', array($this, 'display_summary_before_content'));
        
        // 添加SEO元数据到头部
        add_action('wp_head', array($this, 'add_seo_meta_tags'));
        
        // 添加强制显示摘要的备用机制
        add_action('wp_footer', array($this, 'force_display_summary_fallback'));
    }
    
    public function enqueue_frontend_scripts() {
        if (is_single()) {
            // 添加调试日志
            $this->plugin->write_log('正在加载前端脚本');
            
            // 加载样式
            wp_enqueue_style('deepseek-ai-frontend', DEEPSEEK_AI_PLUGIN_URL . 'css/style.css', array(), $this->plugin->get_version());
            $this->plugin->write_log('已加载前端样式文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'css/style.css');
            
            // 加载脚本
            wp_enqueue_script('deepseek-ai-frontend', DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js', array('jquery'), $this->plugin->get_version(), true);
            $this->plugin->write_log('已加载前端脚本文件: ' . DEEPSEEK_AI_PLUGIN_URL . 'js/scripts.js');
            
            // 本地化脚本
            wp_localize_script('deepseek-ai-frontend', 'deepseek_ai_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('deepseek_ai_nonce'),
                'plugin_url' => DEEPSEEK_AI_PLUGIN_URL,
                'version' => $this->plugin->get_version(),
                'plugin_name' => 'DeepSeek AI 文章摘要生成器'
            ));
            $this->plugin->write_log('已本地化前端脚本数据，版本号: ' . $this->plugin->get_version());
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
                $summary_html = $this->generate_summary_html($summary, $force_display);
                $content = $summary_html . $content;
            }
        }
        
        return $content;
    }
    
    private function generate_summary_html($summary, $force_display = false) {
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
        
        return $summary_html;
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