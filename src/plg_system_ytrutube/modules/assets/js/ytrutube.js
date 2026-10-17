/*!
 * YT RuTube — lazy RuTube video element for YOOtheme Pro.
 * Player control uses the postMessage API: https://github.com/rutube/RutubePlayerJSAPI
 */
(function () {
    'use strict';

    var ORIGIN = /^https:\/\/([a-z0-9-]+\.)?rutube\.ru$/i;
    var ALLOW = 'clipboard-write; autoplay; fullscreen; picture-in-picture; encrypted-media';
    var players = []; // { iframe, root, params, ready }
    var preconnected = false;

    function send(iframe, type, data) {
        try {
            iframe.contentWindow.postMessage(JSON.stringify({ type: type, data: data || {} }), '*');
        } catch (e) {}
    }

    function getParams(src) {
        var params = {};
        var query = src.split('?')[1] || '';

        query.split('&').forEach(function (pair) {
            if (pair) {
                var parts = pair.split('=');
                params[decodeURIComponent(parts[0])] = decodeURIComponent(parts[1] || '');
            }
        });

        return params;
    }

    function addParam(src, name, value) {
        return src + (src.indexOf('?') === -1 ? '?' : '&') + name + '=' + encodeURIComponent(value);
    }

    function findPlayer(source) {
        for (var i = 0; i < players.length; i++) {
            if (players[i].iframe.contentWindow === source) {
                return players[i];
            }
        }

        // Lightbox iframes are created by UIkit, register them on first message
        var iframes = document.querySelectorAll('.uk-lightbox iframe');

        for (var j = 0; j < iframes.length; j++) {
            if (iframes[j].contentWindow === source && isOwnLightboxUrl(iframes[j].src)) {
                return register(iframes[j], null);
            }
        }

        return null;
    }

    function isOwnLightboxUrl(src) {
        var links = document.querySelectorAll('.ytrutube--lightbox .ytrutube__poster');

        for (var i = 0; i < links.length; i++) {
            if (links[i].href === src) {
                return true;
            }
        }

        return false;
    }

    function register(iframe, root) {
        var player = { iframe: iframe, root: root, params: getParams(iframe.src), ready: false };
        players.push(player);
        return player;
    }

    // Apply appearance options once the player reports it is ready
    function setup(player) {
        if (player.ready) {
            return;
        }

        player.ready = true;

        if (player.params.skinColor) {
            send(player.iframe, 'player:setSkinColor', { params: { color: player.params.skinColor } });
        }

        if (player.params.mute === '1') {
            send(player.iframe, 'player:mute');
        }

        // Older player versions don't send "player:playOptionsLoaded"
        player.startTimer = setTimeout(function () {
            start(player);
        }, 1000);
    }

    // Seek and play once the video options have been loaded
    function start(player) {
        clearTimeout(player.startTimer);

        if (player.started) {
            return;
        }

        player.started = true;

        var time = parseInt(player.params.bmstart || player.params.t, 10);

        if (time > 0) {
            send(player.iframe, 'player:setCurrentTime', { time: time });
        }

        if (player.params.autoplay === '1') {
            send(player.iframe, 'player:play');
        }
    }

    function pauseOthers(current) {
        players.forEach(function (player) {
            if (player !== current && player.root && player.root.hasAttribute('data-ytrutube-pause-others')) {
                send(player.iframe, 'player:pause');
            }
        });
    }

    function activate(root, autoplay) {
        if (!root || root.classList.contains('ytrutube--active')) {
            return;
        }

        var src = root.getAttribute('data-ytrutube-src');
        var box = root.querySelector('.ytrutube__box');

        if (!src || !box) {
            return;
        }

        var iframe = document.createElement('iframe');

        iframe.className = 'ytrutube__iframe';
        iframe.src = autoplay ? addParam(src, 'autoplay', 1) : src;
        iframe.title = root.getAttribute('data-ytrutube-title') || 'RuTube';
        iframe.setAttribute('allow', ALLOW);
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('webkitallowfullscreen', '');
        iframe.setAttribute('mozallowfullscreen', '');

        var player = register(iframe, root);

        iframe.addEventListener('load', function () {
            root.classList.add('ytrutube--loaded');

            // Fallback in case "player:ready" was sent before we listened
            setTimeout(function () {
                setup(player);
            }, 1500);
        });

        root.classList.add('ytrutube--active');
        box.appendChild(iframe);

        if (autoplay) {
            pauseOthers(player);
            iframe.focus();
        }
    }

    function preconnect() {
        if (preconnected) {
            return;
        }

        preconnected = true;

        ['https://rutube.ru', 'https://static.rutube.ru', 'https://pic.rtbcdn.ru'].forEach(function (href) {
            var link = document.createElement('link');
            link.rel = 'preconnect';
            link.href = href;
            link.crossOrigin = '';
            document.head.appendChild(link);
        });
    }

    // UIkit sizes lightbox iframes with fixed CSS, fit them to the video ratio instead
    function sizeLightbox() {
        var iframes = document.querySelectorAll('.uk-lightbox iframe:not([data-ytrutube-sized])');

        Array.prototype.forEach.call(iframes, function (iframe) {
            var links = document.querySelectorAll('.ytrutube--lightbox .ytrutube__poster');

            for (var i = 0; i < links.length; i++) {
                if (links[i].href === iframe.src) {
                    var parts = (links[i].getAttribute('data-ytrutube-ratio') || '16 / 9').split('/');
                    var ratio = parseFloat(parts[0]) / parseFloat(parts[1]) || 16 / 9;

                    iframe.setAttribute('data-ytrutube-sized', '');
                    iframe.style.width = 'min(90vw, ' + (85 * ratio).toFixed(3) + 'vh)';
                    iframe.style.height = 'auto';
                    iframe.style.maxWidth = 'none';
                    iframe.style.aspectRatio = parts.join('/');
                    return;
                }
            }
        });
    }

    function initViewport(scope) {
        var elements = (scope || document).querySelectorAll('.ytrutube--viewport:not(.ytrutube--active)');

        if (!elements.length) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            Array.prototype.forEach.call(elements, function (el) {
                activate(el, false);
            });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    activate(entry.target, false);
                }
            });
        }, { rootMargin: '200px 0px' });

        Array.prototype.forEach.call(elements, function (el) {
            observer.observe(el);
        });
    }

    document.addEventListener('click', function (e) {
        var poster = e.target.closest && e.target.closest('button.ytrutube__poster');

        if (poster) {
            e.preventDefault();
            activate(poster.closest('.ytrutube'), true);
        }
    });

    document.addEventListener('pointerover', function (e) {
        if (e.target.closest && e.target.closest('.ytrutube')) {
            preconnect();
        }
    }, { passive: true });

    document.addEventListener('focusin', function (e) {
        if (e.target.closest && e.target.closest('.ytrutube')) {
            preconnect();
        }
    });

    window.addEventListener('message', function (e) {
        if (!ORIGIN.test(e.origin)) {
            return;
        }

        var message;

        try {
            message = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
        } catch (err) {
            return;
        }

        if (!message || !message.type) {
            return;
        }

        var player = findPlayer(e.source);

        if (!player) {
            return;
        }

        if (message.type === 'player:ready') {
            setup(player);
        } else if (message.type === 'player:playOptionsLoaded') {
            setup(player);
            start(player);
        } else if (message.type === 'player:changeState' && message.data && message.data.state === 'playing') {
            pauseOthers(player);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initViewport();
        });
    } else {
        initViewport();
    }

    // Elements re-rendered by the YOOtheme Pro customizer preview
    if ('MutationObserver' in window) {
        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length) {
                    sizeLightbox();
                    initViewport();
                    return;
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
})();
