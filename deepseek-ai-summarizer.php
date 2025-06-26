<?php
/**
 * Plugin Name: AiqijiSummary
 * Text Domain: 爱奇吉智能摘要
 * Plugin URI: https://github.com/JiQingzhe2004/WordPress-AI-Summary
 * Description: 使用爱奇吉摘要自动生成文章摘要和SEO优化内容，支持流式输出效果。
 * - 自动生成文章摘要
 * - 支持流式打字机效果
 * - SEO优化建议
 * - 自定义摘要长度
 * - 响应式设计
 * Version: 3.5.1
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: 吉庆喆
 * Author URI: https://github.com/JiQingzhe2004
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * @package DeepSeekAISummarizer
 * @since 3.5.1
 * 
 * 爱奇吉摘要 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 * 
 * 爱奇吉摘要 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with 爱奇吉摘要. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量（避免重复定义）
if (!defined('DEEPSEEK_AI_PLUGIN_URL')) {
    define('DEEPSEEK_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('DEEPSEEK_AI_PLUGIN_PATH')) {
    define('DEEPSEEK_AI_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('DEEPSEEK_AI_VERSION')) {
    define('DEEPSEEK_AI_VERSION', '3.5.1');
}

// 加载主插件类
require_once DEEPSEEK_AI_PLUGIN_PATH . 'includes/class-deepseek-ai-summarizer.php';

// 初始化插件
new DeepSeekAISummarizer();
?>