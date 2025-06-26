<?php
/**
 * API调用功能类
 *
 * @package DeepSeekAISummarizer
 * @since 3.5.2
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 防止重复声明类
if (!class_exists('DeepSeekAI_API')) {

class DeepSeekAI_API {
    
    private $plugin;
    
    public function __construct($plugin) {
        $this->plugin = $plugin;
    }
    
    public function generate_ai_content($content, $type, $title = '') {
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->info_log('开始调用API，类型 = ' . $type);
        }
        
        // 获取AI服务提供商设置
        $ai_provider = get_option('deepseek_ai_provider', 'deepseek');
        
        if ($ai_provider === 'zhipu') {
            return $this->generate_zhipu_content($content, $type, $title);
        } else {
            return $this->generate_deepseek_content($content, $type, $title);
        }
    }
    
    private function generate_deepseek_content($content, $type, $title = '') {
        $api_key = get_option('deepseek_ai_api_key', '');
        
        if (empty($api_key)) {
            $this->plugin->error_log('DeepSeek API Key未配置');
            return false;
        }
        
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->debug_log('DeepSeek API Key已配置，长度 = ' . strlen($api_key));
            $this->plugin->debug_log('DeepSeek API Key前10位 = ' . substr($api_key, 0, 10) . '...');
        }
        
        $model = get_option('deepseek_ai_model', 'deepseek-chat');
        $max_tokens = get_option('deepseek_ai_max_tokens', 500);
        $temperature = get_option('deepseek_ai_temperature', 0.7);
        
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->debug_log('DeepSeek 模型 = ' . $model . ', 最大令牌 = ' . $max_tokens . ', 温度 = ' . $temperature);
        }
        
        $prompt = $this->build_prompt($content, $type, $title);
        
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->debug_log('DeepSeek 提示词长度 = ' . strlen($prompt));
        }
        
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
        
        $response = $this->make_deepseek_api_request($data, $api_key);
        
        if (!$response) {
            return false;
        }
        
        return $this->parse_response($response, $type);
    }
    
    private function generate_zhipu_content($content, $type, $title = '') {
        $api_key = get_option('zhipu_ai_api_key', '');
        
        if (empty($api_key)) {
            $this->plugin->error_log('智谱清言 API Key未配置');
            return false;
        }
        
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->debug_log('智谱清言 API Key已配置，长度 = ' . strlen($api_key));
            $this->plugin->debug_log('智谱清言 API Key前10位 = ' . substr($api_key, 0, 10) . '...');
        }
        
        $model = get_option('zhipu_ai_model', 'glm-4-flash');
        $max_tokens = get_option('zhipu_ai_max_tokens', 500);
        $temperature = get_option('zhipu_ai_temperature', 0.75);
        
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->debug_log('智谱清言 模型 = ' . $model . ', 最大令牌 = ' . $max_tokens . ', 温度 = ' . $temperature);
        }
        
        $prompt = $this->build_prompt($content, $type, $title);
        
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->debug_log('智谱清言 提示词长度 = ' . strlen($prompt));
        }
        
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
        
        $response = $this->make_zhipu_api_request($data, $api_key);
        
        if (!$response) {
            return false;
        }
        
        return $this->parse_response($response, $type);
    }
    
    private function build_prompt($content, $type, $title = '') {
        if ($type === 'summary') {
            return "请为以下文章内容生成一个简洁、准确的摘要，控制在150字以内，一定要抓重点：\n\n" . $content;
        } else {
            return "请为以下文章生成SEO优化内容，一定要使其SEO标准，这样才能被搜索引擎收录，增加网站权限：\n标题：" . $title . "\n内容：" . $content . "\n\n请返回JSON格式：{\"title\": \"SEO优化标题\", \"description\": \"SEO描述(150字内)\", \"keywords\": \"关键词1,关键词2,关键词3\"}";
        }
    }
    
    private function make_deepseek_api_request($data, $api_key) {
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->info_log('准备发送DeepSeek API请求到: https://api.deepseek.com/chat/completions');
        }
        
        $response = wp_remote_post('https://api.deepseek.com/chat/completions', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        return $this->handle_api_response($response, 'DeepSeek');
    }
    
    private function make_zhipu_api_request($data, $api_key) {
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->info_log('准备发送智谱清言API请求到: https://open.bigmodel.cn/api/paas/v4/chat/completions');
        }
        
        $response = wp_remote_post('https://open.bigmodel.cn/api/paas/v4/chat/completions', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        return $this->handle_api_response($response, '智谱清言');
    }
    
    private function handle_api_response($response, $provider_name) {
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->plugin->error_log($provider_name . ' API请求错误 - ' . $error_message);
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->debug_log($provider_name . ' API响应状态码 = ' . $response_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->debug_log($provider_name . ' API响应内容长度 = ' . strlen($body));
            $this->plugin->debug_log($provider_name . ' API响应内容前500字符 = ' . substr($body, 0, 500));
        }
        
        if ($response_code !== 200) {
            $this->plugin->error_log($provider_name . ' API响应错误，状态码 = ' . $response_code . ', 内容 = ' . $body);
            return false;
        }
        
        $result = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->plugin->error_log($provider_name . ' JSON解析错误 - ' . json_last_error_msg());
            return false;
        }
        
        if (!isset($result['choices'][0]['message']['content'])) {
            $this->plugin->error_log($provider_name . ' API响应格式错误，缺少content字段');
            if ($this->plugin->get_debug_setting('debug_api')) {
                $this->plugin->debug_log($provider_name . ' 完整响应 = ' . print_r($result, true));
            }
            return false;
        }
        
        return $result['choices'][0]['message']['content'];
    }
    
    private function parse_response($ai_response, $type) {
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->debug_log('AI响应内容长度 = ' . strlen($ai_response));
            $this->plugin->debug_log('AI响应内容 = ' . $ai_response);
        }
        
        if ($type === 'summary') {
            $summary = trim($ai_response);
            if ($this->plugin->get_debug_setting('debug_api')) {
                $this->plugin->info_log('摘要生成完成');
            }
            return $summary;
        } else {
            return $this->parse_seo_response($ai_response);
        }
    }
    
    private function parse_seo_response($ai_response) {
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->debug_log('尝试解析SEO JSON数据');
        }
        
        // 清理AI响应中的Markdown代码块标记
        $ai_response = preg_replace('/^```json\s*|\s*```$/s', '', $ai_response);
        $ai_response = trim($ai_response);
        
        if ($this->plugin->get_debug_setting('debug_api')) {
            $this->plugin->debug_log('清理后的JSON内容 = ' . $ai_response);
        }
        
        $seo_data = json_decode($ai_response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->plugin->error_log('SEO JSON解析错误 - ' . json_last_error_msg());
            if ($this->plugin->get_debug_setting('debug_api')) {
                $this->plugin->debug_log('清理后的原始响应 = ' . $ai_response);
            }
            return false;
        }
        
        if ($seo_data && isset($seo_data['title'])) {
            if ($this->plugin->get_debug_setting('debug_api')) {
                $this->plugin->info_log('SEO内容解析成功');
            }
            return $seo_data;
        } else {
            $this->plugin->error_log('SEO数据格式错误，缺少title字段');
            if ($this->plugin->get_debug_setting('debug_api')) {
                $this->plugin->debug_log('解析结果 = ' . print_r($seo_data, true));
            }
            return false;
        }
    }
}

} // 结束 class_exists 检查