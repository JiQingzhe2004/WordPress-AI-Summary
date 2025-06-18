jQuery(document).ready(function($) {
    'use strict';
    
    // 调试功能
    const DEBUG = {
        enabled: typeof deepseek_ai_ajax !== 'undefined' && deepseek_ai_ajax.debug_enabled,
        frontend: typeof deepseek_ai_ajax !== 'undefined' && deepseek_ai_ajax.debug_frontend,
        log: function(message, level = 'info') {
            if (!this.enabled || !this.frontend) return;
            
            const timestamp = new Date().toLocaleTimeString();
            const prefix = `[DeepSeek AI ${timestamp}]`;
            
            switch(level) {
                case 'error':
                    console.error(prefix, message);
                    break;
                case 'warn':
                    console.warn(prefix, message);
                    break;
                case 'info':
                    console.info(prefix, message);
                    break;
                default:
                    console.log(prefix, message);
            }
        }
    };
    
    // 立即输出调试信息
    DEBUG.log('DeepSeek AI 脚本已加载');
    
    // 检查必要的变量是否存在
    if (typeof deepseek_ai_ajax === 'undefined') {
        console.error('deepseek_ai_ajax 未定义，请检查脚本加载');
        // 不要直接return，继续执行基本功能
        console.log('继续执行基本脚本功能...');
    } else {
        DEBUG.log('deepseek_ai_ajax 变量已加载: ' + JSON.stringify({
            ajax_url: deepseek_ai_ajax.ajax_url,
            version: deepseek_ai_ajax.version,
            debug_enabled: deepseek_ai_ajax.debug_enabled,
            debug_frontend: deepseek_ai_ajax.debug_frontend
        }));
    }
    
    // 美化控制台输出
    function logPluginInfo() {
        // 只在调试模式下显示详细信息
        if (!DEBUG.enabled || !DEBUG.frontend) {
            DEBUG.log('插件信息输出已禁用（调试模式关闭）');
            return;
        }
        
        const styles = {
            title: 'font-size: 20px; font-weight: bold; color: #2271b1; padding: 10px 0;',
            subtitle: 'font-size: 14px; color: #666; margin: 5px 0;',
            highlight: 'color: #2271b1; font-weight: bold;',
            success: 'color: #4CAF50;',
            info: 'color: #2196F3;',
            warning: 'color: #FFC107;',
            error: 'color: #F44336;'
        };

        // 获取插件信息
        const pluginName = deepseek_ai_ajax.plugin_name || 'DeepSeek AI 文章摘要生成器';
        const pluginVersion = deepseek_ai_ajax.version || '未知版本';

        // 判断是否在文章编辑页面
        const isEditPage = typeof window.pagenow !== 'undefined' && window.pagenow === 'post';

        if (isEditPage) {
            // 编辑页面显示详细信息
            console.log(
                '%c' + pluginName + '\n' +
                '%c版本: ' + pluginVersion + '\n' +
                '%c作者: 吉庆喆\n' +
                '%c状态: 运行中（调试模式）\n' +
                '%c功能: 自动生成文章摘要和SEO优化内容\n' +
                '%c提示: 按 Ctrl+Shift+S 快速生成摘要\n' +
                '%c提示: 按 Ctrl+Shift+E 快速生成SEO内容',
                styles.title,
                styles.subtitle,
                styles.subtitle,
                styles.success,
                styles.info,
                styles.warning,
                styles.warning
            );

            // 添加分隔线
            console.log(
                '%c' + '='.repeat(50),
                'color: #2271b1; font-weight: bold;'
            );

            // 添加功能状态检查
            const features = {
                'API连接': typeof deepseek_ai_ajax !== 'undefined',
                '打字机效果': true,
                'SEO优化': true,
                '自动保存': true,
                '调试模式': DEBUG.enabled,
                '前端调试': DEBUG.frontend
            };

            console.log('%c功能状态检查:', styles.subtitle);
            Object.entries(features).forEach(([feature, status]) => {
                console.log(
                    `%c${feature}: %c${status ? '✓' : '✗'}`,
                    styles.info,
                    status ? styles.success : styles.error
                );
            });

            // 添加使用提示
            console.log(
                '%c使用提示:\n' +
                '%c1. 在文章编辑页面可以生成摘要和SEO内容\n' +
                '%c2. 摘要会自动同步到WordPress原生摘要字段\n' +
                '%c3. SEO内容会自动添加到文章头部\n' +
                '%c4. 所有内容都支持打字机效果展示',
                styles.subtitle,
                styles.info,
                styles.info,
                styles.info,
                styles.info
            );
        } else {
            // 文章展示页面只显示简单信息
            console.log(
                '%c' + pluginName + '\n' +
                '%c版本: ' + pluginVersion + '\n' +
                '%c状态: 运行中（调试模式）',
                styles.title,
                styles.subtitle,
                styles.success
            );
        }
    }

    // 在页面加载完成后显示插件信息
    logPluginInfo();
    
    // 获取当前文章ID
    function getCurrentPostId() {
        if (typeof window.pagenow !== 'undefined' && window.pagenow === 'post') {
            const postId = $('#post_ID').val();
            DEBUG.log('获取当前文章ID: ' + postId);
            return postId;
        }
        DEBUG.log('不在文章编辑页面，无法获取文章ID');
        return null;
    }
    
    // 显示成功消息
    function showSuccess(message, container) {
        DEBUG.log('显示成功消息: ' + message);
        const successDiv = $('<div class="deepseek-ai-success deepseek-ai-fade-in"><i class="dashicons dashicons-yes"></i>' + message + '</div>');
        container.prepend(successDiv);
        setTimeout(() => {
            successDiv.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // 显示错误消息
    function showError(message, container) {
        DEBUG.log('显示错误消息: ' + message, 'error');
        const errorDiv = $('<div class="deepseek-ai-error deepseek-ai-fade-in"><i class="dashicons dashicons-no"></i>' + message + '</div>');
        container.prepend(errorDiv);
        setTimeout(() => {
            errorDiv.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // 流式输出效果
    function typeWriter(element, text, speed = 30) {
        DEBUG.log('开始打字机效果，文本长度: ' + text.length + ', 速度: ' + speed);
        
        const container = element.closest('.deepseek-ai-summary-container');
        const header = container.find('.deepseek-ai-summary-header');
        
        // 添加加载状态
        container.addClass('loading');
        header.addClass('loading');
        element.addClass('typing');
        
        // 保存原始文本
        const originalText = text;
        element.text('');
        
        let i = 0;
        const timer = setInterval(() => {
            if (i < originalText.length) {
                element.text(element.text() + originalText.charAt(i));
                i++;
            } else {
                clearInterval(timer);
                // 移除加载状态
                container.removeClass('loading');
                header.removeClass('loading');
                element.removeClass('typing');
            }
        }, speed);
    }
    
    // 将typeWriter函数暴露到全局作用域
    window.typeWriter = typeWriter;
    
    // HTML解码函数
    function decodeHtmlEntities(text) {
        if (!text) return text;
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        return textarea.value;
    }
    
    // 前端摘要显示的流式效果
    function initSummaryTypewriter() {
        DEBUG.log('初始化摘要打字机效果');
        
        $('.deepseek-ai-summary-content').each(function() {
            const element = $(this);
            // 优先从data属性中获取原始文本并解码HTML实体
            let originalText = element.attr('data-original-text');
            if (originalText) {
                originalText = decodeHtmlEntities(originalText);
            }
            
            DEBUG.log('检查摘要元素，原始文本长度: ' + (originalText ? originalText.length : 0) + ', 已处理: ' + element.hasClass('typewriter-processed'));
            
            // 检查是否已经处理过打字机效果，并且有原始文本
            if (originalText && originalText.trim() && !element.hasClass('typewriter-processed')) {
                DEBUG.log('开始处理摘要打字机效果');
                // 标记为已处理
                element.addClass('typewriter-processed');
                // 先清空内容
                element.text('');
                // 延迟一下再开始打字效果
                setTimeout(() => {
                    typeWriter(element, originalText, 20);
                }, 500);
            } else if (!originalText || !originalText.trim()) {
                DEBUG.log('摘要元素没有原始文本，跳过打字机效果', 'warn');
                // 处理没有原始文本的情况，直接显示内容
                if (element.text().trim() === '' && originalText) {
                    element.text(originalText);
                }
            } else if (element.hasClass('typewriter-processed') && element.text().trim() === '') {
                // 修复已处理但内容为空的情况
                DEBUG.log('修复已处理但内容为空的摘要元素');
                element.text(originalText);
            }
        });
    }
    
    // 在页面加载完成后初始化打字机效果
    $(document).ready(function() {
        console.log('页面加载完成，开始初始化脚本');
        
        // 确保页面完全加载后再初始化打字机效果
        setTimeout(function() {
            DEBUG.log('延迟初始化打字机效果');
            initSummaryTypewriter();
        }, 1000);
        
        // 显示插件信息
        logPluginInfo();
    });
    
    // 监听动态加载的内容（仅在前端页面）
    $(document).on('ajaxComplete', function() {
        DEBUG.log('AJAX完成，重新初始化前端打字机效果');
        setTimeout(function() {
            initSummaryTypewriter();
        }, 500);
    });
    
    // 生成摘要
    $('#generate-summary').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const loadingDiv = $('#summary-loading');
        const summaryTextarea = $('#deepseek-ai-summary');
        const postId = getCurrentPostId();
        const container = $('.deepseek-ai-section').first();
        
        if (!postId) {
            showError('无法获取文章ID，请先保存文章', container);
            return;
        }
        
        // 获取文章内容 - 兼容多种编辑器
        let content = '';
        
        // 尝试从不同的编辑器获取内容
        if ($('#content').length) {
            content = $('#content').val(); // 经典编辑器文本模式
        }
        
        // TinyMCE 可视化编辑器
        if ((!content || content.trim().length < 10) && typeof tinymce !== 'undefined' && tinymce.get('content')) {
            content = tinymce.get('content').getContent();
        }
        
        // Gutenberg 块编辑器
        if ((!content || content.trim().length < 10) && typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            content = wp.data.select('core/editor').getEditedPostContent();
        }
        
        // 显示加载状态
        button.prop('disabled', true).text('生成中...');
        loadingDiv.show();
        
        // AJAX请求
        $.ajax({
            url: deepseek_ai_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_summary',
                post_id: postId,
                nonce: deepseek_ai_ajax.nonce
            },
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    // 使用打字机效果显示摘要
                    typeWriter(summaryTextarea, response.data.summary);
                    showSuccess('摘要生成成功！', container);
                } else {
                    showError(response.data || '生成摘要失败', container);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = '生成摘要失败';
                if (status === 'timeout') {
                    errorMessage = '请求超时，请检查网络连接';
                } else if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                
                showError(errorMessage, container);
            },
            complete: function() {
                button.prop('disabled', false).text('生成摘要');
                loadingDiv.hide();
            }
        });
    });
    
    // 生成SEO内容
    $('#generate-seo').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const loadingDiv = $('#seo-loading');
        const seoTitle = $('#deepseek-ai-seo-title');
        const seoDescription = $('#deepseek-ai-seo-description');
        const seoKeywords = $('#deepseek-ai-seo-keywords');
        const postId = getCurrentPostId();
        const container = $('.deepseek-ai-section').last();
        
        if (!postId) {
            showError('无法获取文章ID，请先保存文章', container);
            return;
        }
        
        // 获取文章内容 - 兼容多种编辑器
        let content = '';
        
        // 尝试从不同的编辑器获取内容
        if ($('#content').length) {
            content = $('#content').val(); // 经典编辑器文本模式
        }
        
        // TinyMCE 可视化编辑器
        if ((!content || content.trim().length < 10) && typeof tinymce !== 'undefined' && tinymce.get('content')) {
            content = tinymce.get('content').getContent();
        }
        
        // Gutenberg 块编辑器
        if ((!content || content.trim().length < 10) && typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            content = wp.data.select('core/editor').getEditedPostContent();
        }
        
        // 获取文章标题
        let title = $('#title').val();
        if ((!title || title.trim().length < 5) && typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            title = wp.data.select('core/editor').getEditedPostAttribute('title');
        }
        
        // 显示加载状态
        button.prop('disabled', true).text('生成中...');
        loadingDiv.show();
        
        // 添加进度条
        const progressBar = $('<div class="deepseek-ai-progress"><div class="deepseek-ai-progress-bar" style="width: 0%;"></div></div>');
        loadingDiv.after(progressBar);
        
        // 模拟进度
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 12;
            if (progress > 85) progress = 85;
            progressBar.find('.deepseek-ai-progress-bar').css('width', progress + '%');
        }, 250);
        
        // AJAX请求
        $.ajax({
            url: deepseek_ai_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_seo',
                post_id: postId,
                nonce: deepseek_ai_ajax.nonce
            },
            timeout: 30000,
            success: function(response) {
                clearInterval(progressInterval);
                progressBar.find('.deepseek-ai-progress-bar').css('width', '100%');
                
                setTimeout(() => {
                    progressBar.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 500);
                
                if (response.success) {
                    const data = response.data;
                    
                    // 使用打字机效果显示SEO内容
                    setTimeout(() => {
                        typeWriter(seoTitle, data.title, 50);
                    }, 200);
                    
                    setTimeout(() => {
                        typeWriter(seoDescription, data.description, 40);
                    }, 800);
                    
                    setTimeout(() => {
                        typeWriter(seoKeywords, data.keywords, 60);
                    }, 1400);
                    
                    showSuccess('SEO内容生成成功！', container);
                } else {
                    showError(response.data || '生成SEO内容失败', container);
                }
            },
            error: function(xhr, status, error) {
                clearInterval(progressInterval);
                progressBar.remove();
                
                let errorMessage = '生成SEO内容失败';
                if (status === 'timeout') {
                    errorMessage = '请求超时，请检查网络连接';
                } else if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                
                showError(errorMessage, container);
            },
            complete: function() {
                button.prop('disabled', false).text('生成SEO内容');
                loadingDiv.hide();
            }
        });
    });
    
    // 添加工具提示
    function addTooltips() {
        $('[data-tooltip]').each(function() {
            $(this).addClass('deepseek-ai-tooltip');
        });
    }
    
    // 初始化工具提示
    addTooltips();
    
    // 自动保存功能
    let autoSaveTimeout;
    function autoSave() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(() => {
            if (typeof autosave !== 'undefined') {
                autosave();
            }
        }, 2000);
    }
    
    // 监听内容变化
    $('#deepseek-ai-summary, #deepseek-ai-seo-title, #deepseek-ai-seo-description, #deepseek-ai-seo-keywords').on('input', autoSave);
    
    // 添加快捷键支持
    $(document).on('keydown', function(e) {
        // 只在文章编辑页面启用快捷键
        if (typeof window.pagenow === 'undefined' || window.pagenow !== 'post') {
            return;
        }

        // Ctrl+Shift+S 生成摘要
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 's') {
            e.preventDefault();
            console.log('%c触发快捷键: 生成摘要', 'color: #2271b1; font-weight: bold;');
            $('#generate-summary').click();
        }
        
        // Ctrl+Shift+E 生成SEO
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'e') {
            e.preventDefault();
            console.log('%c触发快捷键: 生成SEO内容', 'color: #2271b1; font-weight: bold;');
            $('#generate-seo').click();
        }
    });
    
    // 添加快捷键提示到按钮
    $('#generate-summary').attr('title', '快捷键: Ctrl+Shift+S');
    $('#generate-seo').attr('title', '快捷键: Ctrl+Shift+E');
    
    // 添加字数统计
    function addCharacterCount() {
        $('#deepseek-ai-summary').after('<div class="character-count" style="text-align: right; color: #666; font-size: 12px; margin-top: 5px;">字数: <span id="summary-count">0</span>/150</div>');
        $('#deepseek-ai-seo-description').after('<div class="character-count" style="text-align: right; color: #666; font-size: 12px; margin-top: 5px;">字数: <span id="description-count">0</span>/150</div>');
        
        $('#deepseek-ai-summary').on('input', function() {
            const count = $(this).val().length;
            $('#summary-count').text(count);
            if (count > 150) {
                $('#summary-count').css('color', '#f44336');
            } else {
                $('#summary-count').css('color', '#666');
            }
        });
        
        $('#deepseek-ai-seo-description').on('input', function() {
            const count = $(this).val().length;
            $('#description-count').text(count);
            if (count > 150) {
                $('#description-count').css('color', '#f44336');
            } else {
                $('#description-count').css('color', '#666');
            }
        });
    }
    
    // 初始化字数统计
    addCharacterCount();
    
    // 添加复制功能
    function addCopyButtons() {
        const copyButton = '<button type="button" class="deepseek-ai-btn copy copy-btn" style="margin-left: 10px;">复制</button>';
        
        $('.deepseek-ai-controls').each(function() {
            $(this).append(copyButton);
        });
        
        $('.copy-btn').on('click', function(e) {
            e.preventDefault();
            
            const section = $(this).closest('.deepseek-ai-section');
            let textToCopy = '';
            
            if (section.find('#deepseek-ai-summary').length) {
                textToCopy = section.find('#deepseek-ai-summary').val();
            } else {
                const title = section.find('#deepseek-ai-seo-title').val();
                const description = section.find('#deepseek-ai-seo-description').val();
                const keywords = section.find('#deepseek-ai-seo-keywords').val();
                textToCopy = `标题: ${title}\n描述: ${description}\n关键词: ${keywords}`;
            }
            
            if (textToCopy) {
                // 创建一个临时的textarea元素
                const textArea = document.createElement('textarea');
                textArea.value = textToCopy;
                
                // 设置样式使其不可见
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                
                // 选择文本
                textArea.focus();
                textArea.select();
                
                try {
                    // 尝试使用现代API
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(textToCopy).then(() => {
                            showCopySuccess(this);
                        }).catch(() => {
                            // 如果现代API失败，使用传统方法
                            document.execCommand('copy');
                            showCopySuccess(this);
                        });
                    } else {
                        // 使用传统方法
                        document.execCommand('copy');
                        showCopySuccess(this);
                    }
                } catch (err) {
                    console.error('复制失败:', err);
                    showError('复制失败，请手动复制', section);
                }
                
                // 移除临时元素
                document.body.removeChild(textArea);
            }
        });
    }
    
    // 显示复制成功状态
    function showCopySuccess(button) {
        const $button = $(button);
        const originalText = $button.text();
        $button.text('已复制').prop('disabled', true);
        setTimeout(() => {
            $button.text(originalText).prop('disabled', false);
        }, 2000);
    }
    
    // 初始化复制按钮
    addCopyButtons();
    
    // 添加动画效果
    function animateElements() {
        $('.deepseek-ai-meta-box').addClass('deepseek-ai-fade-in');
        $('.deepseek-ai-section').each(function(index) {
            $(this).css('animation-delay', (index * 0.1) + 's').addClass('deepseek-ai-slide-up');
        });
    }
    
    // 初始化动画
    setTimeout(animateElements, 100);
    
    // 设置页面的表单验证
    if ($('.deepseek-ai-settings form').length) {
        $('.deepseek-ai-settings form').on('submit', function(e) {
            const apiKey = $('input[name="api_key"]').val();
            
            if (!apiKey || apiKey.trim().length < 10) {
                e.preventDefault();
                showError('请输入有效的API Key', $('.deepseek-ai-settings'));
                return false;
            }
            
            // 显示保存中状态
            const submitBtn = $(this).find('input[type="submit"]');
            submitBtn.val('保存中...').prop('disabled', true);
        });
    }
    
    // 添加API Key显示/隐藏切换
    if ($('input[name="api_key"]').length) {
        const apiKeyInput = $('input[name="api_key"]');
        const toggleBtn = $('<button type="button" class="button" style="margin-left: 10px;">显示</button>');
        
        apiKeyInput.after(toggleBtn);
        
        toggleBtn.on('click', function() {
            if (apiKeyInput.attr('type') === 'password') {
                apiKeyInput.attr('type', 'text');
                $(this).text('隐藏');
            } else {
                apiKeyInput.attr('type', 'password');
                $(this).text('显示');
            }
        });
    }
    
    // 错误处理和重试机制
    window.deepseekAIRetry = function(action, maxRetries = 3) {
        let retries = 0;
        
        function attempt() {
            if (action === 'summary') {
                $('#generate-summary').click();
            } else if (action === 'seo') {
                $('#generate-seo').click();
            }
        }
        
        // 监听AJAX错误
        $(document).ajaxError(function(event, xhr, settings) {
            if (settings.data && settings.data.includes(action) && retries < maxRetries) {
                retries++;
                setTimeout(attempt, 2000 * retries); // 递增延迟
            }
        });
        
        attempt();
    };
    
    // 在文章页面中自动检测并显示摘要
    $(document).ready(function() {
        // 立即执行一次
        processArticleSummary();
        
        // 延迟执行以确保DOM完全加载和动态内容渲染
        setTimeout(processArticleSummary, 500);
        setTimeout(processArticleSummary, 1500);
        
        // 对于WordPress中常见的AJAX导航或Pjax加载
        $(document).on('ajaxComplete ready post-load page-load', processArticleSummary);
        
        // 监听自定义摘要加载事件
        $(document).on('deepseekAiSummaryLoaded', function() {
            DEBUG.log('收到摘要加载事件通知，处理摘要');
            processArticleSummary();
        });
    });
    
    // 处理页面加载和导航事件
    $(window).on('load pageshow', function() {
        DEBUG.log('页面加载完成或从缓存恢复，检查摘要显示');
        processArticleSummary();
        setTimeout(processArticleSummary, 1000);
    });
    
    // MutationObserver监听DOM变化，处理AJAX加载的内容
    if (window.MutationObserver) {
        var observer = new MutationObserver(function(mutations) {
            // 只有在变化比较多的情况下才处理，避免频繁执行
            if (mutations.length > 2) {
                DEBUG.log('检测到DOM变化，尝试处理摘要显示');
                processArticleSummary();
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // 处理文章摘要显示的核心函数
    function processArticleSummary() {
        // 仅在有摘要容器时执行
        const containers = $('.deepseek-ai-summary-container');
        if (containers.length) {
            DEBUG.log('检测到文章页面的摘要容器，总数: ' + containers.length);
            
            // 查找所有摘要内容元素
            containers.each(function() {
                const container = $(this);
                const element = container.find('.deepseek-ai-summary-content');
                
                if (element.length === 0) {
                    DEBUG.log('未找到摘要内容元素', 'warn');
                    return; // 跳过这个容器
                }
                
                // 检查元素是否已经有内容或正在处理中
                if (element.text().trim() !== '' || element.hasClass('processing')) {
                    DEBUG.log('摘要内容已有或正在处理中，跳过');
                    return; // 已有内容或正在处理，无需处理
                }
                
                // 获取原始文本，优先从自身data属性获取
                let originalText = element.attr('data-original-text');
                
                // 如果没有找到，尝试从父容器获取
                if (!originalText || !originalText.trim()) {
                    originalText = container.attr('data-summary');
                }
                
                // 解码HTML实体
                if (originalText) {
                    originalText = decodeHtmlEntities(originalText);
                }
                
                // 如果找到了文本，显示它
                if (originalText && originalText.trim()) {
                    DEBUG.log('找到摘要文本，长度: ' + originalText.length);
                    
                    // 标记为正在处理，避免重复处理
                    element.addClass('processing');
                    
                    // 尝试使用打字机效果
                    if (typeof window.typeWriter === 'function') {
                        try {
                            DEBUG.log('使用打字机效果显示摘要');
                            typeWriter(element, originalText, 20);
                        } catch (e) {
                            // 打字机效果失败，直接显示文本
                            DEBUG.log('打字机效果出错: ' + e.message, 'error');
                            element.text(originalText).removeClass('processing').addClass('processed-error');
                        }
                    } else {
                        // 打字机函数不可用，使用简单的JS实现
                        DEBUG.log('打字机函数不可用，使用简易打字机效果');
                        element.text(''); // 清空内容
                        
                        let i = 0;
                        const text = originalText;
                        const speed = 20;
                        
                        const timer = setInterval(function() {
                            if (i < text.length) {
                                element.text(element.text() + text.charAt(i));
                                i++;
                            } else {
                                clearInterval(timer);
                                element.removeClass('processing').addClass('processed');
                            }
                        }, speed);
                    }
                } else {
                    DEBUG.log('未找到摘要文本', 'warn');
                }
            });
        } else {
            DEBUG.log('页面中没有摘要容器');
        }
    }
    
    // 滚动事件处理：当用户滚动到摘要位置时，确保摘要已显示
    $(window).on('scroll', function() {
        $('.deepseek-ai-summary-content').each(function() {
            const element = $(this);
            if (element.text().trim() === '' && isElementInViewport(element)) {
                let originalText = element.attr('data-original-text') || 
                                   element.closest('.deepseek-ai-summary-container').attr('data-summary');
                if (originalText) {
                    originalText = decodeHtmlEntities(originalText);
                    DEBUG.log('用户滚动到摘要位置，显示摘要');
                    element.text(originalText);
                }
            }
        });
    });
    
    // 检查元素是否在视口中
    function isElementInViewport(el) {
        const rect = el[0].getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
    
    console.log('DeepSeek AI Summarizer 插件已加载完成');
});