/**
 * LiveCode Browser Widget Compatibility Layer
 * Provides fallbacks and interfaces for running in LiveCode's browser widget
 */

(function(window) {
    'use strict';

    // Detect if running in LiveCode browser widget
    var isLiveCode = (function() {
        // LiveCode browser widget exposes liveCode object or has specific user agent
        if (typeof window.liveCode !== 'undefined') return true;
        if (navigator.userAgent.indexOf('LiveCode') !== -1) return true;
        // Check for CEF in LiveCode context
        if (window.external && typeof window.external.QueryInterface !== 'undefined') return true;
        // Check if we're in a restricted context (no opener, limited features)
        try {
            localStorage.setItem('__lctest', '1');
            localStorage.removeItem('__lctest');
            return false; // localStorage works, probably normal browser
        } catch (e) {
            return true; // localStorage blocked, likely widget context
        }
    })();

    // Storage adapter - uses localStorage if available, falls back to cookies/memory
    var StorageAdapter = {
        _memoryStore: {},
        _useLocalStorage: false,
        _useCookies: true,

        init: function() {
            try {
                localStorage.setItem('__test', '1');
                localStorage.removeItem('__test');
                this._useLocalStorage = true;
            } catch (e) {
                this._useLocalStorage = false;
                console.log('LiveCode Compat: localStorage unavailable, using cookie fallback');
            }
        },

        setItem: function(key, value) {
            // Try LiveCode communication first
            if (isLiveCode && typeof window.liveCode !== 'undefined' && window.liveCode.setValue) {
                try {
                    window.liveCode.setValue(key, value);
                } catch (e) {}
            }

            // Try localStorage
            if (this._useLocalStorage) {
                try {
                    localStorage.setItem(key, value);
                    return;
                } catch (e) {
                    this._useLocalStorage = false;
                }
            }

            // Cookie fallback (for session persistence)
            if (this._useCookies) {
                this._setCookie(key, value, 1); // 1 day expiry
            }

            // Memory fallback (non-persistent)
            this._memoryStore[key] = value;
        },

        getItem: function(key) {
            // Try LiveCode first
            if (isLiveCode && typeof window.liveCode !== 'undefined' && window.liveCode.getValue) {
                try {
                    var lcValue = window.liveCode.getValue(key);
                    if (lcValue !== null && lcValue !== undefined) {
                        return lcValue;
                    }
                } catch (e) {}
            }

            // Try localStorage
            if (this._useLocalStorage) {
                try {
                    var value = localStorage.getItem(key);
                    if (value !== null) return value;
                } catch (e) {
                    this._useLocalStorage = false;
                }
            }

            // Cookie fallback
            if (this._useCookies) {
                var cookieValue = this._getCookie(key);
                if (cookieValue !== null) return cookieValue;
            }

            // Memory fallback
            return this._memoryStore[key] || null;
        },

        removeItem: function(key) {
            // LiveCode
            if (isLiveCode && typeof window.liveCode !== 'undefined' && window.liveCode.removeValue) {
                try {
                    window.liveCode.removeValue(key);
                } catch (e) {}
            }

            // localStorage
            if (this._useLocalStorage) {
                try {
                    localStorage.removeItem(key);
                } catch (e) {}
            }

            // Cookie
            if (this._useCookies) {
                this._setCookie(key, '', -1);
            }

            // Memory
            delete this._memoryStore[key];
        },

        _setCookie: function(name, value, days) {
            var expires = '';
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = encodeURIComponent(name) + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Strict';
        },

        _getCookie: function(name) {
            var nameEQ = encodeURIComponent(name) + '=';
            var cookies = document.cookie.split(';');
            for (var i = 0; i < cookies.length; i++) {
                var c = cookies[i].trim();
                if (c.indexOf(nameEQ) === 0) {
                    return decodeURIComponent(c.substring(nameEQ.length));
                }
            }
            return null;
        }
    };

    // Initialize storage
    StorageAdapter.init();

    // LiveCode communication interface
    var LiveCodeBridge = {
        /**
         * Send a message to LiveCode
         * @param {string} message - The message/command to send
         * @param {*} data - Optional data payload
         */
        send: function(message, data) {
            if (!isLiveCode) return false;

            // Method 1: liveCode.do() - execute LiveCode script
            if (typeof window.liveCode !== 'undefined' && window.liveCode.do) {
                try {
                    var script = 'dispatch "browserCallback" with "' +
                        this._escape(message) + '","' +
                        this._escape(JSON.stringify(data || {})) + '"';
                    window.liveCode.do(script);
                    return true;
                } catch (e) {
                    console.warn('LiveCode.do failed:', e);
                }
            }

            // Method 2: URL scheme (for older LiveCode versions)
            try {
                var url = 'livecode://' + encodeURIComponent(message);
                if (data) {
                    url += '?data=' + encodeURIComponent(JSON.stringify(data));
                }
                // Create hidden iframe for navigation
                var iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = url;
                document.body.appendChild(iframe);
                setTimeout(function() {
                    document.body.removeChild(iframe);
                }, 100);
                return true;
            } catch (e) {
                console.warn('LiveCode URL scheme failed:', e);
            }

            return false;
        },

        /**
         * Register a callback function that LiveCode can call
         * @param {string} name - Function name
         * @param {function} callback - The callback function
         */
        registerCallback: function(name, callback) {
            window['lc_' + name] = callback;
            // Also register on global window for direct access
            window[name] = callback;
        },

        _escape: function(str) {
            return String(str).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        }
    };

    // Fetch polyfill/wrapper with timeout support for older CEF
    var FetchWrapper = {
        fetch: function(url, options) {
            options = options || {};

            // Use native fetch if available and not in problematic context
            if (window.fetch && !isLiveCode) {
                return window.fetch(url, options);
            }

            // XMLHttpRequest fallback for LiveCode widget
            return new Promise(function(resolve, reject) {
                var xhr = new XMLHttpRequest();
                var method = options.method || 'GET';

                xhr.open(method, url, true);

                // Set headers
                if (options.headers) {
                    Object.keys(options.headers).forEach(function(key) {
                        xhr.setRequestHeader(key, options.headers[key]);
                    });
                }

                // Handle timeout
                var timeoutMs = options.timeout || 30000;
                var timeoutId = setTimeout(function() {
                    xhr.abort();
                    reject(new Error('Request timeout'));
                }, timeoutMs);

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        clearTimeout(timeoutId);

                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve({
                                ok: true,
                                status: xhr.status,
                                statusText: xhr.statusText,
                                text: function() {
                                    return Promise.resolve(xhr.responseText);
                                },
                                json: function() {
                                    return Promise.resolve(JSON.parse(xhr.responseText));
                                }
                            });
                        } else if (xhr.status === 0) {
                            reject(new Error('Network error or CORS blocked'));
                        } else {
                            resolve({
                                ok: false,
                                status: xhr.status,
                                statusText: xhr.statusText,
                                text: function() {
                                    return Promise.resolve(xhr.responseText);
                                },
                                json: function() {
                                    return Promise.resolve(JSON.parse(xhr.responseText));
                                }
                            });
                        }
                    }
                };

                xhr.onerror = function() {
                    clearTimeout(timeoutId);
                    reject(new Error('Network error'));
                };

                // Send body if present
                if (options.body) {
                    xhr.send(options.body);
                } else {
                    xhr.send();
                }
            });
        }
    };

    // AbortController polyfill for older browsers
    if (typeof AbortController === 'undefined') {
        window.AbortController = function() {
            this.signal = { aborted: false };
            this.abort = function() {
                this.signal.aborted = true;
            };
        };
    }

    // MutationObserver polyfill check
    if (typeof MutationObserver === 'undefined') {
        // Simple polling fallback
        window.MutationObserver = function(callback) {
            this.observe = function(target, config) {
                var oldValue = target.innerHTML;
                setInterval(function() {
                    if (target.innerHTML !== oldValue) {
                        oldValue = target.innerHTML;
                        callback([{ type: 'childList', target: target }]);
                    }
                }, 500);
            };
            this.disconnect = function() {};
        };
    }

    // Keyboard input helper for LiveCode browser widget
    // LiveCode's browser widget often doesn't pass keyboard events properly
    var KeyboardHelper = {
        /**
         * Send a key to the currently focused input element
         * Usage from LiveCode: do "LiveCodeCompat.keyboard.sendKey('a')" in widget "browser"
         */
        sendKey: function(key) {
            var el = document.activeElement;
            if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA')) {
                if (key === 'Backspace') {
                    el.value = el.value.slice(0, -1);
                } else if (key === 'Enter') {
                    var event = new KeyboardEvent('keydown', { key: 'Enter', keyCode: 13, bubbles: true });
                    el.dispatchEvent(event);
                } else if (key.length === 1) {
                    el.value += key;
                    // Trigger input event
                    var inputEvent = new Event('input', { bubbles: true });
                    el.dispatchEvent(inputEvent);
                }
            }
        },

        /**
         * Set value directly on an input by ID
         * Usage from LiveCode: do "LiveCodeCompat.keyboard.setValue('passwordInput', '1234')" in widget "browser"
         */
        setValue: function(elementId, value) {
            var el = document.getElementById(elementId);
            if (el) {
                el.value = value;
                el.focus();
                // Trigger input event
                var inputEvent = new Event('input', { bubbles: true });
                el.dispatchEvent(inputEvent);
            }
        },

        /**
         * Focus an element by ID
         * Usage from LiveCode: do "LiveCodeCompat.keyboard.focus('passwordInput')" in widget "browser"
         */
        focus: function(elementId) {
            var el = document.getElementById(elementId);
            if (el) {
                el.focus();
                el.click(); // Some widgets need click to activate
            }
        },

        /**
         * Simulate pressing Enter on an element
         * Usage from LiveCode: do "LiveCodeCompat.keyboard.pressEnter('passwordInput')" in widget "browser"
         */
        pressEnter: function(elementId) {
            var el = elementId ? document.getElementById(elementId) : document.activeElement;
            if (el) {
                var event = new KeyboardEvent('keydown', {
                    key: 'Enter',
                    keyCode: 13,
                    which: 13,
                    bubbles: true,
                    cancelable: true
                });
                el.dispatchEvent(event);

                var keyupEvent = new KeyboardEvent('keyup', {
                    key: 'Enter',
                    keyCode: 13,
                    which: 13,
                    bubbles: true,
                    cancelable: true
                });
                el.dispatchEvent(keyupEvent);
            }
        }
    };

    // Channel number buffer for multi-digit channel entry
    // In LiveCode browser widget, rapid digit presses often only register once.
    // This buffers digits and sends the complete channel number after a timeout.
    var ChannelBuffer = {
        _digits: '',
        _timeout: null,
        _delay: 1500,
        _onChannel: null,
        _overlay: null,

        init: function(options) {
            options = options || {};
            if (options.delay) this._delay = options.delay;
            if (options.onChannel) this._onChannel = options.onChannel;
        },

        addDigit: function(digit) {
            digit = String(digit);
            if (!/^[0-9]$/.test(digit)) return;

            this._digits += digit;

            if (this._overlay) {
                this._updateOverlayDisplay();
            }

            if (this._timeout) clearTimeout(this._timeout);
            var self = this;
            this._timeout = setTimeout(function() {
                self.send();
            }, self._delay);
        },

        send: function() {
            if (this._timeout) {
                clearTimeout(this._timeout);
                this._timeout = null;
            }
            if (this._digits === '') return;

            var channel = this._digits;
            this._digits = '';

            if (this._overlay) {
                this._updateOverlayDisplay();
            }

            if (this._onChannel) {
                this._onChannel(channel);
            }

            LiveCodeBridge.send('channelEntered', { channel: channel });
        },

        clear: function() {
            if (this._timeout) {
                clearTimeout(this._timeout);
                this._timeout = null;
            }
            this._digits = '';
            if (this._overlay) {
                this._updateOverlayDisplay();
            }
        },

        getDigits: function() {
            return this._digits;
        },

        sendDigits: function(channel, digitCallback, delayBetween) {
            var digits = String(channel).split('');
            delayBetween = delayBetween || 300;

            digits.forEach(function(digit, index) {
                setTimeout(function() {
                    digitCallback(digit, index, digits.length);
                }, index * delayBetween);
            });
        },

        _updateOverlayDisplay: function() {
            var display = document.getElementById('lc-channel-display');
            if (display) {
                display.textContent = this._digits || '---';
            }
        }
    };

    // On-screen channel input overlay for LiveCode browser widget
    var ChannelOverlay = {
        _element: null,
        _visible: false,

        show: function(options) {
            options = options || {};
            if (this._element) {
                this._element.style.display = 'block';
                this._visible = true;
                return;
            }

            var overlay = document.createElement('div');
            overlay.id = 'lc-channel-overlay';
            overlay.innerHTML =
                '<style>' +
                    '#lc-channel-overlay {' +
                        'background: rgba(26, 26, 46, 0.95);' +
                        'border: 1px solid rgba(255, 255, 255, 0.1);' +
                        'border-radius: 16px;' +
                        'padding: 1rem;' +
                        'max-width: 280px;' +
                        'margin: 1rem auto;' +
                        'box-shadow: 0 8px 32px rgba(0,0,0,0.4);' +
                    '}' +
                    '#lc-channel-overlay.fixed-position {' +
                        'position: fixed;' +
                        'bottom: 20px;' +
                        'right: 20px;' +
                        'z-index: 10000;' +
                        'margin: 0;' +
                    '}' +
                    '#lc-channel-display {' +
                        'font-size: 2rem;' +
                        'font-weight: 700;' +
                        'text-align: center;' +
                        'color: #e8e8ed;' +
                        'padding: 0.75rem;' +
                        'margin-bottom: 0.75rem;' +
                        'background: rgba(0,0,0,0.3);' +
                        'border-radius: 10px;' +
                        'border: 1px solid rgba(99, 102, 241, 0.3);' +
                        'min-height: 3.5rem;' +
                        'letter-spacing: 0.25em;' +
                        'font-variant-numeric: tabular-nums;' +
                    '}' +
                    '.lc-channel-label {' +
                        'font-size: 0.8rem;' +
                        'color: #8b8b9a;' +
                        'text-align: center;' +
                        'margin-bottom: 0.5rem;' +
                        'text-transform: uppercase;' +
                        'letter-spacing: 0.1em;' +
                    '}' +
                    '#lc-channel-numpad {' +
                        'display: grid;' +
                        'grid-template-columns: repeat(3, 1fr);' +
                        'gap: 6px;' +
                    '}' +
                    '#lc-channel-numpad button {' +
                        'padding: 0.875rem;' +
                        'font-size: 1.25rem;' +
                        'font-weight: 600;' +
                        'border: 1px solid rgba(255,255,255,0.08);' +
                        'border-radius: 10px;' +
                        'background: rgba(99, 102, 241, 0.2);' +
                        'color: #e8e8ed;' +
                        'cursor: pointer;' +
                        'transition: all 0.15s;' +
                        'touch-action: manipulation;' +
                        '-webkit-tap-highlight-color: transparent;' +
                    '}' +
                    '#lc-channel-numpad button:hover {' +
                        'background: rgba(99, 102, 241, 0.4);' +
                    '}' +
                    '#lc-channel-numpad button:active {' +
                        'transform: scale(0.95);' +
                        'background: rgba(99, 102, 241, 0.5);' +
                    '}' +
                    '#lc-channel-numpad .ch-clear {' +
                        'background: rgba(239, 68, 68, 0.25);' +
                        'color: #f87171;' +
                    '}' +
                    '#lc-channel-numpad .ch-clear:hover {' +
                        'background: rgba(239, 68, 68, 0.4);' +
                    '}' +
                    '#lc-channel-numpad .ch-go {' +
                        'background: rgba(34, 197, 94, 0.3);' +
                        'color: #4ade80;' +
                    '}' +
                    '#lc-channel-numpad .ch-go:hover {' +
                        'background: rgba(34, 197, 94, 0.5);' +
                    '}' +
                '</style>' +
                '<div class="lc-channel-label">Enter Channel</div>' +
                '<div id="lc-channel-display">---</div>' +
                '<div id="lc-channel-numpad">' +
                    '<button type="button" data-digit="1">1</button>' +
                    '<button type="button" data-digit="2">2</button>' +
                    '<button type="button" data-digit="3">3</button>' +
                    '<button type="button" data-digit="4">4</button>' +
                    '<button type="button" data-digit="5">5</button>' +
                    '<button type="button" data-digit="6">6</button>' +
                    '<button type="button" data-digit="7">7</button>' +
                    '<button type="button" data-digit="8">8</button>' +
                    '<button type="button" data-digit="9">9</button>' +
                    '<button type="button" class="ch-clear" data-action="clear">C</button>' +
                    '<button type="button" data-digit="0">0</button>' +
                    '<button type="button" class="ch-go" data-action="go">GO</button>' +
                '</div>';

            var numpad = overlay.querySelector('#lc-channel-numpad');
            numpad.addEventListener('click', function(e) {
                var btn = e.target.closest('button');
                if (!btn) return;

                var digit = btn.getAttribute('data-digit');
                var action = btn.getAttribute('data-action');

                if (digit !== null) {
                    ChannelBuffer.addDigit(digit);
                } else if (action === 'clear') {
                    ChannelBuffer.clear();
                } else if (action === 'go') {
                    ChannelBuffer.send();
                }
            });

            if (options.position === 'fixed') {
                overlay.classList.add('fixed-position');
            }

            var container = null;
            if (options.container) {
                container = typeof options.container === 'string'
                    ? document.querySelector(options.container)
                    : options.container;
            }
            if (container) {
                container.appendChild(overlay);
            } else {
                document.body.appendChild(overlay);
            }

            this._element = overlay;
            this._visible = true;
            ChannelBuffer._overlay = overlay;
        },

        hide: function() {
            if (this._element) {
                this._element.style.display = 'none';
                this._visible = false;
            }
        },

        destroy: function() {
            if (this._element && this._element.parentNode) {
                this._element.parentNode.removeChild(this._element);
            }
            this._element = null;
            this._visible = false;
            ChannelBuffer._overlay = null;
        },

        isVisible: function() {
            return this._visible;
        }
    };

    // Global callbacks so LiveCode can send channel numbers directly
    // Usage from LiveCode: do "sendChannelDigit('2')" in widget "browser"
    window.sendChannelDigit = function(digit) {
        ChannelBuffer.addDigit(digit);
    };

    // Usage from LiveCode: do "sendChannelNumber('219')" in widget "browser"
    window.sendChannelNumber = function(channel) {
        ChannelBuffer._digits = String(channel);
        ChannelBuffer.send();
    };

    // Usage from LiveCode: do "clearChannelBuffer()" in widget "browser"
    window.clearChannelBuffer = function() {
        ChannelBuffer.clear();
    };

    // Export to global scope
    window.LiveCodeCompat = {
        isLiveCode: isLiveCode,
        storage: StorageAdapter,
        bridge: LiveCodeBridge,
        fetch: FetchWrapper.fetch.bind(FetchWrapper),
        keyboard: KeyboardHelper,
        channelBuffer: ChannelBuffer,
        channelOverlay: ChannelOverlay,

        // Utility to check environment
        getEnvironment: function() {
            return {
                isLiveCode: isLiveCode,
                hasLocalStorage: StorageAdapter._useLocalStorage,
                hasFetch: typeof window.fetch !== 'undefined',
                userAgent: navigator.userAgent
            };
        },

        // Notify LiveCode that page is ready
        notifyReady: function() {
            if (isLiveCode) {
                LiveCodeBridge.send('pageReady', {
                    url: window.location.href,
                    title: document.title
                });
            }
        }
    };

    // Auto-notify LiveCode when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.LiveCodeCompat.notifyReady();
        });
    } else {
        window.LiveCodeCompat.notifyReady();
    }

    // Debug logging for LiveCode context
    if (isLiveCode) {
        console.log('LiveCode Browser Widget detected - compatibility layer active');
        console.log('Channel buffer ready - use sendChannelDigit(), sendChannelNumber(), or channel overlay');
    }

})(window);
