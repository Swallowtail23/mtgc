/*
Version:     1.7
Date:        28/12/25
Name:        asyncImageRefresh.js
Purpose:     Shared async image refresh helpers.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

(function (window, $) {
    'use strict';

    const mtgAsyncImage = window.mtgAsyncImage || {};
    const mtgAsyncImageSeen = window.mtgAsyncImageSeen || {};
    const mtgImageCacheName = window.mtgImageCacheName || 'mtg-images-v1';

    window.mtgAsyncImage = mtgAsyncImage;
    window.mtgAsyncImageSeen = mtgAsyncImageSeen;

    function stripCache(src) {
        return src ? src.replace(/\?.*$/, '') : '';
    }

    function swapImageWithFade($img, newSrc, forceSwap) {
        const currentSrc = stripCache($img.attr('src'));
        const targetSrc = stripCache(newSrc);
        if (!targetSrc || (!forceSwap && currentSrc === targetSrc)) {
            return;
        }
        const loader = new Image();
        loader.onload = function () {
            $img.css('opacity', '0');
            $img.off('load.mtgfade').on('load.mtgfade', function () {
                const $self = $(this);
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        $self.css('opacity', '1');
                        $self.addClass('async-refresh-flash');
                        setTimeout(function () {
                            $self.removeClass('async-refresh-flash');
                        }, 350);
                    });
                });
            });
            $img.attr('src', newSrc);
            if ($img[0] && $img[0].complete) {
                setTimeout(function () {
                    $img.trigger('load');
                }, 0);
            }
        };
        loader.onerror = function () {
            $img.css('opacity', '1').attr('src', '/images/back.jpg');
        };
        loader.src = newSrc;
    }

    function refreshImageCache(baseUrl, bustUrl) {
        if (!('caches' in window)) {
            return Promise.resolve(false);
        }
        const cacheBustUrl = bustUrl || baseUrl + '?t=' + Date.now();
        return fetch(cacheBustUrl, { cache: 'reload' })
            .then(function (response) {
                if (!response || !response.ok) {
                    return false;
                }
                return caches.open(mtgImageCacheName)
                    .then(function (cache) {
                        return cache.put(baseUrl, response.clone()).then(function () {
                            return true;
                        });
                    });
            })
            .catch(function () {
                return false;
            });
    }

    function handleImageRefresh(cardId, response, options) {
        if (!response || !response.success) {
            return;
        }
        const opts = options || {};
        const useFaces = opts.useFaces === true;
        const onFlipReady = opts.onFlipReady || null;

        if (response.front_changed) {
            const frontSrc = response.front && response.front.indexOf('cardimg') !== -1
                ? response.front
                : '/images/back.jpg';
            const frontBustUrl = frontSrc + '?t=' + Date.now();
            if (useFaces) {
                const frontTargets = $('img[data-cardid="' + cardId + '"][data-face="front"]');
                refreshImageCache(frontSrc, frontBustUrl).then(function () {
                    frontTargets.each(function () {
                        const $target = $(this);
                        $target.attr('data-front-src', frontSrc);
                        swapImageWithFade($target, frontBustUrl, true);
                    });
                });
            } else {
                const targets = $('img[data-cardid="' + cardId + '"]');
                refreshImageCache(frontSrc, frontBustUrl).then(function () {
                    targets.each(function () {
                        const $target = $(this);
                        $target.attr('data-front-src', frontSrc);
                        swapImageWithFade($target, frontBustUrl, true);
                    });
                });
            }
        }

        if (response.back_changed && response.back) {
            const backBustUrl = response.back + '?t=' + Date.now();
            if (useFaces) {
                const backTargets = $('img[data-cardid="' + cardId + '"][data-face="back"]');
                refreshImageCache(response.back, backBustUrl).then(function () {
                    backTargets.each(function () {
                        const $target = $(this);
                        $target.attr('data-back-src', response.back);
                        swapImageWithFade($target, backBustUrl, true);
                    });
                });
            } else {
                const targets = $('img[data-cardid="' + cardId + '"]');
                refreshImageCache(response.back, backBustUrl).then(function () {
                    targets.each(function () {
                        const $target = $(this);
                        $target.attr('data-back-src', response.back);
                        if ($target.hasClass('flipped')) {
                            swapImageWithFade($target, backBustUrl, true);
                        }
                    });
                });
            }
        }

        if (
            onFlipReady
            && response.front
            && response.back
            && response.front.indexOf('cardimg') !== -1
            && response.back.indexOf('cardimg') !== -1
        ) {
            onFlipReady(cardId);
        }
    }

    function refreshCardImagesAsync(options) {
        const opts = options || {};
        const selector = opts.selector || 'img[data-cardid]';
        const queue = [];
        let inFlight = 0;
        const maxConcurrent = 3;
        let pauseUntil = 0;

        $(document).on('pointerdown keydown', function () {
            pauseUntil = Date.now() + 300;
        });

        $(selector).each(function () {
            const $img = $(this);
            const src = $img.attr('src') || '';
            const match = src.match(/cardimg\/[^/]+\/([a-f0-9-]+)(?:_b)?\.jpg/i);
            const cardId = match ? match[1] : $img.data('cardid');
            if (!cardId) {
                return;
            }
            if (mtgAsyncImageSeen[cardId]) {
                return;
            }
            mtgAsyncImageSeen[cardId] = true;
            queue.push(cardId);
        });

        function scheduleNext() {
            if (Date.now() < pauseUntil) {
                setTimeout(scheduleNext, 120);
                return;
            }
            if (inFlight >= maxConcurrent || queue.length === 0) {
                return;
            }
            const cardId = queue.shift();
            inFlight += 1;
            $.ajax({
                url: 'ajax/ajaximagecheck.php',
                type: 'POST',
                data: { cardid: cardId },
                dataType: 'json',
                success: function (response) {
                    handleImageRefresh(cardId, response, opts);
                },
                complete: function () {
                    inFlight -= 1;
                    setTimeout(scheduleNext, 0);
                }
            });
            setTimeout(scheduleNext, 0);
        }

        setTimeout(scheduleNext, 0);
    }

    window.mtgSwapImageWithFade = swapImageWithFade;
    window.mtgRefreshImageCache = refreshImageCache;
    window.mtgHandleImageRefresh = handleImageRefresh;
    window.mtgRefreshCardImagesAsync = refreshCardImagesAsync;
})(window, window.jQuery);
