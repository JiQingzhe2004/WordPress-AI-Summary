<?php
/**
 * 前端显示功能类
 *
 * @package DeepSeekAISummarizer
 * @since 2.3.0
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
                'nonce' => wp_create_nonce('deepseek_ai_nonce'),
                'plugin_url' => DEEPSEEK_AI_PLUGIN_URL,
                'version' => $this->plugin->get_version(),
                'plugin_name' => 'DeepSeek AI 文章摘要生成器',
                'debug_enabled' => $this->plugin->get_debug_setting('debug_enabled'),
                'debug_frontend' => $this->plugin->get_debug_setting('debug_frontend')
            ));
            if ($this->plugin->get_debug_setting('debug_frontend')) {
                $this->plugin->debug_log('已本地化前端脚本数据，版本号: ' . $this->plugin->get_version());
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
        
        if ($this->plugin->get_debug_setting('debug_frontend')) {
            $this->plugin->debug_log('摘要显示检查 - 强制显示: ' . ($force_display ? '是' : '否') . ', 应该显示: ' . ($should_display ? '是' : '否'));
        }
        
        if ($should_display) {
            // 优先使用WordPress原生摘要
            $summary = get_the_excerpt();
            
            // 如果原生摘要为空，则尝试使用自定义字段
            if (empty($summary)) {
                $summary = get_post_meta(get_the_ID(), '_deepseek_ai_summary', true);
            }
            
            if ($this->plugin->get_debug_setting('debug_frontend')) {
                $this->plugin->debug_log('摘要内容长度: ' . strlen($summary));
            }
            
            if (!empty($summary)) {
                $summary_html = $this->generate_summary_html($summary, $force_display);
                $content = $summary_html . $content;
                if ($this->plugin->get_debug_setting('debug_frontend')) {
                    $this->plugin->info_log('摘要已添加到内容前');
                }
            }
        }
        
        return $content;
    }
    
    private function generate_summary_html($summary, $force_display = false) {
        $summary_html = '<div class="deepseek-ai-summary-container deepseek-ai-loaded" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007cba; border-radius: 4px;" data-summary="' . esc_attr($summary) . '">';
        $summary_html .= '<div class="deepseek-ai-summary-header" style="display: flex; align-items: center; margin-bottom: 10px; font-weight: bold; color: #007cba;">';
        $summary_html .= '<span class="deepseek-ai-icon">🤖</span>';
        $summary_html .= '<span class="deepseek-ai-title">AI 智能摘要（爱奇吉）</span>';
        $summary_html .= '</div>';
        // 将摘要内容存储在data属性中，等待打字机效果填充
        $summary_html .= '<div class="deepseek-ai-summary-content deepseek-ai-content" style="line-height: 1.6; color: #333;" data-original-text="' . esc_attr($summary) . '"></div>';
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
                
                // 触发自定义事件通知摘要已加载到页面
                setTimeout(function() {
                    if (typeof jQuery !== "undefined") {
                        jQuery(document).trigger("deepseekAiSummaryLoaded");
                    } else {
                        var event = new Event("deepseekAiSummaryLoaded");
                        document.dispatchEvent(event);
                    }
                }, 100);
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
        
        // 安全处理摘要内容
        $escaped_summary = esc_js($summary);
        $attr_summary = esc_attr($summary);
        
        // 输出JavaScript代码，在页面加载完成后强制插入摘要
        ?>
        <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            // 检查是否已经存在摘要容器
            if (document.querySelector(".deepseek-ai-summary-container")) {
                return;
            }
            
            // 创建摘要HTML（内容为空，等待打字机效果填充）
            var summaryHtml = '<div class="deepseek-ai-summary-container deepseek-ai-loaded" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007cba; border-radius: 4px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;" data-summary="<?php echo addslashes($attr_summary); ?>">' +
                '<div class="deepseek-ai-summary-header" style="display: flex; align-items: center; margin-bottom: 10px; font-weight: bold; color: #007cba;">' +
                    '<span class="deepseek-ai-icon">🤖</span>' +
                    '<span class="deepseek-ai-title">AI 智能摘要（爱奇吉）</span>' +
                '</div>' +
                '<div class="deepseek-ai-summary-content deepseek-ai-content" style="line-height: 1.6; color: #333;" data-original-text="<?php echo addslashes($attr_summary); ?>"></div>' +
            '</div>';
        
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
                "main",
                "#content", // 增加常见的内容区域选择器
                ".article-content",
                ".article-body",
                "div[class*=\"content\"]", // 匹配包含content的类名
                "div[class*=\"article\"]" // 匹配包含article的类名
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
                    
                    // 触发打字机效果
                    var summaryContent = summaryElement.querySelector(".deepseek-ai-summary-content");
                    var originalText = summaryContent.getAttribute("data-original-text");
                    if (originalText) {
                        // 优先使用jQuery方式调用打字机效果
                        setTimeout(function() {
                            if (typeof window.jQuery !== "undefined" && window.typeWriter) {
                                console.log("使用jQuery打字机效果显示摘要");
                                window.typeWriter(window.jQuery(summaryContent), originalText, 20);
                            } else {
                                // 如果jQuery或打字机函数不可用，使用原生JS实现打字效果
                                console.log("使用原生JS打字机效果显示摘要");
                                var text = originalText;
                                summaryContent.textContent = "";
                                
                                var i = 0;
                                var timer = setInterval(function() {
                                    if (i < text.length) {
                                        summaryContent.textContent += text.charAt(i);
                                        i++;
                                    } else {
                                        clearInterval(timer);
                                        // 触发摘要加载完成事件
                                        if (typeof window.jQuery !== "undefined") {
                                            window.jQuery(document).trigger("deepseekAiSummaryLoaded");
                                        } else {
                                            var event = new Event("deepseekAiSummaryLoaded");
                                            document.dispatchEvent(event);
                                        }
                                    }
                                }, 20);
                            }
                            
                            // jQuery版本的打字机效果也触发事件
                            if (typeof window.jQuery !== "undefined" && window.typeWriter) {
                                setTimeout(function() {
                                    window.jQuery(document).trigger("deepseekAiSummaryLoaded");
                                }, 1000);
                            }
                        }, 500);
                    } else {
                        // 没有原始文本，直接显示
                        summaryContent.textContent = "<?php echo $escaped_summary; ?>";
                        
                        // 触发摘要加载事件
                        setTimeout(function() {
                            if (typeof window.jQuery !== "undefined") {
                                window.jQuery(document).trigger("deepseekAiSummaryLoaded");
                            } else {
                                var event = new Event("deepseekAiSummaryLoaded");
                                document.dispatchEvent(event);
                            }
                        }, 100);
                    }
                }
            }
            
            if (!inserted) {
                console.log("未找到合适的内容区域来显示AI摘要，尝试直接插入到<body>中");
                // 最后的尝试，直接添加到body的顶部
                var bodyElement = document.body;
                if (bodyElement) {
                    var tempDiv = document.createElement("div");
                    tempDiv.innerHTML = summaryHtml;
                    var summaryElement = tempDiv.firstChild;
                    
                    // 找到第一个有意义的内容元素
                    var firstContentElement = bodyElement.querySelector("h1, h2, p, article, .content, #content");
                    if (firstContentElement) {
                        // 插入到第一个内容元素前
                        firstContentElement.parentNode.insertBefore(summaryElement, firstContentElement);
                    } else {
                        // 或者直接添加到body的开头
                        bodyElement.insertBefore(summaryElement, bodyElement.firstChild);
                    }
                    
                    console.log("AI摘要已直接添加到页面中");
                    
                    // 触发打字机效果，使用与上面相同的逻辑
                    var summaryContent = summaryElement.querySelector(".deepseek-ai-summary-content");
                    var originalText = summaryContent.getAttribute("data-original-text");
                    
                    if (originalText) {
                        // 优先使用jQuery方式调用打字机效果
                        setTimeout(function() {
                            if (typeof window.jQuery !== "undefined" && window.typeWriter) {
                                console.log("使用jQuery打字机效果显示摘要");
                                window.typeWriter(window.jQuery(summaryContent), originalText, 20);
                            } else {
                                // 如果jQuery或打字机函数不可用，使用原生JS实现打字效果
                                console.log("使用原生JS打字机效果显示摘要");
                                var text = originalText;
                                summaryContent.textContent = "";
                                
                                var i = 0;
                                var timer = setInterval(function() {
                                    if (i < text.length) {
                                        summaryContent.textContent += text.charAt(i);
                                        i++;
                                    } else {
                                        clearInterval(timer);
                                        // 触发摘要加载完成事件
                                        if (typeof window.jQuery !== "undefined") {
                                            window.jQuery(document).trigger("deepseekAiSummaryLoaded");
                                        } else {
                                            var event = new Event("deepseekAiSummaryLoaded");
                                            document.dispatchEvent(event);
                                        }
                                    }
                                }, 20);
                            }
                            
                            // jQuery版本的打字机效果也触发事件
                            if (typeof window.jQuery !== "undefined" && window.typeWriter) {
                                setTimeout(function() {
                                    window.jQuery(document).trigger("deepseekAiSummaryLoaded");
                                }, 1000);
                            }
                        }, 500);
                    } else {
                        summaryContent.textContent = "<?php echo $escaped_summary; ?>";
                        
                        // 触发摘要加载事件
                        setTimeout(function() {
                            if (typeof window.jQuery !== "undefined") {
                                window.jQuery(document).trigger("deepseekAiSummaryLoaded");
                            } else {
                                var event = new Event("deepseekAiSummaryLoaded");
                                document.dispatchEvent(event);
                            }
                        }, 100);
                    }
                }
            }
            
            // 添加重试机制，如果打字机效果未生效，则直接显示文本
            setTimeout(function() {
                document.querySelectorAll(".deepseek-ai-summary-content").forEach(function(element) {
                    if (element.textContent.trim() === "") {
                        var originalText = element.getAttribute("data-original-text");
                        if (originalText) {
                            console.log("检测到打字机效果未生效，直接显示摘要内容");
                            element.textContent = originalText;
                        }
                    }
                });
            }, 3000);
        });
        </script>
        <?php
    }
}