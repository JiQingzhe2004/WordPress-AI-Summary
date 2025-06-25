<?php
/**
 * AJAX处理功能类
 *
 * @package DeepSeekAISummarizer
 * @since 3.5.0
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
        add_action('wp_ajax_save_social_settings', array($this, 'ajax_save_social_settings'));
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
    
    public function ajax_save_social_settings() {
        // 记录开始日志
        $this->plugin->info_log('开始保存社交媒体设置');
        
        // 检查是否启用AJAX调试
        $debug_ajax = get_option('deepseek_ai_debug_ajax', false);
        
        try {
            check_ajax_referer('deepseek_ai_nonce', 'nonce');
            if ($debug_ajax) {
                $this->plugin->debug_log('AJAX社交媒体保存 - Nonce验证通过');
            }
            
            $post_id = intval($_POST['post_id']);
            if ($debug_ajax) {
                $this->plugin->debug_log('AJAX社交媒体保存 - 文章ID = ' . $post_id);
            }
            
            // 验证用户权限
            if (!current_user_can('edit_post', $post_id)) {
                $this->plugin->error_log('AJAX社交媒体保存失败 - 用户无权限编辑文章，ID = ' . $post_id);
                wp_send_json_error('您没有权限编辑此文章');
            }
            
            // 保存社交媒体标题
            if (isset($_POST['social_title'])) {
                $social_title = sanitize_text_field($_POST['social_title']);
                update_post_meta($post_id, '_deepseek_ai_social_title', $social_title);
                if ($debug_ajax) {
                    $this->plugin->debug_log('AJAX社交媒体保存 - 标题: ' . $social_title);
                }
            }
            
            // 保存社交媒体描述
            if (isset($_POST['social_description'])) {
                $social_description = sanitize_textarea_field($_POST['social_description']);
                update_post_meta($post_id, '_deepseek_ai_social_description', $social_description);
                if ($debug_ajax) {
                    $this->plugin->debug_log('AJAX社交媒体保存 - 描述: ' . substr($social_description, 0, 100) . '...');
                }
            }
            
            // 保存通用社交分享图片
            if (isset($_POST['social_image'])) {
                $social_image = esc_url_raw($_POST['social_image']);
                update_post_meta($post_id, '_deepseek_ai_social_image', $social_image);
                if ($debug_ajax) {
                    $this->plugin->debug_log('AJAX社交媒体保存 - 通用图片: ' . $social_image);
                }
            }
            
            // 保存微信分享图片
            if (isset($_POST['wechat_image'])) {
                $wechat_image = esc_url_raw($_POST['wechat_image']);
                update_post_meta($post_id, '_deepseek_ai_wechat_image', $wechat_image);
                if ($debug_ajax) {
                    $this->plugin->debug_log('AJAX社交媒体保存 - 微信图片: ' . $wechat_image);
                }
            }
            
            $this->plugin->info_log('社交媒体设置保存成功，文章ID: ' . $post_id);
            wp_send_json_success('社交媒体设置已保存');
            
        } catch (Exception $e) {
            $this->plugin->error_log('AJAX社交媒体保存异常 - ' . $e->getMessage());
            wp_send_json_error('系统错误：' . $e->getMessage());
        }
    }
}

} // 结束 class_exists 检查