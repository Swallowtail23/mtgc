/*
Version:     1.14
Date:        29/12/25
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
                hideCardPlaceholder($self);
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

    function isChangedFlag(value) {
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    function hideCardPlaceholder($img) {
        const cardId = $img.data('cardid');
        if (!cardId) {
            return;
        }
        const placeholders = $('.card-info-placeholder[data-cardid="' + cardId + '"]');
        if (placeholders.length) {
            placeholders.addClass('card-info-hidden');
        }
        $img.removeClass('card-image-hidden');
    }

    function buildBustUrl(url) {
        if (!url) {
            return '';
        }
        const token = 't=' + Date.now();
        return url.indexOf('?') === -1 ? url + '?' + token : url + '&' + token;
    }

    function retryPlaceholderImages(options) {
        const opts = options || {};
        const selector = opts.selector || '.card-info-placeholder';
        const queue = [];
        let inFlight = 0;
        const maxConcurrent = 3;

        $(selector).each(function () {
            const $placeholder = $(this);
            if ($placeholder.hasClass('card-info-hidden')) {
                return;
            }
            const cardId = $placeholder.data('cardid');
            if (!cardId) {
                return;
            }
            const $img = $('img[data-cardid="' + cardId + '"]').first();
            if (!$img.length) {
                return;
            }
            const frontSrc = $img.attr('data-front-src') || '';
            if (!frontSrc) {
                return;
            }
            queue.push({ cardId: cardId, $img: $img, frontSrc: frontSrc });
        });

        function scheduleNext() {
            if (inFlight >= maxConcurrent || queue.length === 0) {
                return;
            }
            const item = queue.shift();
            inFlight += 1;
            fetch(item.frontSrc, { method: 'HEAD', cache: 'no-store' })
                .then(function (response) {
                    if (response && response.ok) {
                        swapImageWithFade(item.$img, buildBustUrl(item.frontSrc), true);
                    }
                })
                .catch(function () {
                    return;
                })
                .finally(function () {
                    inFlight -= 1;
                    setTimeout(scheduleNext, 0);
                });
            setTimeout(scheduleNext, 0);
        }

        setTimeout(scheduleNext, 0);
    }

    function handleImageRefresh(cardId, response, options) {
        if (!response || !response.success) {
            return;
        }
        const opts = options || {};
        const useFaces = opts.useFaces === true;
        const onFlipReady = opts.onFlipReady || null;

        const frontChanged = isChangedFlag(response.front_changed);
        const backChanged = isChangedFlag(response.back_changed);
        const placeholder = $('.card-info-placeholder[data-cardid="' + cardId + '"]').first();
        const placeholderVisible = placeholder.length && !placeholder.hasClass('card-info-hidden');
        const hasFrontImage = response.front && response.front.indexOf('cardimg') !== -1;
        const shouldRevealFront = placeholderVisible && hasFrontImage;

        if (frontChanged || shouldRevealFront) {
            const frontSrc = hasFrontImage ? response.front : '/images/back.jpg';
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

        if (backChanged && response.back) {
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

    $(document).on('click', '.card-info-refresh', function (event) {
        event.preventDefault();
        const cardId = $(this).data('cardid');
        if (!cardId) {
            return;
        }
        $.ajax({
            url: 'ajax/ajaximagecheck.php',
            type: 'POST',
            data: { cardid: cardId },
            dataType: 'json',
            success: function (response) {
                handleImageRefresh(cardId, response, {});
            }
        });
    });

    window.mtgSwapImageWithFade = swapImageWithFade;
    window.mtgRefreshImageCache = refreshImageCache;
    window.mtgHandleImageRefresh = handleImageRefresh;
    window.mtgRefreshCardImagesAsync = refreshCardImagesAsync;
    window.mtgRetryPlaceholderImages = retryPlaceholderImages;
})(window, window.jQuery);
