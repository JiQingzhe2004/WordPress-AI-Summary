<?php
/**
 * Plugin Name: 爱奇吉摘要
 * Plugin URI: https://github.com/JiQingzhe2004/WordPress-AI-Summary
 * Description: 使用爱奇吉摘要自动生成文章摘要和SEO优化内容，支持流式输出效果
 * Version: 2.2.0
 * Author: 吉庆喆
 * License: GPL v2 or later
 * Text Domain: deepseek-ai-summarizer
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('DEEPSEEK_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DEEPSEEK_AI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DEEPSEEK_AI_VERSION', '2.1.0');

// 加载主插件类
require_once DEEPSEEK_AI_PLUGIN_PATH . 'includes/class-deepseek-ai-summarizer.php';

// 初始化插件
new DeepSeekAISummarizer();
?>