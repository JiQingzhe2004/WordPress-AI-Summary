<?php
/**
 * AJAX处理功能类
 *
 * @package DeepSeekAISummarizer
 * @since 3.0.3
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 防止重复声明类
if (!class_exists('DeepSeekAI_Ajax')) {

class DeepSeekAI_Ajax {
    
    private $plugin;
    
    public function __construct($plugin) {
        $this->plugin = $plugin;
        
        // AJAX处理
        add_action('wp_ajax_generate_summary', array($this, 'ajax_generate_summary'));
        add_action('wp_ajax_generate_seo', array($this, 'ajax_generate_seo'));
    }
    
    public function ajax_generate_summary() {
        // 记录开始日志
        $this->plugin->info_log('开始生成摘要');
        
        // 检查是否启用AJAX调试
        $debug_ajax = get_option('deepseek_ai_debug_ajax', false);
        
        try {
            check_ajax_referer('deepseek_ai_nonce', 'nonce');
            if ($debug_ajax) {
                $this->plugin->debug_log('AJAX摘要生成 - Nonce验证通过');
            }
            
            $post_id = intval($_POST['post_id']);
            if ($debug_ajax) {
                $this->plugin->debug_log('AJAX摘要生成 - 文章ID = ' . $post_id);
            }
            
            $post = get_post($post_id);
            
            if (!$post) {
                $this->plugin->error_log('AJAX摘要生成失败 - 文章不存在，ID = ' . $post_id);
                wp_send_json_error('文章不存在');
                return;
            }
            
            $content = wp_strip_all_tags($post->post_content);
            if ($debug_ajax) {
                $this->plugin->debug_log('AJAX摘要生成 - 文章内容长度 = ' . strlen($content));
            }
            
            if (empty($content)) {
                $this->plugin->warning_log('AJAX摘要生成失败 - 文章内容为空');
                wp_send_json_error('文章内容为空，无法生成摘要');
                return;
            }
            
            $summary = $this->plugin->get_api()->generate_ai_content($content, 'summary');
            
            if ($summary) {
                // 更新WordPress原生摘要字段
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_excerpt' => $summary
                ));
                
                // 更新自定义字段（使用update_post_meta确保只保存一次）
                update_post_meta($post_id, '_deepseek_ai_summary', $summary);
                
                $this->plugin->info_log('AJAX摘要生成成功，长度 = ' . strlen($summary));
                if ($debug_ajax) {
                    $this->plugin->debug_log('AJAX摘要生成 - 摘要内容预览: ' . substr($summary, 0, 100) . '...');
                }
                wp_send_json_success(array('summary' => $summary));
            } else {
                $this->plugin->error_log('AJAX摘要生成失败 - API返回空结果');
                wp_send_json_error('生成摘要失败，请检查API配置');
            }
        } catch (Exception $e) {
            $this->plugin->error_log('AJAX摘要生成异常 - ' . $e->getMessage());
            wp_send_json_error('系统错误：' . $e->getMessage());
        }
    }
    
    public function ajax_generate_seo() {
        // 记录开始日志
        $this->plugin->info_log('开始生成SEO内容');
        
        // 检查是否启用AJAX调试
        $debug_ajax = get_option('deepseek_ai_debug_ajax', false);
        
        try {
            check_ajax_referer('deepseek_ai_nonce', 'nonce');
            if ($debug_ajax) {
                $this->plugin->debug_log('AJAX SEO生成 - Nonce验证通过');
            }
            
            $post_id = intval($_POST['post_id']);
            if ($debug_ajax) {
                $this->plugin->debug_log('AJAX SEO生成 - 文章ID = ' . $post_id);
            }
            
            $post = get_post($post_id);
            
            if (!$post) {
                $this->plugin->error_log('AJAX SEO生成失败 - 文章不存在，ID = ' . $post_id);
                wp_send_json_error('文章不存在');
                return;
            }
            
            $content = wp_strip_all_tags($post->post_content);
            $title = $post->post_title;
            
            if ($debug_ajax) {
                $this->plugin->debug_log('AJAX SEO生成 - 文章标题 = ' . $title);
                $this->plugin->debug_log('AJAX SEO生成 - 文章内容长度 = ' . strlen($content));
            }
            
            if (empty($content)) {
                $this->plugin->warning_log('AJAX SEO生成失败 - 文章内容为空');
                wp_send_json_error('文章内容为空，无法生成SEO内容');
                return;
            }
            
            if (empty($title)) {
                $this->plugin->warning_log('AJAX SEO生成失败 - 文章标题为空');
                wp_send_json_error('文章标题为空，无法生成SEO内容');
                return;
            }
            
            $seo_data = $this->plugin->get_api()->generate_ai_content($content, 'seo', $title);
            
            if ($seo_data) {
                // 使用update_post_meta确保只保存一次
                update_post_meta($post_id, '_deepseek_ai_seo_title', $seo_data['title']);
                update_post_meta($post_id, '_deepseek_ai_seo_description', $seo_data['description']);
                update_post_meta($post_id, '_deepseek_ai_seo_keywords', $seo_data['keywords']);
                
                $this->plugin->info_log('AJAX SEO内容生成成功');
                if ($debug_ajax) {
                    $this->plugin->debug_log('AJAX SEO生成 - 标题: ' . $seo_data['title']);
                    $this->plugin->debug_log('AJAX SEO生成 - 描述: ' . substr($seo_data['description'], 0, 100) . '...');
                    $this->plugin->debug_log('AJAX SEO生成 - 关键词: ' . $seo_data['keywords']);
                }
                wp_send_json_success($seo_data);
            } else {
                $this->plugin->error_log('AJAX SEO生成失败 - API返回空结果');
                wp_send_json_error('生成SEO内容失败，请检查API配置');
            }
        } catch (Exception $e) {
            $this->plugin->error_log('AJAX SEO生成异常 - ' . $e->getMessage());
            wp_send_json_error('系统错误：' . $e->getMessage());
        }
    }
}

} // 结束 class_exists 检查