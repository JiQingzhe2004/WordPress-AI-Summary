<?php
/**
 * AJAX处理功能类
 *
 * @package DeepSeekAISummarizer
 * @since 2.1.0
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

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
        $this->plugin->write_log('开始生成摘要');
        
        try {
            check_ajax_referer('deepseek_ai_nonce', 'nonce');
            $this->plugin->write_log('Nonce验证通过');
            
            $post_id = intval($_POST['post_id']);
            $this->plugin->write_log('文章ID = ' . $post_id);
            
            $post = get_post($post_id);
            
            if (!$post) {
                $this->plugin->write_log('文章不存在，ID = ' . $post_id);
                wp_send_json_error('文章不存在');
                return;
            }
            
            $content = wp_strip_all_tags($post->post_content);
            $this->plugin->write_log('文章内容长度 = ' . strlen($content));
            
            if (empty($content)) {
                $this->plugin->write_log('文章内容为空');
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
                
                $this->plugin->write_log('摘要生成成功，长度 = ' . strlen($summary));
                wp_send_json_success(array('summary' => $summary));
            } else {
                $this->plugin->write_log('摘要生成失败');
                wp_send_json_error('生成摘要失败，请检查API配置');
            }
        } catch (Exception $e) {
            $this->plugin->write_log('异常错误 - ' . $e->getMessage());
            wp_send_json_error('系统错误：' . $e->getMessage());
        }
    }
    
    public function ajax_generate_seo() {
        // 记录开始日志
        $this->plugin->write_log('开始生成SEO内容');
        
        try {
            check_ajax_referer('deepseek_ai_nonce', 'nonce');
            $this->plugin->write_log('Nonce验证通过');
            
            $post_id = intval($_POST['post_id']);
            $this->plugin->write_log('文章ID = ' . $post_id);
            
            $post = get_post($post_id);
            
            if (!$post) {
                $this->plugin->write_log('文章不存在，ID = ' . $post_id);
                wp_send_json_error('文章不存在');
                return;
            }
            
            $content = wp_strip_all_tags($post->post_content);
            $title = $post->post_title;
            
            $this->plugin->write_log('文章标题 = ' . $title);
            $this->plugin->write_log('文章内容长度 = ' . strlen($content));
            
            if (empty($content)) {
                $this->plugin->write_log('文章内容为空');
                wp_send_json_error('文章内容为空，无法生成SEO内容');
                return;
            }
            
            if (empty($title)) {
                $this->plugin->write_log('文章标题为空');
                wp_send_json_error('文章标题为空，无法生成SEO内容');
                return;
            }
            
            $seo_data = $this->plugin->get_api()->generate_ai_content($content, 'seo', $title);
            
            if ($seo_data) {
                // 使用update_post_meta确保只保存一次
                update_post_meta($post_id, '_deepseek_ai_seo_title', $seo_data['title']);
                update_post_meta($post_id, '_deepseek_ai_seo_description', $seo_data['description']);
                update_post_meta($post_id, '_deepseek_ai_seo_keywords', $seo_data['keywords']);
                
                $this->plugin->write_log('SEO内容生成成功');
                wp_send_json_success($seo_data);
            } else {
                $this->plugin->write_log('SEO内容生成失败');
                wp_send_json_error('生成SEO内容失败，请检查API配置');
            }
        } catch (Exception $e) {
            $this->plugin->write_log('异常错误 - ' . $e->getMessage());
            wp_send_json_error('系统错误：' . $e->getMessage());
        }
    }
}