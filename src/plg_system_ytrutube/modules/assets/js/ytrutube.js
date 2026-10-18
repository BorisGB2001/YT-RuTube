/*!
 * YT RuTube — lazy RuTube video element for YOOtheme Pro.
 * Player control uses the postMessage API: https://github.com/rutube/RutubePlayerJSAPI
 */
(function () {
    'use strict';

    var ORIGIN = /^https:\/\/([a-z0-9-]+\.)?rutube\.ru$/i;
    var ALLOW = 'clipboard-write; autoplay; fullscreen; picture-in-picture; encrypted-media';
    var SEEK_STEP = 10;
    var HIDE_DELAY = 2500;
    var START_TIMEOUT = 2000;
    var PAUSE_RETRY = 1000;
    var TICK_WINDOW = 1500;
    var DEBUG = /[?&]ytrutube_debug=1/.test(location.search);
    var players = [];
    var preconnected = false;

    function log() {
        if (DEBUG && window.console) {
            console.info.apply(console, ['[YT RuTube]'].concat([].slice.call(arguments)));
        }
    }

    var ICONS = {
        play: '<path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11.04-6.86a1 1 0 0 0 0-1.72L9.5 4.28A1 1 0 0 0 8 5.14z"/>',
        pause: '<path d="M6.5 5h3.75v14H6.5zM13.75 5h3.75v14h-3.75z"/>',
        volume: '<path d="M3 9v6h4l5 4V5L7 9H3zm13.5 3A4.5 4.5 0 0 0 14 8v8a4.5 4.5 0 0 0 2.5-4zM14 3.2v2.1a7 7 0 0 1 0 13.4v2.1a9 9 0 0 0 0-17.6z"/>',
        muted: '<path d="M3 9v6h4l5 4V5L7 9H3zm18.3.7-1.4-1.4-2.4 2.4-2.4-2.4-1.4 1.4 2.4 2.4-2.4 2.4 1.4 1.4 2.4-2.4 2.4 2.4 1.4-1.4-2.4-2.4z"/>',
        expand: '<path d="M4 4h6v2H6v4H4zm10 0h6v6h-2V6h-4zM4 14h2v4h4v2H4zm14 0h2v6h-6v-2h4z"/>',
        compress: '<path d="M8 4h2v6H4V8h4zm6 0h2v4h4v2h-6zM4 14h6v6H8v-4H4zm10 0h6v2h-4v4h-2z"/>'
    };

    function icon(name) {
        return '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true">' + ICONS[name] + '</svg>';
    }

    function send(player, type, data) {
        log('→', type, data || '');

        try {
            player.iframe.contentWindow.postMessage(JSON.stringify({ type: type, data: data || {} }), '*');
        } catch (e) {}
    }

    function getParams(src) {
        var params = {};

        (src.split('?')[1] || '').split('&').forEach(function (pair) {
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

    function getOptions(root) {
        try {
            return JSON.parse(root.getAttribute('data-ytrutube-ui') || '{}');
        } catch (e) {
            return {};
        }
    }

    function formatTime(seconds) {
        seconds = Math.max(0, Math.floor(seconds || 0));

        var h = Math.floor(seconds / 3600);
        var m = Math.floor(seconds % 3600 / 60);
        var s = seconds % 60;

        return (h ? h + ':' + (m < 10 ? '0' : '') : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function findPlayer(source) {
        for (var i = 0; i < players.length; i++) {
            if (players[i].iframe.contentWindow === source) {
                return players[i];
            }
        }

        return null;
    }

    /* Player lifecycle */

    function activate(root, autoplay, box) {
        var inline = !box;
        box = box || root.querySelector('.ytrutube__box');

        var src = root.getAttribute('data-ytrutube-src');

        if (!src || !box || (inline && root.classList.contains('ytrutube--active'))) {
            return null;
        }

        var options = getOptions(root);
        var iframe = document.createElement('iframe');
        var resume = parseInt(root.getAttribute('data-ytrutube-resume'), 10);

        // Continue after a hard pause
        if (resume > 0) {
            root.removeAttribute('data-ytrutube-resume');
            src = addParam(addParam(src.replace(/([?&])(bmstart|t)=\d+/g, '$1').replace(/[?&]+$/, '').replace(/&&+/g, '&').replace('?&', '?'), 'bmstart', resume), 't', resume);
        }

        iframe.className = 'ytrutube__iframe';
        iframe.src = autoplay ? addParam(src, 'autoplay', 1) : src;
        iframe.title = root.getAttribute('data-ytrutube-title') || 'RuTube';
        iframe.setAttribute('allow', ALLOW);
        iframe.setAttribute('allowfullscreen', '');

        var player = {
            root: root,
            box: box,
            iframe: iframe,
            inline: inline,
            options: options,
            params: getParams(iframe.src),
            playing: false,
            started: false,
            advert: false,
            muted: getParams(src).mute === '1',
            time: 0,
            duration: parseFloat(root.getAttribute('data-ytrutube-duration')) || 0
        };

        players.push(player);

        if (options.custom) {
            // Once the video plays, the RuTube player gets no clicks: links, menus and
            // recommendations inside it can't be used, only our controls. Until then
            // clicks pass through, as browsers may require a click inside the player
            // to start it, and ads may need one too.
            box.classList.add('ytrutube__box--waiting');
            buildControls(player);
        }

        iframe.addEventListener('load', function () {
            box.classList.add('ytrutube__box--loaded');

            if (inline) {
                root.classList.add('ytrutube--loaded');
            }

            // Fallback in case "player:ready" was sent before we listened
            setTimeout(function () {
                setup(player);
            }, 1500);
        });

        if (inline) {
            root.classList.add('ytrutube--active');
        }

        box.insertBefore(iframe, box.firstChild);
        update(player);

        if (autoplay) {
            pauseOthers(player);
        }

        return player;
    }

    // Apply appearance options once the player reports it is ready
    function setup(player) {
        if (player.ready) {
            return;
        }

        player.ready = true;

        if (player.params.skinColor) {
            send(player, 'player:setSkinColor', { params: { color: player.params.skinColor } });
        }

        if (player.muted) {
            send(player, 'player:mute');
        }

        // Older player versions don't send "player:playOptionsLoaded"
        player.startTimer = setTimeout(function () {
            start(player);
        }, 1000);
    }

    // Seek and play once the video options have been loaded
    function start(player) {
        clearTimeout(player.startTimer);

        if (player.optionsApplied) {
            return;
        }

        player.optionsApplied = true;

        var time = parseInt(player.params.bmstart || player.params.t, 10);

        if (time > 0) {
            send(player, 'player:setCurrentTime', { time: time });
        }

        if (player.params.autoplay === '1') {
            send(player, 'player:play');
        }
    }

    // Back to the poster, e.g. when the video ends (instead of RuTube recommendations)
    function destroy(player) {
        var index = players.indexOf(player);

        if (index === -1) {
            return;
        }

        players.splice(index, 1);
        clearTimeout(player.hideTimer);

        if (isFullscreen(player)) {
            toggleFullscreen(player);
        }

        send(player, 'player:remove');

        [player.iframe, player.shield, player.controls].forEach(function (el) {
            if (el && el.parentNode) {
                el.parentNode.removeChild(el);
            }
        });

        player.box.classList.remove('ytrutube__box--loaded', 'ytrutube__box--playing', 'ytrutube__box--idle', 'ytrutube__box--waiting');

        if (player.inline) {
            player.root.classList.remove('ytrutube--active', 'ytrutube--loaded');

            var poster = player.root.querySelector('.ytrutube__poster');

            if (poster) {
                poster.focus({ preventScroll: true });
            }
        } else if (player.modal && window.UIkit) {
            window.UIkit.modal(player.modal).hide();
        }
    }

    // The player's state events are unreliable when an ad blocker breaks RuTube's ad
    // module, so playback is judged by the playback time actually moving.
    function isAdvancing(player) {
        return Date.now() - (player.lastTick || 0) < TICK_WINDOW;
    }

    // The video really plays: cover the RuTube player and hide its own controls
    function markStarted(player) {
        if (player.started) {
            return;
        }

        player.started = true;

        if (player.options.custom) {
            send(player, 'player:hideControls');
            player.iframe.setAttribute('tabindex', '-1');

            if (player.box.contains(document.activeElement) || document.activeElement === document.body) {
                player.shield.focus({ preventScroll: true });
            }
        }
    }

    function pauseOthers(current) {
        players.forEach(function (player) {
            if (player !== current && player.root.hasAttribute('data-ytrutube-pause-others')) {
                send(player, 'player:pause');
            }
        });
    }

    /* Own controls */

    function button(cls, label) {
        var el = document.createElement('button');
        el.type = 'button';
        el.className = 'ytrutube__control ' + cls;
        el.setAttribute('aria-label', label);
        return el;
    }

    function buildControls(player) {
        var o = player.options;
        var box = player.box;
        var labels = o.labels || {};

        var shield = document.createElement('div');
        shield.className = 'ytrutube__shield';
        shield.tabIndex = 0;
        shield.setAttribute('role', 'button');
        shield.setAttribute('aria-label', player.root.getAttribute('data-ytrutube-title') || 'RuTube');

        var bar = document.createElement('div');
        bar.className = 'ytrutube__controls';

        var els = {};

        if (o.play) {
            els.play = button('ytrutube__control--play', labels.play || 'Play');
            bar.appendChild(els.play);
        }

        if (o.time) {
            els.time = document.createElement('span');
            els.time.className = 'ytrutube__time';
            bar.appendChild(els.time);
        }

        if (o.progress) {
            els.progress = document.createElement('div');
            els.progress.className = 'ytrutube__progress' + (o.seek ? ' ytrutube__progress--seek' : '');
            els.progress.innerHTML = '<div class="ytrutube__progress-fill"></div>';
            els.fill = els.progress.firstChild;
            bar.appendChild(els.progress);
        } else {
            bar.appendChild(Object.assign(document.createElement('span'), { className: 'ytrutube__spacer' }));
        }

        if (o.volume) {
            els.volume = button('ytrutube__control--volume', labels.mute || 'Mute');
            bar.appendChild(els.volume);
        }

        if (o.fullscreen) {
            els.fullscreen = button('ytrutube__control--fullscreen', labels.fullscreen || 'Fullscreen');
            bar.appendChild(els.fullscreen);
        }

        player.shield = shield;
        player.controls = bar;
        player.els = els;

        box.appendChild(shield);

        if (bar.querySelector('.ytrutube__control, .ytrutube__time, .ytrutube__progress')) {
            box.appendChild(bar);
        }

        shield.addEventListener('click', function () {
            if (o.click) {
                togglePlay(player);
            }

            wake(player);
        });

        shield.addEventListener('dblclick', function () {
            if (o.fullscreen) {
                toggleFullscreen(player);
            }
        });

        shield.addEventListener('keydown', function (e) {
            onKey(player, e);
        });

        bar.addEventListener('keydown', function (e) {
            if (e.target === bar || e.key !== ' ') {
                onKey(player, e);
            }
        });

        els.play && els.play.addEventListener('click', function () {
            togglePlay(player);
        });

        els.volume && els.volume.addEventListener('click', function () {
            toggleMute(player);
        });

        els.fullscreen && els.fullscreen.addEventListener('click', function () {
            toggleFullscreen(player);
        });

        if (els.progress && o.seek) {
            bindSeek(player);
        }

        ['pointermove', 'pointerdown', 'focusin'].forEach(function (type) {
            box.addEventListener(type, function () {
                wake(player);
            });
        });

        box.addEventListener('pointerleave', function () {
            hideSoon(player, 600);
        });
    }

    function bindSeek(player) {
        var progress = player.els.progress;
        var dragging = false;

        function timeAt(e) {
            var rect = progress.getBoundingClientRect();
            return Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width)) * player.duration;
        }

        progress.addEventListener('pointerdown', function (e) {
            if (!player.duration) {
                return;
            }

            dragging = true;
            progress.setPointerCapture(e.pointerId);
            player.time = timeAt(e);
            update(player);
        });

        progress.addEventListener('pointermove', function (e) {
            if (dragging) {
                player.time = timeAt(e);
                update(player);
            }
        });

        progress.addEventListener('pointerup', function (e) {
            if (dragging) {
                dragging = false;
                seek(player, timeAt(e));
            }
        });
    }

    function onKey(player, e) {
        var o = player.options;
        var key = e.key;

        if ((key === ' ' || key === 'k') && (o.click || o.play)) {
            togglePlay(player);
        } else if ((key === 'f' || key === 'F') && o.fullscreen) {
            toggleFullscreen(player);
        } else if ((key === 'm' || key === 'M') && o.volume) {
            toggleMute(player);
        } else if ((key === 'ArrowRight' || key === 'ArrowLeft') && o.progress && o.seek && player.duration) {
            seek(player, player.time + (key === 'ArrowRight' ? SEEK_STEP : -SEEK_STEP));
        } else if (key === 'Escape' && player.pseudoFullscreen) {
            toggleFullscreen(player);
        } else {
            return;
        }

        e.preventDefault();
        wake(player);
    }

    function togglePlay(player) {
        var playingNow = player.lastTick ? isAdvancing(player) : player.playing;

        player.playing = !playingNow;
        send(player, player.playing ? 'player:play' : 'player:pause');
        clearTimeout(player.playTimer);
        clearTimeout(player.pauseTimer);

        if (!player.playing) {
            // When RuTube's ad module is blocked, the player resumes right after a pause.
            // If the video keeps going, unload the player and resume from this position later.
            player.pauseTimer = setTimeout(function () {
                if (isAdvancing(player)) {
                    hardPause(player);
                }
            }, PAUSE_RETRY);
        } else {
            pauseOthers(player);

            // The browser may block a start that wasn't clicked inside the player:
            // let the next click through to the RuTube player.
            player.playTimer = setTimeout(function () {
                if (!player.confirmed && !isAdvancing(player)) {
                    log('play not confirmed, clicks go to the RuTube player');
                    player.playing = false;
                    player.started = false;
                    send(player, 'player:showControls');
                    update(player);
                }
            }, START_TIMEOUT);
        }

        update(player);
    }

    function hardPause(player) {
        log('pause ignored by the player, unloading it at', Math.floor(player.time));
        player.root.setAttribute('data-ytrutube-resume', Math.floor(player.time));
        destroy(player);
    }

    function toggleMute(player) {
        player.muted = !player.muted;
        send(player, player.muted ? 'player:mute' : 'player:unMute');
        update(player);
    }

    function seek(player, time) {
        player.time = Math.min(Math.max(0, time), player.duration || time);
        send(player, 'player:setCurrentTime', { time: player.time });
        update(player);
    }

    function isFullscreen(player) {
        var el = document.fullscreenElement || document.webkitFullscreenElement;
        return el === player.box || !!player.pseudoFullscreen;
    }

    // Fullscreen of our box (iframe + controls). iPhone has no element fullscreen,
    // there the box covers the viewport instead.
    function toggleFullscreen(player) {
        var box = player.box;

        if (isFullscreen(player)) {
            if (player.pseudoFullscreen) {
                player.pseudoFullscreen = false;
                box.classList.remove('ytrutube__box--fullscreen');
                document.documentElement.classList.remove('ytrutube-noscroll');
            } else {
                (document.exitFullscreen || document.webkitExitFullscreen).call(document);
            }
        } else if (box.requestFullscreen || box.webkitRequestFullscreen) {
            (box.requestFullscreen || box.webkitRequestFullscreen).call(box);
        } else {
            player.pseudoFullscreen = true;
            box.classList.add('ytrutube__box--fullscreen');
            document.documentElement.classList.add('ytrutube-noscroll');
        }

        setTimeout(function () {
            update(player);
        }, 100);
    }

    function wake(player) {
        player.box.classList.remove('ytrutube__box--idle');
        hideSoon(player, HIDE_DELAY);
    }

    function hideSoon(player, delay) {
        clearTimeout(player.hideTimer);

        if (player.options.autohide) {
            player.hideTimer = setTimeout(function () {
                if (player.playing && !player.controls.contains(document.activeElement)) {
                    player.box.classList.add('ytrutube__box--idle');
                }
            }, delay);
        }
    }

    function update(player) {
        player.box.classList.toggle('ytrutube__box--playing', player.playing);

        if (player.options.custom) {
            player.box.classList.toggle('ytrutube__box--waiting', !player.started || player.advert);
        }

        var els = player.els;
        var labels = player.options.labels || {};

        if (!els) {
            return;
        }

        if (els.play) {
            els.play.innerHTML = icon(player.playing ? 'pause' : 'play');
            els.play.setAttribute('aria-label', player.playing ? labels.pause || 'Pause' : labels.play || 'Play');
        }

        if (els.volume) {
            els.volume.innerHTML = icon(player.muted ? 'muted' : 'volume');
            els.volume.setAttribute('aria-label', player.muted ? labels.unmute || 'Unmute' : labels.mute || 'Mute');
        }

        if (els.fullscreen) {
            var fullscreen = isFullscreen(player);
            els.fullscreen.innerHTML = icon(fullscreen ? 'compress' : 'expand');
            els.fullscreen.setAttribute('aria-label', fullscreen ? labels.exitFullscreen || 'Exit fullscreen' : labels.fullscreen || 'Fullscreen');
        }

        if (els.time) {
            els.time.textContent = formatTime(player.time) + (player.duration ? ' / ' + formatTime(player.duration) : '');
        }

        if (els.fill) {
            els.fill.style.width = (player.duration ? Math.min(100, player.time / player.duration * 100) : 0) + '%';
        }
    }

    /* Lightbox: own UIkit modal, so the same controls work there */

    function openModal(root) {
        var UIkit = window.UIkit;

        if (!UIkit) {
            activate(root, true);
            return;
        }

        var parts = (getComputedStyle(root).getPropertyValue('--ytrutube-ratio') || '16 / 9').split('/');
        var ratio = parseFloat(parts[0]) / parseFloat(parts[1]) || 16 / 9;

        var modal = document.createElement('div');
        modal.className = 'uk-modal ytrutube-modal';
        modal.setAttribute('style', root.getAttribute('style') || '');
        modal.innerHTML = '<div class="uk-modal-dialog uk-margin-auto-vertical">'
            + '<button class="uk-modal-close-outside" type="button" uk-close></button>'
            + '<div class="ytrutube__box"></div></div>';

        var box = modal.querySelector('.ytrutube__box');
        box.style.width = 'min(90vw, ' + (85 * ratio).toFixed(3) + 'vh)';

        document.body.appendChild(modal);

        var player = activate(root, true, box);

        if (player) {
            player.modal = modal;
        }

        modal.addEventListener('hidden', function (e) {
            if (e.target === modal) {
                player && destroy(player);
                modal.parentNode && modal.parentNode.removeChild(modal);
            }
        });

        UIkit.modal(modal).show();
    }

    /* Events */

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

    function initViewport() {
        var elements = document.querySelectorAll('.ytrutube--viewport:not(.ytrutube--active):not([data-ytrutube-observed])');

        Array.prototype.forEach.call(elements, function (el) {
            el.setAttribute('data-ytrutube-observed', '');

            if (!('IntersectionObserver' in window)) {
                activate(el, false);
                return;
            }

            var observer = new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) {
                    observer.disconnect();
                    activate(el, false);
                }
            }, { rootMargin: '200px 0px' });

            observer.observe(el);
        });
    }

    document.addEventListener('click', function (e) {
        var poster = e.target.closest && e.target.closest('button.ytrutube__poster');
        var root = poster && poster.closest('.ytrutube');

        if (!root || !root.hasAttribute('data-ytrutube-src')) {
            return;
        }

        e.preventDefault();

        if (root.classList.contains('ytrutube--lightbox')) {
            openModal(root);
        } else {
            activate(root, true);
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

    ['fullscreenchange', 'webkitfullscreenchange'].forEach(function (type) {
        document.addEventListener(type, function () {
            players.forEach(update);
        });
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

        var player = message && message.type && findPlayer(e.source);

        if (!player) {
            return;
        }

        var data = message.data || {};

        if (message.type !== 'player:currentTime') {
            log('←', message.type, data);
        }

        switch (message.type) {
            case 'player:ready':
                setup(player);
                break;

            case 'player:playOptionsLoaded':
                setup(player);
                start(player);
                break;

            case 'player:changeState':
                // States: loading, playing, pause, completed, advert (older players: paused, stopped).
                // "advert" alone is not trusted: with a blocked ad module it can stick while the
                // video plays. Ads are detected by player:rollState instead.
                player.confirmed = data.state === 'playing';

                if (data.state === 'playing') {
                    clearTimeout(player.playTimer);
                    player.playing = true;
                    markStarted(player);
                    pauseOthers(player);
                    hideSoon(player, HIDE_DELAY);
                } else if (/^(pause|paused|stopped|completed)$/.test(data.state)) {
                    player.playing = false;
                    player.box.classList.remove('ytrutube__box--idle');
                }

                update(player);
                break;

            case 'player:rollState':
                player.advert = data.state === 'play';
                update(player);
                break;

            case 'player:currentTime':
                // A single report is not a start signal (RuTube reports the saved resume
                // position before playback); time moving forward is.
                var time = parseFloat(data.time) || 0;

                if (time > player.time && time - player.time < 5 && !player.advert) {
                    if (player.lastTick) {
                        player.confirmed = true;
                        player.playing = true;
                        clearTimeout(player.playTimer);
                        markStarted(player);
                    }

                    player.lastTick = Date.now();
                }

                player.time = time;
                update(player);
                break;

            case 'player:durationChange':
                player.duration = parseFloat(data.duration) || player.duration;
                update(player);
                break;

            case 'player:playComplete':
                player.root.removeAttribute('data-ytrutube-resume');
                if (player.options.endPoster) {
                    destroy(player);
                } else {
                    player.playing = false;
                    update(player);
                }
                break;
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initViewport);
    } else {
        initViewport();
    }

    // Elements re-rendered by the YOOtheme Pro customizer preview
    if ('MutationObserver' in window) {
        new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length) {
                    initViewport();
                    return;
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }
})();
