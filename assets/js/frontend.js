/**
 * DeepSeek AI Summarizer Frontend JavaScript
 * 处理摘要显示和打字机效果
 */

(function($) {
    'use strict';

    // 全局配置
    const DeepSeekAI = {
        config: {
            typewriterSpeed: 20,
            debugMode: false,
            selectors: {
                container: '.deepseek-ai-summary-container',
                content: '.deepseek-ai-summary-content',
                header: '.deepseek-ai-summary-header'
            }
        },
        
        // 初始化
        init: function() {
            this.bindEvents();
            this.initTypewriter();
            this.log('DeepSeek AI Summarizer 已初始化');
        },
        
        // 绑定事件
        bindEvents: function() {
            $(document).ready(() => {
                this.initTypewriter();
            });
            
            // 监听动态内容加载
            if (window.MutationObserver) {
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.addedNodes.length) {
                            this.initTypewriter();
                        }
                    });
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            }
        },
        
        // 初始化打字机效果
        initTypewriter: function() {
            const containers = document.querySelectorAll(this.config.selectors.container);
            
            containers.forEach((container) => {
                if (container.dataset.typewriterInitialized) {
                    return; // 避免重复初始化
                }
                
                const contentElement = container.querySelector(this.config.selectors.content);
                if (!contentElement) {
                    return;
                }
                
                const originalText = contentElement.dataset.originalText;
                const speed = parseInt(contentElement.dataset.typewriterSpeed) || this.config.typewriterSpeed;
                
                if (originalText) {
                    container.dataset.typewriterInitialized = 'true';
                    this.startTypewriter(contentElement, originalText, speed);
                }
            });
        },
        
        // 开始打字机效果
        startTypewriter: function(element, text, speed) {
            if (!element || !text) {
                return;
            }
            
            // 清空内容
            element.innerHTML = '';
            element.classList.remove('typing-complete');
            
            let index = 0;
            const typeInterval = setInterval(() => {
                if (index < text.length) {
                    element.innerHTML += text.charAt(index);
                    index++;
                } else {
                    clearInterval(typeInterval);
                    element.classList.add('typing-complete');
                    this.log('打字机效果完成');
                    
                    // 触发完成事件
                    this.triggerEvent(element, 'typewriter-complete');
                }
            }, speed);
            
            // 存储interval ID以便可能的清理
            element.dataset.typewriterInterval = typeInterval;
        },
        
        // 停止打字机效果
        stopTypewriter: function(element) {
            const intervalId = element.dataset.typewriterInterval;
            if (intervalId) {
                clearInterval(parseInt(intervalId));
                delete element.dataset.typewriterInterval;
            }
        },
        
        // 强制显示摘要（备用机制）
        forceDisplaySummary: function(summaryHtml, delay = 1000) {
            setTimeout(() => {
                try {
                    // 检查是否已存在摘要容器
                    if (document.querySelector(this.config.selectors.container)) {
                        this.log('摘要容器已存在，跳过强制显示');
                        return;
                    }
                    
                    this.log('执行强制显示摘要备用机制');
                    
                    // 尝试多个选择器找到合适的插入位置
                    const selectors = [
                        '.entry-content',
                        '.post-content',
                        '.content',
                        'article .content',
                        '.single-content',
                        'main article',
                        '.post',
                        'article',
                        'main'
                    ];
                    
                    let contentElement = null;
                    for (const selector of selectors) {
                        contentElement = document.querySelector(selector);
                        if (contentElement) {
                            break;
                        }
                    }
                    
                    if (contentElement) {
                        contentElement.insertAdjacentHTML('beforebegin', summaryHtml);
                        this.log('摘要已通过备用机制插入');
                        
                        // 重新初始化打字机效果
                        this.initTypewriter();
                    } else {
                        this.warn('未找到合适的内容容器');
                    }
                    
                } catch (error) {
                    this.error('强制显示摘要时发生错误:', error);
                }
            }, delay);
        },
        
        // 触发自定义事件
        triggerEvent: function(element, eventName, data = {}) {
            const event = new CustomEvent(eventName, {
                detail: data,
                bubbles: true,
                cancelable: true
            });
            element.dispatchEvent(event);
        },
        
        // 日志方法
        log: function(message, ...args) {
            if (this.config.debugMode || window.deepseekAIDebug) {
                console.log('[DeepSeek AI]', message, ...args);
            }
        },
        
        warn: function(message, ...args) {
            console.warn('[DeepSeek AI]', message, ...args);
        },
        
        error: function(message, ...args) {
            console.error('[DeepSeek AI]', message, ...args);
        },
        
        // 性能监控
        performanceLog: function(action, startTime) {
            if (this.config.debugMode || window.deepseekAIDebug) {
                const duration = performance.now() - startTime;
                this.log(`性能监控 - ${action}: ${duration.toFixed(2)}ms`);
            }
        },
        
        // 工具方法：防抖
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        // 工具方法：节流
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        }
    };
    
    // 兼容性检查和初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            DeepSeekAI.init();
        });
    } else {
        DeepSeekAI.init();
    }
    
    // 暴露到全局作用域
    window.DeepSeekAI = DeepSeekAI;
    
    // jQuery 插件形式（如果jQuery可用）
    if (typeof $ !== 'undefined') {
        $.fn.deepseekTypewriter = function(options) {
            const settings = $.extend({
                speed: DeepSeekAI.config.typewriterSpeed,
                text: null
            }, options);
            
            return this.each(function() {
                const $this = $(this);
                const text = settings.text || $this.data('original-text');
                if (text) {
                    DeepSeekAI.startTypewriter(this, text, settings.speed);
                }
            });
        };
    }
    
})(typeof jQuery !== 'undefined' ? jQuery : null);

// 向后兼容的全局函数
function initDeepSeekAITypewriter() {
    if (window.DeepSeekAI) {
        window.DeepSeekAI.initTypewriter();
    }
}

function forceDisplayDeepSeekAISummary(summaryHtml, delay) {
    if (window.DeepSeekAI) {
        window.DeepSeekAI.forceDisplaySummary(summaryHtml, delay);
    }
}