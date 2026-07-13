(function () {
    'use strict';

    function loadScript(src, onload) {
        var s = document.createElement('script');
        s.src = src;
        s.onload = onload;
        document.head.appendChild(s);
    }

    function runScripts(container) {
        container.querySelectorAll('script').forEach(function (old) {
            var s = document.createElement('script');
            for (var i = 0; i < old.attributes.length; i++) {
                s.setAttribute(old.attributes[i].name, old.attributes[i].value);
            }
            s.text = old.text;
            old.parentNode.replaceChild(s, old);
        });
    }

    function attachZoomIndicator(viewer, inner) {
        var host = inner.parentNode;
        if (!host || host.querySelector('.iiif-player-zoom')) return;
        var el = document.createElement('div');
        el.className = 'iiif-player-zoom';
        el.setAttribute('aria-live', 'polite');
        el.textContent = '';
        host.appendChild(el);

        var hideTimer = null;
        var lastPct = null;
        function show() {
            el.classList.add('is-visible');
            if (hideTimer) clearTimeout(hideTimer);
            hideTimer = setTimeout(function () {
                el.classList.remove('is-visible');
            }, 3000);
        }
        function update(force) {
            var vp = viewer.viewport;
            if (!vp) return;
            // 100 % = 1 image pixel rendered on 1 screen pixel (image displayed
            // at its native resolution). imageToViewportZoom(1) returns the
            // viewport zoom that achieves this 1:1 ratio.
            var nativeZoom = vp.imageToViewportZoom ? vp.imageToViewportZoom(1) : null;
            if (!nativeZoom) return;
            var pct = Math.round((vp.getZoom(true) / nativeZoom) * 100);
            if (pct === lastPct && !force) return;
            lastPct = pct;
            el.textContent = pct + ' %';
            show();
        }
        // 'zoom' is not a viewer-level event in OpenSeadragon. The relevant
        // events are 'open' (initial render) and 'animation' (continuous ticks
        // during zoom/pan; we filter pan-only updates by comparing rounded
        // percentages).
        viewer.addHandler('open', function () { update(true); });
        viewer.addHandler('animation', function () { update(false); });
        if (viewer.world && viewer.world.getItemCount() > 0) update(true);
    }

    function initCore(stage) {
        var player = stage.getAttribute('data-player');
        var embedId = stage.getAttribute('data-embed-id');
        var assetJs = stage.getAttribute('data-asset-js');
        var inner = document.createElement('div');
        inner.id = embedId + '-inner';
        inner.style.cssText = 'width:100%;height:100%;';
        stage.appendChild(inner);

        if (player === 'mirador_core') {
            var manifestId = stage.getAttribute('data-manifest');
            var start = function () {
                window.Mirador.player({ id: inner.id, windows: [{ manifestId: manifestId }] });
            };
            if (window.Mirador) start(); else loadScript(assetJs, start);
        } else if (player === 'openseadragon') {
            var firstTile = JSON.parse(stage.getAttribute('data-tile-source'));
            var prefix = stage.getAttribute('data-asset-prefix');
            var tilesJson = stage.getAttribute('data-tiles');
            var tiles = tilesJson ? JSON.parse(tilesJson) : null;

            function toTileSource(t) {
                return t.type === 'iiif' ? t.url : { type: 'image', url: t.url };
            }

            if (tiles && tiles.length > 1) {
                var pos = stage.getAttribute('data-sidebar-position') || 'bottom';
                var horizontal = (pos === 'top' || pos === 'bottom');
                stage.classList.add('iiif-player-sidebar-' + pos);
                stage.removeChild(inner);
                var osdArea = document.createElement('div');
                osdArea.className = 'iiif-player-osd-area';
                osdArea.appendChild(inner);
                var sidebar = document.createElement('div');
                sidebar.className = 'iiif-player-sidebar';
                var navPrev = document.createElement('button');
                navPrev.type = 'button';
                navPrev.className = 'iiif-player-nav iiif-player-nav-prev';
                navPrev.setAttribute('aria-label', horizontal ? 'Previous' : 'Up');
                navPrev.innerHTML = horizontal ? '&#9664;' : '&#9650;';
                var navNext = document.createElement('button');
                navNext.type = 'button';
                navNext.className = 'iiif-player-nav iiif-player-nav-next';
                navNext.setAttribute('aria-label', horizontal ? 'Next' : 'Down');
                navNext.innerHTML = horizontal ? '&#9654;' : '&#9660;';
                var track = document.createElement('div');
                track.className = 'iiif-player-sidebar-track';
                sidebar.appendChild(navPrev);
                sidebar.appendChild(track);
                sidebar.appendChild(navNext);
                if (pos === 'top' || pos === 'left') {
                    stage.appendChild(sidebar);
                    stage.appendChild(osdArea);
                } else {
                    stage.appendChild(osdArea);
                    stage.appendChild(sidebar);
                }

                var thumbs = [];
                var currentIndex = 0;
                function scrollToCenter(thumb) {
                    if (!thumb) return;
                    if (horizontal) {
                        var target = thumb.offsetLeft - (track.clientWidth / 2) + (thumb.offsetWidth / 2);
                        track.scrollTo({ left: target, behavior: 'smooth' });
                    } else {
                        var target2 = thumb.offsetTop - (track.clientHeight / 2) + (thumb.offsetHeight / 2);
                        track.scrollTo({ top: target2, behavior: 'smooth' });
                    }
                }
                function goTo(i) {
                    if (i < 0 || i >= tiles.length || i === currentIndex) {
                        updateNav();
                        return;
                    }
                    currentIndex = i;
                    if (window._iiifPlayerOsd && window._iiifPlayerOsd[inner.id]) {
                        window._iiifPlayerOsd[inner.id].open(toTileSource(tiles[i]));
                    }
                    thumbs.forEach(function (el, j) {
                        el.classList.toggle('active', j === i);
                    });
                    scrollToCenter(thumbs[i]);
                    updateNav();
                }
                tiles.forEach(function (t, i) {
                    var a = document.createElement('button');
                    a.type = 'button';
                    a.className = 'iiif-player-thumb';
                    a.title = t.title || '';
                    a.setAttribute('aria-label', t.title || '');
                    a.innerHTML = '<img src="' + t.thumb + '" alt="">';
                    a.addEventListener('click', function () { goTo(i); });
                    if (i === 0) a.classList.add('active');
                    track.appendChild(a);
                    thumbs.push(a);
                });

                // Translate vertical wheel to horizontal scroll on horizontal
                // sidebars; without this the wheel event bubbles up to the
                // OpenSeadragon canvas and zooms the viewer instead.
                if (horizontal) {
                    track.addEventListener('wheel', function (e) {
                        var delta = e.deltaX || e.deltaY;
                        if (!delta) return;
                        e.preventDefault();
                        track.scrollLeft += delta;
                    }, { passive: false });
                }

                function updateNav() {
                    navPrev.disabled = currentIndex <= 0;
                    navNext.disabled = currentIndex >= tiles.length - 1;
                }
                navPrev.addEventListener('click', function () { goTo(currentIndex - 1); });
                navNext.addEventListener('click', function () { goTo(currentIndex + 1); });
                updateNav();
            }

            var optsJson = stage.getAttribute('data-osd-options');
            var stringsJson = stage.getAttribute('data-osd-strings');
            var extraOpts = optsJson ? JSON.parse(optsJson) : {};
            var strings = stringsJson ? JSON.parse(stringsJson) : {};

            var showZoom = stage.getAttribute('data-show-zoom') === '1';

            var start2 = function () {
                Object.keys(strings).forEach(function (k) {
                    window.OpenSeadragon.setString(k, strings[k]);
                });
                var opts = Object.assign({}, extraOpts);
                opts.id = inner.id;
                opts.prefixUrl = prefix;
                opts.tileSources = [toTileSource(firstTile)];
                window._iiifPlayerOsd = window._iiifPlayerOsd || {};
                var viewer = window.OpenSeadragon(opts);
                window._iiifPlayerOsd[inner.id] = viewer;
                if (showZoom) attachZoomIndicator(viewer, inner);
            };
            if (window.OpenSeadragon) start2(); else loadScript(assetJs, start2);
        }
    }

    function setup(root) {
        var btn = root.querySelector('.iiif-player-toggle');
        // The overlay (with the stage and close button) may have been portalled
        // out of the button by the theme's advanced player; resolve it via the
        // toggle's aria-controls so the toggle still binds wherever it lives.
        var ovId = btn ? btn.getAttribute('aria-controls') : null;
        var overlay = root.querySelector('.iiif-player-overlay')
            || (ovId ? document.getElementById(ovId) : null);
        var stage = root.querySelector('.iiif-player-stage')
            || (overlay ? overlay.querySelector('.iiif-player-stage') : null);
        if (!stage) return;

        var player = stage.getAttribute('data-player');
        var isCore = player === 'mirador_core' || player === 'openseadragon';

        // Inline mode: init player immediately, no button/overlay.
        if (root.classList.contains('iiif-player-inline')) {
            if (isCore) initCore(stage);
            return;
        }

        var closeBtn = overlay ? overlay.querySelector('.iiif-player-close') : null;
        var tpl = root.querySelector('template.iiif-player-template');
        if (!btn || !overlay || !closeBtn) return;

        var lazy = stage.getAttribute('data-lazy') === '1';
        var loaded = false;

        function open() {
            if (!loaded) {
                if (isCore) {
                    initCore(stage);
                } else if (lazy && tpl) {
                    stage.appendChild(tpl.content.cloneNode(true));
                    runScripts(stage);
                }
                loaded = true;
            }
            overlay.style.display = 'flex';
            btn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            // Module viewers init hidden: force relayout.
            window.dispatchEvent(new Event('resize'));
            // a11y: move focus into the dialog (do not keep it on the toggle).
            closeBtn.focus();
        }
        function close() {
            overlay.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
            // a11y: return focus to the toggle (e.g. after Escape).
            btn.focus();
        }

        btn.addEventListener('click', open);
        closeBtn.addEventListener('click', close);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.style.display !== 'none') close();
        });
    }

    function init() {
        document.querySelectorAll('.iiif-player-button').forEach(function (root) {
            if (root.dataset.iiifPlayerButtonInit) return;
            root.dataset.iiifPlayerButtonInit = '1';
            setup(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
