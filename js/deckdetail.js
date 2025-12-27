/*
Version:     2.54
Date:        27/12/25
Name:        deckdetail.js
Purpose:     Deck detail page JS handlers and ajax fragment refresh.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

function toggleInfoBox() {
    var infoBox = document.getElementById("infoBox");
    infoBox.style.display = (infoBox.style.display === "none" || infoBox.style.display === "")
        ? "block"
        : "none";
}

var deckNumber = 0;
var isCommanderDeck = false;
var deckName = '';
var randomDrawEnabled = false;
var randomDrawRefs = [];
var csrfToken = '';
var lastAppliedVersion = 0;
var fragmentTargets = {};
var decksideHeroAutoLoaded = false;
var deckSectionState = {
    creatures: true,
    instantsorcery: true,
    other: true,
    lands: true,
    sideboard: true
};
if (window.mtgDeckDetailConfig) {
    deckNumber = window.mtgDeckDetailConfig.deckNumber || 0;
    isCommanderDeck = window.mtgDeckDetailConfig.isCommanderDeck === true;
    deckName = window.mtgDeckDetailConfig.deckName || '';
    randomDrawEnabled = window.mtgDeckDetailConfig.randomDrawEnabled === true;
    if (Array.isArray(window.mtgDeckDetailConfig.randomDrawRefs)) {
        randomDrawRefs = window.mtgDeckDetailConfig.randomDrawRefs;
    }
    if (window.mtgDeckDetailConfig.csrfToken) {
        csrfToken = window.mtgDeckDetailConfig.csrfToken;
    }
    if (window.mtgDeckDetailConfig.deckVersion) {
        lastAppliedVersion = parseInt(window.mtgDeckDetailConfig.deckVersion, 10) || 0;
    }
    if (window.mtgDeckDetailConfig.fragmentTargets) {
        fragmentTargets = window.mtgDeckDetailConfig.fragmentTargets;
    }
}

function updateRandomDrawState() {
    var $randomDraw = $('#deck-random-draw-fragment');
    if (!$randomDraw.length) {
        randomDrawEnabled = false;
        randomDrawRefs = [];
        return;
    }
    randomDrawEnabled = $randomDraw.data('enabled') === 1 || $randomDraw.data('enabled') === '1';
    var refsRaw = $randomDraw.attr('data-refs') || '[]';
    try {
        randomDrawRefs = JSON.parse(refsRaw);
    } catch (e) {
        randomDrawRefs = [];
    }
}

function normalizeVersion(rawVersion) {
    var versionInt = parseInt(rawVersion, 10);
    if (isNaN(versionInt)) {
        return 0;
    }
    return versionInt;
}

function updateDeckVersion(rawVersion) {
    var versionInt = normalizeVersion(rawVersion);
    if (versionInt > lastAppliedVersion) {
        lastAppliedVersion = versionInt;
    }
}

function preloadFirstDeckImage() {
    var $firstImg = $('#decklist-fragment img.deckcardimg').first();
    if (!$firstImg.length) {
        return;
    }
    var src = $firstImg.data('front-src') || $firstImg.attr('src');
    if (!src) {
        return;
    }
    var img = new Image();
    img.src = src;
    var heroImg = document.getElementById('deckside-hero-img');
    if (heroImg && window.innerWidth >= 1890) {
        setDecksideHeroRotatable(false);
        var firstLink = $firstImg.closest('a').attr('href') || '#';
        setDecksideHeroLink(firstLink);
        if (src && src.indexOf('/images/back.jpg') === -1) {
            setDecksideHeroImage(src);
            decksideHeroAutoLoaded = true;
            if (window.console && console.debug) {
                console.debug('[DEBUG] Deckside hero auto-loaded from first deck image.');
            }
        } else {
            $firstImg.off('load.decksideAuto').on('load.decksideAuto', function () {
                if (decksideHeroAutoLoaded) {
                    return;
                }
                var loadedSrc = $firstImg.data('front-src') || this.src;
                if (!loadedSrc || loadedSrc.indexOf('/images/back.jpg') !== -1) {
                    return;
                }
                setDecksideHeroImage(loadedSrc);
                setDecksideHeroLink(firstLink);
                decksideHeroAutoLoaded = true;
                if (window.console && console.debug) {
                    console.debug('[DEBUG] Deckside hero auto-loaded after async image update.');
                }
            });
        }
    }
}

function maybeLoadDecksideHeroOnResize() {
    if (window.innerWidth < 1890) {
        return;
    }
    if (decksideHeroAutoLoaded) {
        return;
    }
    var heroImg = document.getElementById('deckside-hero-img');
    if (!heroImg) {
        return;
    }
    if (heroImg.src && heroImg.src.indexOf('/images/back.jpg') === -1) {
        decksideHeroAutoLoaded = true;
        return;
    }
    preloadFirstDeckImage();
}

function setDecksideHeroRotatable(isRotatable) {
    var heroImg = document.getElementById('deckside-hero-img');
    if (!heroImg) {
        return;
    }
    var shouldRotate = isRotatable === true;
    heroImg.classList.remove('is-rotated');
    heroImg.dataset.rotatable = shouldRotate ? '1' : '0';
    if (window.console && console.log) {
        console.log('[DEBUG] Deckside hero rotation updated', { isRotatable: shouldRotate });
    }
}

function bindDecksideHeroRotation() {
    var heroImg = document.getElementById('deckside-hero-img');
    if (!heroImg || heroImg.dataset.rotationBound === '1') {
        return;
    }
    heroImg.dataset.rotationBound = '1';
    var rotateTimer = null;
    var unrotateTimer = null;
    heroImg.addEventListener('mouseenter', function () {
        if (heroImg.dataset.rotatable !== '1') {
            return;
        }
        if (unrotateTimer) {
            clearTimeout(unrotateTimer);
        }
        if (rotateTimer) {
            clearTimeout(rotateTimer);
        }
        rotateTimer = setTimeout(function () {
            heroImg.classList.add('is-rotated');
        }, 300);
    });
    heroImg.addEventListener('mouseleave', function () {
        if (rotateTimer) {
            clearTimeout(rotateTimer);
        }
        unrotateTimer = setTimeout(function () {
            heroImg.classList.remove('is-rotated');
        }, 300);
    });
}

function updateDecksideHeroBackground(src) {
    var decksideHero = document.getElementById('deckside-hero');
    if (!decksideHero) {
        return;
    }
    var isBack = typeof src === 'string' && src.indexOf('/images/back.jpg') !== -1;
    decksideHero.classList.toggle('has-image', !isBack);
}

function setDecksideHeroImage(src) {
    var heroImg = document.getElementById('deckside-hero-img');
    if (!heroImg) {
        return;
    }
    if (heroImg.dataset.src === src) {
        return;
    }
    heroImg.classList.remove('is-visible');
    updateDecksideHeroBackground(src);
    heroImg.dataset.src = src;
    heroImg.onload = function () {
        updateDecksideHeroBackground(this.src);
        heroImg.classList.add('is-visible');
    };
    heroImg.src = src;
    if (heroImg.complete) {
        updateDecksideHeroBackground(heroImg.src);
        heroImg.classList.add('is-visible');
    }
}

function setDecksideHeroLink(href) {
    var heroLink = document.getElementById('deckside-hero-link');
    if (!heroLink) {
        return;
    }
    var safeHref = typeof href === 'string' && href.length ? href : '#';
    if (heroLink.getAttribute('href') === safeHref) {
        return;
    }
    heroLink.setAttribute('href', safeHref);
    if (window.console && console.log) {
        console.log('[DEBUG] Deckside hero link updated', { href: safeHref });
    }
}

function updateRandomDrawPlacement() {
    var $fragment = $('#deck-random-draw-fragment');
    var $footer = $('#deckside-footer');
    var $anchor = $('#deck-random-draw-anchor');
    if (!$fragment.length || !$footer.length || !$anchor.length) {
        return;
    }
    var hasContent = $fragment.attr('data-has-content') === '1';
    if (!hasContent) {
        $footer.hide();
        $fragment.removeClass('is-docked').addClass('is-inline');
        return;
    }
    var deckside = document.getElementById('deckside');
    if (!deckside) {
        return;
    }
    var columnCount = parseInt(window.getComputedStyle(deckside).columnCount, 10);
    var shouldDock = columnCount >= 3 && window.innerHeight > 1100;
    if (shouldDock) {
        $footer.show();
        if (!$fragment.parent().is($footer)) {
            $footer.append($fragment);
        }
        $fragment.addClass('is-docked').removeClass('is-inline');
    } else {
        $footer.hide();
        if (!$fragment.prev().is($anchor)) {
            $anchor.after($fragment);
        }
        $fragment.removeClass('is-docked').addClass('is-inline');
    }
}

function getDeckSectionKeys() {
    return ['creatures', 'instantsorcery', 'other', 'lands', 'sideboard'];
}

function setDeckSectionCollapsed(section, collapsed) {
    deckSectionState[section] = collapsed;
    var $rows = $('tr.deckrow[data-section="' + section + '"]');
    if (collapsed) {
        $rows.hide();
    } else {
        $rows.show();
    }
    var $toggle = $('.js-decksection-toggle[data-section="' + section + '"]');
    if ($toggle.length) {
        $toggle.text(collapsed ? 'chevron_right' : 'expand_more');
    }
}

function applyDeckSectionState() {
    getDeckSectionKeys().forEach(function (section) {
        var collapsed = deckSectionState[section] !== false;
        setDeckSectionCollapsed(section, collapsed);
    });
}

function getFragmentList() {
    if (window.mtgDeckDetailConfig && Array.isArray(window.mtgDeckDetailConfig.fragments)) {
        return window.mtgDeckDetailConfig.fragments.slice();
    }
    return Object.keys(getFragmentTargets());
}

function getFragmentTargets() {
    if (window.mtgDeckDetailConfig && window.mtgDeckDetailConfig.fragmentTargets) {
        return window.mtgDeckDetailConfig.fragmentTargets;
    }
    return {
        decklist: 'decklist-fragment',
        colour_identity: 'deck-colour-identity-fragment',
        warnings: 'deck-warnings-fragment',
        mana_value: 'deck-mana-value-fragment',
        mana_costs: 'deck-mana-costs-fragment',
        deck_value: 'deck-value-fragment',
        deck_lists: 'deck-lists-fragment',
        export_list: 'deck-export-fragment',
        missing: 'deck-missing-fragment',
        buy_missing: 'deck-buy-fragment',
        random_draw: 'deck-random-draw-fragment'
    };
}

function hardReloadDeckDetail() {
    if (deckNumber) {
        window.location.href = 'deckdetail.php?deck=' + deckNumber;
    } else {
        window.location.reload();
    }
}

function swapImageWithFade($img, newSrc) {
    $img.css('opacity', '0');
    $img.off('load.mtgfade').on('load.mtgfade', function() {
        var $self = $(this);
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                $self.css('opacity', '1');
            });
        });
    });
    $img.attr('src', newSrc);
    if ($img[0] && $img[0].complete) {
        setTimeout(function() {
            $img.trigger('load');
        }, 0);
    }
}

var deckImageQueue = [];
var deckImageQueued = {};
var deckImageInFlight = 0;
var deckImagePauseUntil = 0;
var deckImageMaxConcurrent = 3;

function enqueueDeckImage(cardId, priority) {
    if (!cardId) {
        return;
    }
    if (deckImageQueued[cardId] !== true) {
        deckImageQueued[cardId] = true;
        if (priority === true) {
            deckImageQueue.unshift(cardId);
        } else {
            deckImageQueue.push(cardId);
        }
    } else if (priority === true) {
        var idx = deckImageQueue.indexOf(cardId);
        if (idx > 0) {
            deckImageQueue.splice(idx, 1);
            deckImageQueue.unshift(cardId);
        }
    }
    scheduleDeckImageLoad();
}

function scheduleDeckImageLoad() {
    if (Date.now() < deckImagePauseUntil) {
        setTimeout(scheduleDeckImageLoad, 120);
        return;
    }
    if (deckImageInFlight >= deckImageMaxConcurrent || deckImageQueue.length === 0) {
        return;
    }
    var cardId = deckImageQueue.shift();
    deckImageInFlight += 1;
    $.ajax({
        url: 'ajax/ajaximagecheck.php',
        type: 'POST',
        data: { cardid: cardId },
        dataType: 'json',
        success: function(response) {
            if (!response || !response.success) {
                return;
            }
            if (response.front && response.front.indexOf('cardimg') !== -1) {
                var newSrc = response.front + '?t=' + Date.now();
                $('img[data-cardid="' + cardId + '"]').each(function() {
                    var $target = $(this);
                    $target.attr('data-front-src', response.front);
                    $target.attr('data-back-src', response.back || $target.attr('data-back-src') || '');
                    swapImageWithFade($target, newSrc);
                });
            }
            if (response.back && response.back.indexOf('cardimg') !== -1) {
                $('img[data-cardid="' + cardId + '"]').attr('data-back-src', response.back);
            }
        },
        complete: function() {
            deckImageInFlight -= 1;
            setTimeout(scheduleDeckImageLoad, 0);
        }
    });
    setTimeout(scheduleDeckImageLoad, 0);
}

function refreshCardImagesAsync() {
    var seen = {};
    $('img[data-cardid]').each(function() {
        var cardId = $(this).data('cardid');
        if (!cardId || seen[cardId]) {
            return;
        }
        seen[cardId] = true;
        enqueueDeckImage(cardId, false);
    });
}

window.enqueueDeckImage = enqueueDeckImage;
window.refreshCardImagesAsync = refreshCardImagesAsync;

window.bindRandomCardEvents = function() {
    $('td').off('mouseenter mouseleave');
    $('td.hoverTD').off('touchstart touchmove touchend');
    $('td.hoverTD a.taphover').off('click');
    var touchDetected = false;
    var hoverTimeout;
    var lastHoveredDiv = null;
    var documentTouchHandler = null;
    var documentClickHandler = null;
    var windowScrollHandler = null;

    function hideTouchHoverPreview(reason) {
        if (lastHoveredDiv && lastHoveredDiv.is(':visible')) {
            console.debug('[DEBUG]', 'Touch hover cleared.', reason);
            lastHoveredDiv.hide();
        }
    }

    function clearTouchHoverOnInteraction(e, reason) {
        if (!touchDetected) {
            return;
        }
        if ($(e.target).closest('td.hoverTD').length) {
            return;
        }
        hideTouchHoverPreview(reason);
    }

    function setupNonTouchEvents() {
        $('td').on('mouseenter', function(e) {
            if (touchDetected) return;

            var $link = $(this).find('a.taphover');
            if ($link.length) {
                var id = $link.attr('id');
                var $div = $('#' + id.replace('-taphover', ''));
                var cardId = $div.find('img[data-cardid]').data('cardid') || $link.data('cardid');
                if (cardId) {
                    enqueueDeckImage(cardId, true);
                }

                if (lastHoveredDiv && lastHoveredDiv !== $div) {
                    clearTimeout(lastHoveredDiv.data('timeoutId'));
                    lastHoveredDiv.hide();
                }

                lastHoveredDiv = $div;

                hoverTimeout = setTimeout(function() {
                    showHoverDiv($link, e);
                }, 200); // 200ms delay before showing the hover image

                $div.on('mouseenter', function() {
                    clearTimeout($div.data('timeoutId'));
                }).on('mouseleave', function() {
                    var timeoutId = setTimeout(function() {
                        $div.hide("slow");
                    }, 200); // 200ms delay before hiding the div
                    $div.data('timeoutId', timeoutId);
                });
            }
        }).on('mouseleave', function(e) {
            if (touchDetected) return;

            clearTimeout(hoverTimeout);

            var $link = $(this).find('a.taphover');
            if ($link.length) {
                var id = $link.attr('id');
                var $div = $('#' + id.replace('-taphover', ''));
                var timeoutId = setTimeout(function() {
                    $div.hide("slow");
                }, 200); // 200ms delay before hiding the div
                $div.data('timeoutId', timeoutId);
            }
        });
    }

    function removeNonTouchEvents() {
        $('td').off('mouseenter mouseleave');
    }

    function setupTouchEvents() {
        var touchStartTime = 0;
        var touchStartX = 0;
        var touchStartY = 0;
        var isScrolling = false;
        var shouldTriggerLink = false;

        // Touch start event
        $('td.hoverTD').on('touchstart', function(e) {
            touchStartTime = Date.now();
            isScrolling = false;
            shouldTriggerLink = false;

            var touch = e.originalEvent.touches[0] || e.originalEvent.changedTouches[0];
            touchStartX = touch.pageX;
            touchStartY = touch.pageY;

            // Add touch-active and no-hover classes
            $('tr.deckrow').addClass('no-hover');
        });

        // Touch move event
        $('td.hoverTD').on('touchmove', function(e) {
            var touch = e.originalEvent.touches[0] || e.originalEvent.changedTouches[0];
            var moveX = touch.pageX;
            var moveY = touch.pageY;

            if (Math.abs(moveX - touchStartX) > 10 || Math.abs(moveY - touchStartY) > 10) {
                isScrolling = true;
            }
        });

        // Touch end event
        $('td.hoverTD').on('touchend', function(e) {
            var touchDuration = Date.now() - touchStartTime;

            if (!isScrolling && touchDuration < 300) {
                // 300ms threshold to distinguish between tap and scroll
                var $link = $(this).find('a.taphover');
                if ($link.length) {
                    e.preventDefault();
                    shouldTriggerLink = true;
                    if (lastHoveredDiv && lastHoveredDiv.is(':visible')) {
                        lastHoveredDiv.hide();
                    }

                    // Ensure event contains touches or changedTouches directly
                    var touch = e.originalEvent.changedTouches[0] || e.originalEvent.touches[0];
                    var customEvent = {
                        pageX: touch.pageX,
                        pageY: touch.pageY
                    };
                    showHoverDiv($link, customEvent); // Custom event with correct coordinates passed here
                    lastHoveredDiv = $('#' + $link.attr('id').replace('-taphover', ''));
                }
            } else {
                shouldTriggerLink = false;
            }
        });

        // Click event to prevent link following
        $('td.hoverTD a.taphover').on('click', function(e) {
            if (!shouldTriggerLink) {
                e.preventDefault();
            }
        });

        if (documentTouchHandler) {
            document.removeEventListener('touchstart', documentTouchHandler, true);
        }
        if (documentClickHandler) {
            document.removeEventListener('click', documentClickHandler, true);
        }
        if (windowScrollHandler) {
            window.removeEventListener('scroll', windowScrollHandler, { passive: true });
        }

        documentTouchHandler = function(e) {
            clearTouchHoverOnInteraction(e, 'document touch');
        };
        documentClickHandler = function(e) {
            clearTouchHoverOnInteraction(e, 'document click');
        };
        windowScrollHandler = function() {
            hideTouchHoverPreview('scroll');
        };

        document.addEventListener('touchstart', documentTouchHandler, true);
        document.addEventListener('click', documentClickHandler, true);
        window.addEventListener('scroll', windowScrollHandler, { passive: true });
    }

    function getMenuWidth() {
        var menu = document.getElementById('menu');
        if (menu) {
            var computedStyle = window.getComputedStyle(menu);
            var left = parseInt(computedStyle.left, 10);

            // If the menu is off-screen (negative left position), consider it inactive
            if (left < 0) {
                return 0;
            }

            return menu.offsetWidth;
        }
        return 0;
    }

    function getHeaderHeight() {
        var header = document.getElementById('header');
        if (header) {
            var computedStyle = window.getComputedStyle(header);
            var height = parseInt(computedStyle.height, 10);

            return height;
        }
        return 0;
    }

    function showHoverDiv($link, e) {
        var id = $link.attr('id');
        var $div = $('#' + id.replace('-taphover', ''));
        var mouseX, mouseY;
        var cardId = $div.find('img[data-cardid]').data('cardid') || $link.data('cardid');
        if (cardId) {
            enqueueDeckImage(cardId, true);
        }
        var decksideHero = document.getElementById('deckside-hero');
        if (decksideHero && window.innerWidth >= 1890) {
            var $img = $div.find('img.deckcardimg').first();
            setDecksideHeroRotatable($div.hasClass('splitfloat'));
            var heroSrc = $img.attr('src') || $img.data('front-src');
            if (heroSrc) {
                setDecksideHeroImage(heroSrc);
            }
            setDecksideHeroLink($link.attr('href') || '#');
            $img.off('load.decksideHero').on('load.decksideHero', function () {
                setDecksideHeroImage(this.src);
            });
            $('.deckcardimgdiv').hide();
            return;
        }

        if (e.pageX && e.pageY) {
            mouseX = e.pageX;
            mouseY = e.pageY;
        } else {
            // Handle cases where pageX and pageY are not directly available
            var touch = e.changedTouches ? e.changedTouches[0] : e.touches[0];
            mouseX = touch.pageX;
            mouseY = touch.pageY;
        }

        // Force reflow to ensure dimensions are calculated
        $div.css('display', 'block');
        var divWidth = $div.outerWidth();
        var divHeight = $div.outerHeight();
        $div.css('display', 'none');

        // Set fallback value for divHeight if it's 0
        if (divWidth === 0) {
            divWidth = 180; // Assuming 180 as the default width
        }
        if (divHeight === 0) {
            divHeight = 258; // Assuming 258 as the default height
        }

        // Get the width of the menu if it's active
        var menuWidth = getMenuWidth();
        // Get the height of the header
        var headerHeight = getHeaderHeight();
        // Adjust position to prevent overflow if necessary
        var leftPosition = mouseX - 150;
        // Always show the image 80px below mouse click, even when scrolled
        var topPosition = mouseY - headerHeight + 80;

        // Ensure the div stays within the viewport and does not overlap the menu
        var viewportWidth = $(window).width();
        var viewportHeight = $(window).height();
        var bottomViewable = viewportHeight + window.scrollY;
        var realImgBottom = mouseY + 80 + divHeight;
        console.log({
            mouseX: mouseX,
            mouseY: mouseY,
            menuWidth: menuWidth,
            headerHeight: headerHeight,
            leftPosition: leftPosition,
            topPosition: topPosition,
            viewportWidth: viewportWidth,
            viewportHeight: viewportHeight,
            bottomViewable: bottomViewable,
            divWidth: divWidth,
            divHeight: divHeight,
            realImgBottom: realImgBottom
        });

        // TopPosition is the distance from the top even if that is scrolled off the top of the view
        // It positions the top of the image below the header
        //      It is relative to bottom of header
        // viewportHeight is what can be seen
        // window.scrollY is what is off the top

        if (leftPosition + divWidth > viewportWidth) {
            leftPosition = viewportWidth - divWidth - 10; // 10px padding from the edge
        }
        if (leftPosition < menuWidth) {
            leftPosition = menuWidth + 100; // 10px padding from the menu
        }
        if (realImgBottom + 10 > bottomViewable) {
            // the image won't fit in view
            topPosition = Math.max(
                mouseY - divHeight - headerHeight - 80,
                window.scrollY + 10
            ); // 80px above mouse, unless < 10px from header
        }

        console.log({ leftPosition: leftPosition, topPosition: topPosition }); // Log the final positions

        $div.css({ top: topPosition + 'px', left: leftPosition + 'px' }).show("slow");
    }

    // Default to non-touch hover solution
    setupNonTouchEvents();

    // Global touchstart listener to detect touch events on td.hoverTD
    window.addEventListener('touchstart', function(e) {
        if (touchDetected) return;

        if (!$(e.target).closest('td.hoverTD').length) {
            return;
        }

        touchDetected = true;

        // Remove existing non-touch event handlers
        removeNonTouchEvents();

        // Set up touch-specific events
        setupTouchEvents();
    }, { capture: true, passive: false }); // Use capture phase and set passive to false

    // Rebind mouse-specific event handlers when mouse movement is detected
    window.addEventListener('mousemove', function(e) {
        if (!touchDetected) return;

        touchDetected = false;

        // Remove touch-specific event handlers
        $('td.hoverTD').off('touchstart touchmove touchend');
        if (documentTouchHandler) {
            document.removeEventListener('touchstart', documentTouchHandler, true);
        }
        if (documentClickHandler) {
            document.removeEventListener('click', documentClickHandler, true);
        }
        if (windowScrollHandler) {
            window.removeEventListener('scroll', windowScrollHandler, { passive: true });
        }
        documentTouchHandler = null;
        documentClickHandler = null;
        windowScrollHandler = null;

        // Remove no-hover class on mouse events
        $('tr.deckrow').removeClass('no-hover');

        // Set up non-touch events again
        setupNonTouchEvents();
    });
};

window.bindRandomDrawStripInteractions = function() {
    var selector = '#deck-random-draw-fragment .random-draw-card';
    var $fragment = $('#deck-random-draw-fragment');
    var $document = $(document);
    var touchActiveClass = 'is-touch-active';
    var touchDeactivatingClass = 'is-touch-deactivating';
    var hoverOutClass = 'is-hover-out';
    var hoverSuppressClass = 'is-hover-suppressed';
    var touchDeactivateDelay = 250;
    var pendingActivateTimer = null;
    var pendingTouchCard = null;
    var touchSwitchTimer = null;
    var hoverSuppressTimer = null;
    var lastHoveredCard = null;
    var isTouchSwitching = function() {
        return !!pendingTouchCard || $(selector + '.' + touchDeactivatingClass).length > 0;
    };
    var clearTouchPreview = function(reason) {
        var $active = $(selector + '.' + touchActiveClass);
        if ($active.length) {
            $active.addClass(touchDeactivatingClass);
        }
        if (touchSwitchTimer) {
            clearTimeout(touchSwitchTimer);
            touchSwitchTimer = null;
        }
        pendingTouchCard = null;
        $(selector).removeClass(touchActiveClass).removeData('touch-ready');
        setTimeout(function() {
            $(selector).removeClass(touchDeactivatingClass);
        }, touchDeactivateDelay);
        console.debug('[DEBUG]', 'Random draw touch preview cleared.', reason);
    };
    var setTouchMode = function(isTouch) {
        if (!$fragment.length) {
            return;
        }
        $fragment.toggleClass('is-touch-mode', isTouch);
    };

    $document.off('touchstart.deckdetail', selector).on('touchstart.deckdetail', selector, function () {
        var $card = $(this);
        setTouchMode(true);
        var wasActive = $card.hasClass(touchActiveClass);
        var $otherActive = $(selector + '.' + touchActiveClass + ',' + selector + '.' + touchDeactivatingClass).not($card);
        if (pendingActivateTimer) {
            clearTimeout(pendingActivateTimer);
            pendingActivateTimer = null;
        }
        if (touchSwitchTimer) {
            clearTimeout(touchSwitchTimer);
            touchSwitchTimer = null;
        }
        $card.removeClass(touchDeactivatingClass);
        if ($otherActive.length) {
            $otherActive.addClass(touchDeactivatingClass);
            $otherActive.removeClass(touchActiveClass).removeData('touch-ready');
            pendingTouchCard = $card;
            touchSwitchTimer = setTimeout(function() {
                $(selector).removeClass(touchDeactivatingClass);
                if (!pendingTouchCard || !pendingTouchCard.length) {
                    return;
                }
                $(selector).not(pendingTouchCard).removeClass(touchActiveClass).removeData('touch-ready');
                pendingTouchCard.data('touch-ready', !wasActive);
                if (!wasActive) {
                    pendingTouchCard.addClass(touchActiveClass);
                }
                pendingTouchCard = null;
            }, touchDeactivateDelay);
            return;
        }
        $(selector).not($card).removeClass(touchActiveClass).removeClass(touchDeactivatingClass).removeData('touch-ready');
        $card.data('touch-ready', !wasActive);
        if (!wasActive) {
            $card.addClass(touchActiveClass);
        }
    });

    $document.off('click.deckdetail', selector).on('click.deckdetail', selector, function (e) {
        var $card = $(this);
        var isHoverCapable = !window.matchMedia
            || window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (isHoverCapable) {
            if (!$card.is(':hover') && !$card.is(':focus')) {
                console.debug('[DEBUG]', 'Random draw click blocked until hover.');
                e.preventDefault();
            }
            return;
        }

        if ($fragment.hasClass('is-touch-mode') && isTouchSwitching()) {
            console.debug('[DEBUG]', 'Random draw touch click blocked during switch.');
            e.preventDefault();
            return;
        }

        if ($card.data('touch-ready') === true) {
            console.debug('[DEBUG]', 'Random draw touch preview activated.');
            e.preventDefault();
            $card.data('touch-ready', false);
            return;
        }

        if (!$card.hasClass(touchActiveClass)) {
            console.debug('[DEBUG]', 'Random draw touch blocked until preview is active.');
            e.preventDefault();
            $(selector).removeClass(touchActiveClass).removeData('touch-ready');
            $card.addClass(touchActiveClass);
        }
    });

    $document.off('touchstart.deckdetail', '#deck-random-draw-fragment').on(
        'touchstart.deckdetail',
        '#deck-random-draw-fragment',
        function (e) {
            if ($(e.target).closest(selector).length) {
                return;
            }
            clearTouchPreview('fragment touch');
        }
    );

    document.removeEventListener('pointerdown', window.mtgRandomDrawPointerReset, true);
    document.removeEventListener('pointerdown', window.mtgRandomDrawMouseModeHandler, true);
    document.removeEventListener('touchstart', window.mtgRandomDrawTouchReset, true);
    document.removeEventListener('touchend', window.mtgRandomDrawTouchEndReset, true);
    document.removeEventListener('click', window.mtgRandomDrawClickReset, true);
    window.removeEventListener('scroll', window.mtgRandomDrawScrollReset, { passive: true });

    window.mtgRandomDrawPointerReset = function(e) {
        if ($(e.target).closest('#deck-random-draw-fragment').length) {
            return;
        }
        clearTouchPreview('document pointer');
    };

    window.mtgRandomDrawTouchReset = function(e) {
        if ($(e.target).closest('#deck-random-draw-fragment').length) {
            return;
        }
        clearTouchPreview('document touch');
    };

    window.mtgRandomDrawTouchEndReset = function(e) {
        if ($(e.target).closest('#deck-random-draw-fragment').length) {
            return;
        }
        clearTouchPreview('document touchend');
    };

    window.mtgRandomDrawClickReset = function(e) {
        if ($(e.target).closest('#deck-random-draw-fragment').length) {
            return;
        }
        clearTouchPreview('document click');
    };

    window.mtgRandomDrawScrollReset = function() {
        clearTouchPreview('scroll');
    };

    if (window.mtgRandomDrawTouchModeHandler) {
        document.removeEventListener('touchstart', window.mtgRandomDrawTouchModeHandler, true);
    }
    window.mtgRandomDrawTouchModeHandler = function() {
        setTouchMode(true);
    };

    document.addEventListener('pointerdown', window.mtgRandomDrawPointerReset, true);
    document.addEventListener('touchstart', window.mtgRandomDrawTouchReset, true);
    document.addEventListener('touchend', window.mtgRandomDrawTouchEndReset, true);
    document.addEventListener('click', window.mtgRandomDrawClickReset, true);
    window.addEventListener('scroll', window.mtgRandomDrawScrollReset, { passive: true });
    document.addEventListener('touchstart', window.mtgRandomDrawTouchModeHandler, true);
    window.mtgRandomDrawMouseModeHandler = function(e) {
        if (e && e.pointerType === 'mouse') {
            setTouchMode(false);
        }
    };
    document.addEventListener('pointerdown', window.mtgRandomDrawMouseModeHandler, true);

    var hoverCapable = !window.matchMedia
        || window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    if (!hoverCapable) {
        setTouchMode(true);
    }

    $document.off('pointerenter.deckdetailHoverOut pointerleave.deckdetailHoverOut', selector)
        .on('pointerenter.deckdetailHoverOut', selector, function(e) {
            if ($fragment.hasClass('is-touch-mode')) {
                return;
            }
            if (e.pointerType && e.pointerType !== 'mouse') {
                return;
            }
            var $card = $(this);
            if (lastHoveredCard && lastHoveredCard[0] !== $card[0]) {
                $fragment.addClass(hoverSuppressClass);
                if (hoverSuppressTimer) {
                    clearTimeout(hoverSuppressTimer);
                }
                hoverSuppressTimer = setTimeout(function() {
                    $fragment.removeClass(hoverSuppressClass);
                }, 350);
            }
            var timer = $card.data('hover-out-timer');
            if (timer) {
                clearTimeout(timer);
                $card.removeData('hover-out-timer');
            }
            $card.removeClass(hoverOutClass);
            lastHoveredCard = $card;
        })
        .on('pointerleave.deckdetailHoverOut', selector, function(e) {
            if ($fragment.hasClass('is-touch-mode')) {
                return;
            }
            if (e.pointerType && e.pointerType !== 'mouse') {
                return;
            }
            var $card = $(this);
            $card.addClass(hoverOutClass);
            var timer = setTimeout(function() {
                $card.removeClass(hoverOutClass);
                if (lastHoveredCard && lastHoveredCard[0] === $card[0]) {
                    lastHoveredCard = null;
                }
            }, 350);
            $card.data('hover-out-timer', timer);
        });
};

function applyFragmentResponse(response, options) {
    if (!response || response.success !== true || !response.fragments) {
        hardReloadDeckDetail();
        return;
    }
    var responseVersion = normalizeVersion(response.version || response.deck_version);
    if (responseVersion && responseVersion < lastAppliedVersion) {
        return;
    }
    var replaceDecklist = true;
    var refreshImages = true;
    var newCardIds = [];
    if (options) {
        replaceDecklist = options.replaceDecklist !== false;
        refreshImages = options.refreshImages !== false;
        if (Array.isArray(options.newCardIds)) {
            newCardIds = options.newCardIds;
        }
    }
    try {
        var targets = getFragmentTargets();
        if (replaceDecklist && response.fragments.decklist) {
            var decklistId = targets.decklist || 'decklist-fragment';
            $('#' + decklistId).replaceWith(response.fragments.decklist);
        }
        Object.keys(response.fragments).forEach(function (fragmentKey) {
            if (fragmentKey === 'decklist') {
                return;
            }
            var targetId = targets[fragmentKey];
            if (!targetId) {
                return;
            }
            $('#' + targetId).replaceWith(response.fragments[fragmentKey]);
        });
        applyDeckSectionState();
        updateRandomDrawPlacement();
        if (window.bindRandomCardEvents) {
            window.bindRandomCardEvents();
        }
        if (window.bindRandomDrawStripInteractions) {
            window.bindRandomDrawStripInteractions();
        }
        bindDeckDetailHandlers();
        if (refreshImages && window.refreshCardImagesAsync) {
            window.refreshCardImagesAsync();
        } else if (newCardIds.length && window.enqueueDeckImage) {
            newCardIds.forEach(function (cardId) {
                window.enqueueDeckImage(cardId, true);
            });
        }
        renderManaValueChart();
        updateDeckTotals();
        updateRandomDrawState();
        if (responseVersion) {
            updateDeckVersion(responseVersion);
        }
    } catch (e) {
        hardReloadDeckDetail();
    }
}

function showDeckMessage(text, isHtml) {
    if (!text) {
        return;
    }
    var $message = $('<div class="msg-new error-new"></div>');
    if (isHtml) {
        $message.append('<span>' + text + '</span><br>');
    } else {
        $message.append($('<span></span>').text(text)).append('<br>');
    }
    $message.append("<p onmouseover=\"\" style=\"cursor: pointer;\" id='dismiss'>OK</p>");
    $message.on('click', function () {
        closeMe(this);
    });
    $('body').append($message);
}

function closeMe(obj) {
    obj.style.display = 'none';
    if (deckNumber) {
        window.location.href = 'deckdetail.php?deck=' + deckNumber;
    }
}

function submitForm() {
    var notesTextarea = $('#notes');
    var sidenotesTextarea = $('#sidenotes');
    var saveButton = $('.save_icon');

    var notes = notesTextarea.val();
    var sidenotes = sidenotesTextarea.length ? sidenotesTextarea.val() : '';
    var deck = $('#updatenotesform').find('input[name="deck"]').val();

    $.ajax({
        url: 'ajax/ajaxdecknotes.php',
        type: 'POST',
        data: {
            newnotes: notes,
            newsidenotes: sidenotes,
            decknumber: deck
        },
        dataType: 'json',
        success: function (result) {
            if (result.success) {
                window.initialNotesValue = notesTextarea.val();
                window.initialSidenotesValue = sidenotesTextarea.val();
                saveButton.prop('disabled', true);
            } else {
                alert('Error updating notes: ' + result.error);
            }
        },
        error: function (xhr, status, error) {
            console.error('Error:', error);
            alert('An error occurred while updating the notes.');
        }
    });
}

function refreshTable() {
    if (!randomDrawEnabled) {
        return;
    }
    var xhr = new XMLHttpRequest();
    var data = JSON.stringify({
        uniquecard_ref: randomDrawRefs,
        include_check: true,
        csrf_token: csrfToken
    });
    xhr.open('POST', 'ajax/ajaxrandomdraw.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            document.getElementById('table-container').innerHTML = xhr.responseText;
            var $randomContent = $('#deck-random-draw-fragment .random-draw-content');
            if ($randomContent.length) {
                $randomContent.removeClass('is-visible');
                $randomContent[0].offsetWidth;
                setTimeout(function () {
                    $randomContent.addClass('is-visible');
                }, 10);
            }
            if (window.bindRandomCardEvents) {
                window.bindRandomCardEvents();
            }
            if (window.bindRandomDrawStripInteractions) {
                window.bindRandomDrawStripInteractions();
            }
            window.dispatchEvent(new Event('resize'));
        }
    };
    xhr.send(data);
}

window.closeMe = closeMe;
window.submitForm = submitForm;
window.refreshTable = refreshTable;

function bindDeckDetailHandlers() {
    var $importFile = $('#importfile');
    if ($importFile.length && $importFile.val() === '') {
        $('#importsubmit').prop('disabled', true);
    }
    var $photoFile = $('#importphoto');
    if ($photoFile.length && $photoFile.val() === '') {
        $('#photosubmit').prop('disabled', true);
    }
    applyDeckSectionState();
    $(document).off('click.deckdetail', '.js-decksection-toggle').on(
        'click.deckdetail',
        '.js-decksection-toggle',
        function () {
            var section = $(this).data('section');
            if (!section) {
                return;
            }
            var collapsed = deckSectionState[section] !== false;
            setDeckSectionCollapsed(section, !collapsed);
        }
    );

    $(document).off('click.deckdetail', '.js-decksection-toggle-all').on(
        'click.deckdetail',
        '.js-decksection-toggle-all',
        function () {
            var anyCollapsed = getDeckSectionKeys().some(function (section) {
                return deckSectionState[section] !== false;
            });
            getDeckSectionKeys().forEach(function (section) {
                setDeckSectionCollapsed(section, !anyCollapsed);
            });
        }
    );
    $(document).off('click.deckdetail', '.js-plusmain').on('click.deckdetail', '.js-plusmain', function (e) {
        e.preventDefault();
        var $button = $(this);
        if ($button.data('busy')) {
            return;
        }
        $button.data('busy', true);
        var cardId = $button.data('cardid');
        var $row = $button.closest('tr.deckrow');
        $.ajax({
            url: 'ajax/ajaxdeckcard.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'plusmain',
                decknumber: deckNumber,
                cardid: cardId,
                csrf_token: csrfToken,
                fragments: getFragmentList()
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            updateDeckVersion(response.deck_version);
            var $qtyCell = $row.find('.js-qty-main');
            if ($qtyCell.length && response.cardqty !== undefined) {
                $qtyCell.text(response.cardqty);
            }
            if (response.cardqty !== undefined) {
                $row.data('qty', response.cardqty);
            }
            var maxCopies = parseInt($button.data('maxcopies'), 10);
            if (!isNaN(maxCopies)) {
                var totalCopies = (response.cardqty || 0) + (response.sideqty || 0);
                if (totalCopies >= maxCopies) {
                    $button.hide();
                } else {
                    $button.show();
                }
            }
            if (response.status === 'limitreached') {
                alert('Deck already contains the limit for this card name');
                $button.hide();
            } else if (response.status && response.status.indexOf('limitpartial:') === 0) {
                var limitedQty = response.status.split(':')[1];
                alert(limitedQty + ' imported due to card name limit');
            }
            updateDeckTotals();
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $(document).off('click.deckdetail', '.js-minusmain').on('click.deckdetail', '.js-minusmain', function (e) {
        e.preventDefault();
        var $button = $(this);
        if ($button.data('busy')) {
            return;
        }
        $button.data('busy', true);
        var cardId = $button.data('cardid');
        var cardRef = $button.data('cardref');
        var $row = $button.closest('tr.deckrow');
        $.ajax({
            url: 'ajax/ajaxdeckcard.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'minusmain',
                decknumber: deckNumber,
                cardid: cardId,
                csrf_token: csrfToken,
                fragments: getFragmentList()
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            updateDeckVersion(response.deck_version);
            if (response.cardqty <= 0) {
                $row.remove();
                if (cardRef) {
                    $('#list-' + cardRef).remove();
                }
                updateDeckTotals();
                applyFragmentResponse(response);
                return;
            }
            var $qtyCell = $row.find('.js-qty-main');
            if ($qtyCell.length && response.cardqty !== undefined) {
                $qtyCell.text(response.cardqty);
            }
            if (response.cardqty !== undefined) {
                $row.data('qty', response.cardqty);
            }
            var $plusButton = $row.find('.js-plusmain');
            var maxCopies = parseInt($plusButton.data('maxcopies'), 10);
            if (!isNaN(maxCopies)) {
                var totalCopies = (response.cardqty || 0) + (response.sideqty || 0);
                if (totalCopies >= maxCopies) {
                    $plusButton.hide();
                } else {
                    $plusButton.show();
                }
            } else {
                $plusButton.show();
            }
            updateDeckTotals();
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $(document).off('click.deckdetail', '.js-deletemain').on('click.deckdetail', '.js-deletemain', function (e) {
        e.preventDefault();
        var $button = $(this);
        if ($button.data('busy')) {
            return;
        }
        $button.data('busy', true);
        var cardId = $button.data('cardid');
        var cardRef = $button.data('cardref');
        $.ajax({
            url: 'ajax/ajaxdeckcard.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'deletemain',
                decknumber: deckNumber,
                cardid: cardId,
                csrf_token: csrfToken,
                fragments: getFragmentList()
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            updateDeckVersion(response.deck_version);
            var $row = $button.closest('tr.deckrow');
            var $qtyCell = $row.find('.js-qty-main');
            if (response.cardqty <= 0) {
                $row.remove();
                if (cardRef) {
                    $('#list-' + cardRef).remove();
                }
            } else if ($qtyCell.length && response.cardqty !== undefined) {
                $qtyCell.text(response.cardqty);
                $row.data('qty', response.cardqty);
                var $plusButton = $row.find('.js-plusmain');
                var maxCopies = parseInt($plusButton.data('maxcopies'), 10);
                if (!isNaN(maxCopies)) {
                    var totalCopies = (response.cardqty || 0) + (response.sideqty || 0);
                    if (totalCopies >= maxCopies) {
                        $plusButton.hide();
                    } else {
                        $plusButton.show();
                    }
                }
            }
            if (cardRef) {
                $('#list-' + cardRef).remove();
            }
            if (response.side_row_html) {
                ensureSideboardSection();
                var $existingSide = $('tr.deckrow[data-section="sideboard"][data-cardid="' + cardId + '"]');
                if ($existingSide.length) {
                    var $sideQty = $existingSide.find('.js-qty-side');
                    if ($sideQty.length && response.sideqty !== undefined) {
                        $sideQty.text(response.sideqty);
                    }
                    if (response.sideqty !== undefined) {
                        $existingSide.data('qty', response.sideqty);
                    }
                } else {
                    var $insertBefore = $('#sideboard-total-row');
                    if ($insertBefore.length) {
                        $insertBefore.before(response.side_row_html);
                    } else {
                        $('#sideboard-start').after(response.side_row_html);
                    }
                }
                if (response.side_hover_html) {
                    if (response.cardref) {
                        $('#listside-' + response.cardref).remove();
                    }
                    var $table = $('table.deckcardlist').first();
                    if ($table.length) {
                        $table.after(response.side_hover_html);
                    } else {
                        $('body').append(response.side_hover_html);
                    }
                }
                if (window.bindRandomCardEvents) {
                    window.bindRandomCardEvents();
                }
            }
            updateDeckTotals();
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $(document).off('click.deckdetail', '.js-maintoside').on('click.deckdetail', '.js-maintoside', function (e) {
        e.preventDefault();
        var $button = $(this);
        if ($button.data('busy')) {
            return;
        }
        $button.data('busy', true);
        var cardId = $button.data('cardid');
        var cardRef = $button.data('cardref');
        $.ajax({
            url: 'ajax/ajaxdeckcard.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'maintoside',
                decknumber: deckNumber,
                cardid: cardId,
                csrf_token: csrfToken,
                fragments: getFragmentList()
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            updateDeckVersion(response.deck_version);
            var $row = $button.closest('tr.deckrow');
            var $qtyCell = $row.find('.js-qty-main');
            if (response.cardqty <= 0) {
                $row.remove();
                if (cardRef) {
                    $('#list-' + cardRef).remove();
                }
            } else if ($qtyCell.length && response.cardqty !== undefined) {
                $qtyCell.text(response.cardqty);
                $row.data('qty', response.cardqty);
                var $plusButton = $row.find('.js-plusmain');
                var maxCopies = parseInt($plusButton.data('maxcopies'), 10);
                if (!isNaN(maxCopies)) {
                    var totalCopies = (response.cardqty || 0) + (response.sideqty || 0);
                    if (totalCopies >= maxCopies) {
                        $plusButton.hide();
                    } else {
                        $plusButton.show();
                    }
                }
            }
            if (response.side_row_html) {
                ensureSideboardSection();
                var $existingSide = $('tr.deckrow[data-section="sideboard"][data-cardid="' + cardId + '"]');
                if ($existingSide.length) {
                    var $sideQty = $existingSide.find('.js-qty-side');
                    if ($sideQty.length && response.sideqty !== undefined) {
                        $sideQty.text(response.sideqty);
                    }
                    if (response.sideqty !== undefined) {
                        $existingSide.data('qty', response.sideqty);
                    }
                } else {
                    var $insertBefore = $('#sideboard-total-row');
                    if ($insertBefore.length) {
                        $insertBefore.before(response.side_row_html);
                    } else {
                        $('#sideboard-start').after(response.side_row_html);
                    }
                }
                if (response.side_hover_html) {
                    if (response.cardref) {
                        $('#listside-' + response.cardref).remove();
                    }
                    var $table = $('table.deckcardlist').first();
                    if ($table.length) {
                        $table.after(response.side_hover_html);
                    } else {
                        $('body').append(response.side_hover_html);
                    }
                }
                if (window.bindRandomCardEvents) {
                    window.bindRandomCardEvents();
                }
            }
            updateDeckTotals();
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $(document).off('click.deckdetail', '.js-plusside').on('click.deckdetail', '.js-plusside', function (e) {
        e.preventDefault();
        var $button = $(this);
        if ($button.data('busy')) {
            return;
        }
        $button.data('busy', true);
        var cardId = $button.data('cardid');
        var cardRef = $button.data('cardref');
        var $row = $button.closest('tr.deckrow');

        $.ajax({
            url: 'ajax/ajaxdeckcard.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'plusside',
                decknumber: deckNumber,
                cardid: cardId,
                csrf_token: csrfToken,
                fragments: getFragmentList()
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            updateDeckVersion(response.deck_version);
            var $qtyCell = $row.find('.js-qty-side');
            if ($qtyCell.length && response.sideqty !== undefined) {
                $qtyCell.text(response.sideqty);
            }
            if (response.sideqty !== undefined) {
                $row.data('qty', response.sideqty);
            }
            var maxCopies = parseInt($button.data('maxcopies'), 10);
            if (!isNaN(maxCopies)) {
                var totalCopies = (response.cardqty || 0) + (response.sideqty || 0);
                if (totalCopies >= maxCopies) {
                    $button.hide();
                } else {
                    $button.show();
                }
            }
            if (response.status === 'limitreached') {
                alert('Deck already contains the limit for this card name');
                $button.hide();
            } else if (response.status && response.status.indexOf('limitpartial:') === 0) {
                var limitedQty = response.status.split(':')[1];
                alert(limitedQty + ' imported due to card name limit');
            }
            updateDeckTotals();
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $(document).off('click.deckdetail', '.js-minusside').on('click.deckdetail', '.js-minusside', function (e) {
        e.preventDefault();
        var $button = $(this);
        if ($button.data('busy')) {
            return;
        }
        $button.data('busy', true);
        var cardId = $button.data('cardid');
        var cardRef = $button.data('cardref');
        var $row = $button.closest('tr.deckrow');

        $.ajax({
            url: 'ajax/ajaxdeckcard.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'minusside',
                decknumber: deckNumber,
                cardid: cardId,
                csrf_token: csrfToken,
                fragments: getFragmentList()
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            updateDeckVersion(response.deck_version);
            if (response.sideqty <= 0) {
                $row.remove();
                if (cardRef) {
                    $('#listside-' + cardRef).remove();
                }
            } else {
                var $qtyCell = $row.find('.js-qty-side');
                if ($qtyCell.length && response.sideqty !== undefined) {
                    $qtyCell.text(response.sideqty);
                }
                if (response.sideqty !== undefined) {
                    $row.data('qty', response.sideqty);
                }
                var $plusButton = $row.find('.js-plusside');
                var maxCopies = parseInt($plusButton.data('maxcopies'), 10);
                if (!isNaN(maxCopies)) {
                    var totalCopies = (response.cardqty || 0) + (response.sideqty || 0);
                    if (totalCopies >= maxCopies) {
                        $plusButton.hide();
                    } else {
                        $plusButton.show();
                    }
                } else {
                    $plusButton.show();
                }
            }
            updateDeckTotals();
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $(document).off('click.deckdetail', '.js-deleteside').on('click.deckdetail', '.js-deleteside', function (e) {
        e.preventDefault();
        var $button = $(this);
        if ($button.data('busy')) {
            return;
        }
        $button.data('busy', true);
        var cardId = $button.data('cardid');
        var cardRef = $button.data('cardref');
        var $row = $button.closest('tr.deckrow');

        $.ajax({
            url: 'ajax/ajaxdeckcard.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'deleteside',
                decknumber: deckNumber,
                cardid: cardId,
                csrf_token: csrfToken,
                fragments: getFragmentList()
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            updateDeckVersion(response.deck_version);
            $row.remove();
            if (cardRef) {
                $('#listside-' + cardRef).remove();
            }
            updateDeckTotals();
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $(document).off('click.deckdetail', '.js-sidetomain').on('click.deckdetail', '.js-sidetomain', function (e) {
        e.preventDefault();
        var $button = $(this);
        if ($button.data('busy')) {
            return;
        }
        $button.data('busy', true);
        var cardId = $button.data('cardid');
        var cardRef = $button.data('cardref');
        var $row = $button.closest('tr.deckrow');

        $.ajax({
            url: 'ajax/ajaxdeckcard.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'sidetomain',
                decknumber: deckNumber,
                cardid: cardId,
                csrf_token: csrfToken,
                fragments: getFragmentList()
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            updateDeckVersion(response.deck_version);
            if (response.sideqty <= 0) {
                $row.remove();
                if (cardRef) {
                    $('#listside-' + cardRef).remove();
                }
            } else {
                var $qtyCell = $row.find('.js-qty-side');
                if ($qtyCell.length && response.sideqty !== undefined) {
                    $qtyCell.text(response.sideqty);
                }
                if (response.sideqty !== undefined) {
                    $row.data('qty', response.sideqty);
                }
            }
            updateDeckTotals();
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $(document)
        .off('click.deckdetail', '.js-commander-add, .js-partner-add, .js-commander-remove')
        .on('click.deckdetail', '.js-commander-add, .js-partner-add, .js-commander-remove', function (e) {
        e.preventDefault();
        var $button = $(this);
        if ($button.data('busy')) {
            return;
        }
        $button.data('busy', true);
        var cardId = $button.data('cardid');
        var action = 'commander_add';
        if ($button.hasClass('js-partner-add')) {
            action = 'partner_add';
        } else if ($button.hasClass('js-commander-remove')) {
            action = 'commander_remove';
        }

        $.ajax({
            url: 'ajax/ajaxdeckcard.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: action,
                decknumber: deckNumber,
                cardid: cardId,
                csrf_token: csrfToken,
                fragments: getFragmentList()
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            updateDeckVersion(response.deck_version);
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $(document).off('click.deckdetail', '#random-draw-button').on('click.deckdetail', '#random-draw-button', function (e) {
        if (!randomDrawEnabled) {
            return;
        }
        refreshTable(e);
    });

    $(document)
        .off('change.deckdetail', '#changeType select[name="updatetype"]')
        .on('change.deckdetail', '#changeType select[name="updatetype"]', function (event) {
            event.preventDefault();
            var $select = $(this);
            var newType = $select.val();
            if (!newType || !deckNumber) {
                return;
            }
            $select.prop('disabled', true);
            $.ajax({
                url: 'ajax/ajaxdecktype.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    decknumber: deckNumber,
                    updatetype: newType,
                    csrf_token: csrfToken,
                    fragments: getFragmentList()
                }
            }).done(function (response) {
                if (!response || response.success !== true) {
                    alert('That did not work. Please try again.');
                    return;
                }
                updateDeckVersion(response.deck_version);
                isCommanderDeck = response.is_commander === true;
                if (window.mtgDeckDetailConfig) {
                    window.mtgDeckDetailConfig.isCommanderDeck = isCommanderDeck;
                }
                var selectedText = $select.find('option:selected').text();
                var $currentType = $('#currentType');
                $currentType.empty();
                $('<span></span>')
                    .css('font-weight', '500')
                    .text(selectedText)
                    .appendTo($currentType);
                $currentType.append('<br>');
                $("#changeType").hide();
                $("#currentType").show();
                applyFragmentResponse(response);
            }).fail(function () {
                alert('That did not work. Please try again.');
            }).always(function () {
                $select.prop('disabled', false);
            });
        });

    $(document).off('submit.deckdetail', '#quickadd-form').on('submit.deckdetail', '#quickadd-form', function (event) {
        event.preventDefault();
        var $form = $(this);
        var quickadd = $('#quickadd-text').val();
        if (!quickadd || quickadd.trim() === '') {
            alert('Add cards field cannot be empty');
            return;
        }
        if ($form.data('busy')) {
            return;
        }
        $form.data('busy', true);
        $.ajax({
            url: 'ajax/ajaxdeckadd.php',
            method: 'POST',
            dataType: 'json',
            data: {
                decknumber: deckNumber,
                quickadd: quickadd,
                csrf_token: csrfToken,
                fragments: getFragmentList()
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            updateDeckVersion(response.deck_version);
            if (response.status === 'cardnotfound' || response.status === 'cardnotadded') {
                showDeckMessage("That didn't work... check card name");
            } else if (response.status === 'limitreached') {
                showDeckMessage('Deck already contains the limit for this card name');
            } else if (response.status && response.status.indexOf('limitpartial:') === 0) {
                var limitQty = parseInt(response.status.split(':')[1], 10) || 0;
                showDeckMessage(limitQty + ' imported due to card name limit');
            } else if (response.status === 'multierror') {
                showDeckMessage('Multi input errors<br>&nbsp;Details sent by email', true);
            }
            $('#quickadd-text').val('');
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $form.data('busy', false);
        });
    });

    $(document).off('change.deckdetail', '#importfile').on('change.deckdetail', '#importfile', function () {
        var hasFile = $(this).val() !== '';
        $('#importsubmit').prop('disabled', !hasFile);
    });

    $(document).off('change.deckdetail', '#importphoto').on('change.deckdetail', '#importphoto', function () {
        var hasFile = $(this).val() !== '';
        $('#photosubmit').prop('disabled', !hasFile);
    });

    $(document).off('submit.deckdetail', '#import-form').on('submit.deckdetail', '#import-form', function (event) {
        event.preventDefault();
        var $form = $(this);
        var fileInput = $('#importfile')[0];
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            alert('Please select a file to import');
            return;
        }
        if ($form.data('busy')) {
            return;
        }
        $form.data('busy', true);
        document.body.style.cursor = 'wait';
        var formData = new FormData($form[0]);
        formData.append('decknumber', deckNumber);
        formData.append('csrf_token', csrfToken);
        var fragments = getFragmentList();
        fragments.forEach(function (fragmentKey) {
            formData.append('fragments[]', fragmentKey);
        });
        $.ajax({
            url: 'ajax/ajaxdeckimport.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            updateDeckVersion(response.deck_version);
            if (response.status === 'cardnotfound' || response.status === 'cardnotadded') {
                showDeckMessage("That didn't work... check card name");
            } else if (response.status === 'limitreached') {
                showDeckMessage('Deck already contains the limit for this card name');
            } else if (response.status && response.status.indexOf('limitpartial:') === 0) {
                var limitQty = parseInt(response.status.split(':')[1], 10) || 0;
                showDeckMessage(limitQty + ' imported due to card name limit');
            } else if (response.status === 'multierror') {
                showDeckMessage('Multi input errors<br>&nbsp;Details sent by email', true);
            }
            $('#importfile').val('');
            $('#importsubmit').prop('disabled', true);
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $form.data('busy', false);
            document.body.style.cursor = '';
        });
    });

    $(document).off('submit.deckdetail', '#renameForm').on('submit.deckdetail', '#renameForm', function (event) {
        event.preventDefault();
        var $form = $(this);
        var fieldValue = $('#newname').val();
        if (!fieldValue || fieldValue.trim() === '') {
            alert('Rename field cannot be empty');
            return;
        }
        if (deckName && fieldValue.trim() === deckName.trim()) {
            alert('To cancel rename click edit button again');
            return;
        }
        if ($form.data('busy')) {
            return;
        }
        $form.data('busy', true);
        $.ajax({
            url: 'ajax/ajaxdeckrename.php',
            method: 'POST',
            dataType: 'json',
            data: {
                decknumber: deckNumber,
                newname: fieldValue,
                csrf_token: csrfToken,
                fragments: ['export_list']
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                if (response && response.status === 'nameexists') {
                    showDeckMessage('Deck name exists already');
                } else {
                    showDeckMessage('Unknown error');
                }
                return;
            }
            updateDeckVersion(response.deck_version);
            if (response.deckname_html) {
                $('#deckname').html(response.deckname_html);
            } else if (response.deckname) {
                $('#deckname').text(response.deckname);
            }
            if (response.deckname) {
                deckName = response.deckname;
                if (window.mtgDeckDetailConfig) {
                    window.mtgDeckDetailConfig.deckName = response.deckname;
                }
            }
            $("#renameForm, #changeType, #currentType").toggle("block");
            applyFragmentResponse(response);
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $form.data('busy', false);
        });
    });

    $(document).off('submit.deckdetail', '#uploadForm').on('submit.deckdetail', '#uploadForm', function (event) {
        event.preventDefault();
        var $form = $(this);
        if ($form.data('busy')) {
            return;
        }
        $form.data('busy', true);
        var formData = new FormData($form[0]);
        formData.append('decknumber', deckNumber);
        formData.append('update', '');
        formData.append('csrf_token', csrfToken);
        $.ajax({
            url: '/ajax/ajaxphoto.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            timeout: 5000
        }).done(function (response) {
            if (response && response.success) {
                $('#result').html(response.message);
                var imageUrl = 'deckimage.php?deck=' + deckNumber;
                var timestamp = new Date().getTime();
                $('#deckPhoto').attr('src', imageUrl + '&' + timestamp);
                $('#photo_div').show();
                $('#deletePhotoBtn').show();
                $('#photosubmit').prop('disabled', true);
                setTimeout(function () {
                    $('#result').html('');
                }, 5000);
            } else {
                $('#result').html('Error: ' + (response ? response.message : 'Unknown error'));
                setTimeout(function () {
                    $('#result').html('');
                }, 20000);
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $('#result').html('Error: ' + textStatus + ' - ' + errorThrown);
            setTimeout(function () {
                $('#result').html('');
            }, 20000);
        }).always(function () {
            $form.data('busy', false);
        });
    });

    $(document).off('click.deckdetail', '#deletePhotoBtn').on('click.deckdetail', '#deletePhotoBtn', function () {
        if ($(this).data('busy')) {
            return;
        }
        $(this).data('busy', true);
        var formData = new FormData();
        formData.append('decknumber', deckNumber);
        formData.append('delete', '');
        formData.append('csrf_token', csrfToken);
        $.ajax({
            url: '/ajax/ajaxphoto.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            timeout: 5000
        }).done(function (response) {
            if (response && response.success) {
                $('#result').html(response.message);
                $('#photo_div').hide();
                $('#deletePhotoBtn').hide();
                setTimeout(function () {
                    $('#result').html('');
                }, 5000);
            } else {
                $('#result').html('Error: ' + (response ? response.message : 'Unknown error'));
                setTimeout(function () {
                    $('#result').html('');
                }, 20000);
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $('#result').html('Error: ' + textStatus + ' - ' + errorThrown);
            setTimeout(function () {
                $('#result').html('');
            }, 20000);
        }).always(function () {
            $('#deletePhotoBtn').data('busy', false);
        });
    });
}

function ensureSideboardSection() {
    if ($('#sideboard-start').length) {
        return;
    }
    var $table = $('table.deckcardlist').first();
    if (!$table.length) {
        return;
    }
    var headerColspan = isCommanderDeck ? 4 : 6;
    var totalColspan = isCommanderDeck ? 2 : 4;
    var headerHtml = "<tr style=\"border-top: 1pt solid black;\" id=\"sideboard-start\">"
        + "<td colspan=\"" + headerColspan + "\"><i><b>Sideboard</b></i></td>"
        + "</tr>";
    var totalHtml = "<tr style=\"border-bottom: 1pt solid black; border-top: 1pt solid black;\" "
        + "id=\"sideboard-total-row\">"
        + "<td colspan=\"" + totalColspan + "\"><i><b>Total sideboard</b></i></td>"
        + "<td colspan=\"1\" class='deckcardlistcenter'><i><b><span id='total-sideboard'>0</span></b></i></td>"
        + "<td colspan=\"1\">&nbsp;</td>"
        + "</tr>";
    $table.append(headerHtml);
    $table.append(totalHtml);
}

    function updateDeckTotals() {
        var sections = ['creatures', 'instantsorcery', 'other', 'lands', 'planes'];
        var mainTotal = 0;
        sections.forEach(function (section) {
            var total = 0;
        var $rows = $('tr.deckrow[data-section="' + section + '"]');
        var $qtyCells = $rows.find('.js-qty-main');
        if ($qtyCells.length) {
            $qtyCells.each(function () {
                var qty = parseInt($(this).text(), 10);
                if (!isNaN(qty)) {
                    total += qty;
                }
            });
        } else {
            $rows.each(function () {
                var qty = parseInt($(this).data('qty'), 10);
                if (!isNaN(qty)) {
                    total += qty;
                }
            });
        }
        var $target = $('#total-' + section);
        if ($target.length) {
            $target.text(total);
        }
        mainTotal += total;
    });
    var $mainRows = $('tr.deckrow').not('[data-section=\"sideboard\"]');
    var $mainQtyCells = $mainRows.find('.js-qty-main');
    if ($mainQtyCells.length) {
        mainTotal = 0;
        $mainQtyCells.each(function () {
            var qty = parseInt($(this).text(), 10);
            if (!isNaN(qty)) {
                mainTotal += qty;
            }
        });
    } else {
        mainTotal = 0;
        $mainRows.each(function () {
            var qty = parseInt($(this).data('qty'), 10);
            if (!isNaN(qty)) {
                mainTotal += qty;
            }
        });
    }
    var $mainTotal = $('#total-main');
    if ($mainTotal.length) {
        $mainTotal.text(mainTotal);
    }
    var sideTotal = 0;
    var $sideRows = $('tr.deckrow[data-section="sideboard"]');
    var $sideQtyCells = $sideRows.find('.js-qty-side');
    if ($sideQtyCells.length) {
        $sideQtyCells.each(function () {
            var qty = parseInt($(this).text(), 10);
            if (!isNaN(qty)) {
                sideTotal += qty;
            }
        });
    } else {
        $sideRows.each(function () {
            var qty = parseInt($(this).data('qty'), 10);
            if (!isNaN(qty)) {
                sideTotal += qty;
            }
        });
    }
    var $sideTotal = $('#total-sideboard');
        if ($sideTotal.length) {
            $sideTotal.text(sideTotal);
        }
    }

    function renderManaValueChart() {
        var $fragment = $('#deck-mana-value-fragment');
        if (!$fragment.length) {
            return;
        }
        if ($fragment.attr('data-show-chart') !== '1') {
            return;
        }
        var countsRaw = $fragment.attr('data-cmc-counts') || '[]';
        var cmcCounts = [];
        try {
            cmcCounts = JSON.parse(countsRaw);
        } catch (e) {
            cmcCounts = [];
        }
        while (cmcCounts.length < 7) {
            cmcCounts.push(0);
        }

        function drawChart() {
            if (!window.google || !google.visualization || !google.charts || !google.charts.Bar) {
                return;
            }
            var data = google.visualization.arrayToDataTable([
                ['', 'Qty'],
                ['0', cmcCounts[0]],
                ['1', cmcCounts[1]],
                ['2', cmcCounts[2]],
                ['3', cmcCounts[3]],
                ['4', cmcCounts[4]],
                ['5', cmcCounts[5]],
                ['6+', cmcCounts[6]]
            ]);

            var options = {
                bars: 'vertical',
                axisTitlesPosition: 'none',
                backgroundColor: {
                    fill: '#e8eaf6'
                },
                chartArea: {
                    left: 0,
                    top: 0,
                    backgroundColor: '#e8eaf6'
                },
                legend: {
                    position: 'none'
                },
                hAxis: {
                    textPosition: 'none'
                },
                vAxis: {
                    minValue: '0'
                }
            };
            var chartContainer = document.getElementById('barchart_material');
            if (chartContainer) {
                var chart = new google.charts.Bar(chartContainer);
                chart.draw(data, google.charts.Bar.convertOptions(options));
            }
        }

        if (window.google && google.charts && google.charts.load) {
            google.charts.load('current', {'packages': ['bar']});
            google.charts.setOnLoadCallback(drawChart);
        }
    }

function refreshDeckFragments(options) {
        // Fragment dependencies are documented in docs/deckdetail_fragments.md.
        if (!deckNumber) {
            return;
        }
        var refreshImages = true;
        var newCardIds = [];
        var requestedFragments = null;
        var replaceDecklist = true;
        if (options) {
            refreshImages = options.refreshImages !== false;
            if (Array.isArray(options.newCardIds)) {
                newCardIds = options.newCardIds;
            }
            if (Array.isArray(options.fragments)) {
                requestedFragments = options.fragments;
                replaceDecklist = requestedFragments.indexOf('decklist') !== -1;
            }
        }
        var fragments = requestedFragments || getFragmentList();
        $.ajax({
            url: 'ajax/ajaxdeckfragments.php',
            method: 'POST',
            dataType: 'json',
            data: {
                decknumber: deckNumber,
                fragments: fragments,
                expected_version: lastAppliedVersion,
                csrf_token: csrfToken
            }
        }).done(function (response) {
            applyFragmentResponse(response, {
                replaceDecklist: replaceDecklist,
                refreshImages: refreshImages,
                newCardIds: newCardIds
            });
        });
    }

    function showDeckdetailWidthBanner() {
        var banner = document.getElementById('deckdetail-width-banner');
        if (!banner) {
            return;
        }
        var width = document.documentElement.clientWidth || window.innerWidth;
        var layout = document.getElementById('deckdetail-layout');
        var columns = 'unknown';
        if (layout && window.getComputedStyle) {
            columns = window.getComputedStyle(layout).gridTemplateColumns || 'unknown';
        }
        banner.textContent = 'Viewable width: ' + width + 'px';
    }

    window.showDeckdetailWidthBanner = showDeckdetailWidthBanner;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showDeckdetailWidthBanner);
    } else {
        showDeckdetailWidthBanner();
    }
    window.addEventListener('load', showDeckdetailWidthBanner);
    document.addEventListener('touchstart', showDeckdetailWidthBanner, { once: true, passive: true });
    document.addEventListener('touchend', showDeckdetailWidthBanner, { once: true, passive: true });
    document.addEventListener('pointerdown', showDeckdetailWidthBanner, { once: true });
    document.addEventListener('click', showDeckdetailWidthBanner, { once: true });

    $(document).ready(function () {
        bindDeckDetailHandlers();
        renderManaValueChart();
        updateRandomDrawState();
        $('#deck-random-draw-fragment .random-draw-content').addClass('is-visible');
        refreshCardImagesAsync();
        preloadFirstDeckImage();
        updateRandomDrawPlacement();
        bindDecksideHeroRotation();
        // Update the 'onerror' attribute for all images
        $('img').on('error', function() {
            this.src = '/images/back.jpg';
        });
        $(document).on('pointerdown keydown', function() {
            deckImagePauseUntil = Date.now() + 300;
        });

        // Click event for the document (outside .deckcardimgdiv and .randomcardimgdiv)
        $(document).on('click', function () {
            $('.deckcardimgdiv').hide("slow");
            $('.randomcardimgdiv').hide("slow");
        });

        $('#menubuttondiv, .searchicon').on('click', function () {
            $('.deckcardimgdiv').hide("slow");
            $('.randomcardimgdiv').hide("slow");
        });

        // Scroll event for the window
        $(window).on('scroll', function () {
            $('.deckcardimgdiv').hide("slow");
            $('.randomcardimgdiv').hide("slow");
        });

        $(window).on('resize', function () {
            updateRandomDrawPlacement();
            maybeLoadDecksideHeroOnResize();
        });

        window.bindRandomCardEvents();
        window.bindRandomDrawStripInteractions();
        var notesTextarea = $('#notes');
        var sidenotesTextarea = $('#sidenotes');
        var saveButton = $('.save_icon');

        window.initialNotesValue = notesTextarea.val();
        window.initialSidenotesValue = sidenotesTextarea.val();

        function checkChanges() {
            if (
                notesTextarea.val() !== window.initialNotesValue
                || (sidenotesTextarea.length && sidenotesTextarea.val() !== window.initialSidenotesValue)
            ) {
                saveButton.prop('disabled', false);
            } else {
                saveButton.prop('disabled', true);
            }
        }

        notesTextarea.on('input', checkChanges);
        if (sidenotesTextarea.length) {
            sidenotesTextarea.on('input', checkChanges);
        }

    });

window.toggleForm = function () {
    $("#renameForm, #changeType, #currentType").toggle("block");
};

window.ComparePrep = function () {
    $('body').css('cursor', 'wait');
};

window.duplicateDeck = function (user, deckname, decknumber, decktype) {
    var formData = new FormData();
    formData.append('user', user);
    formData.append('deckname', deckname);
    formData.append('decknumber', decknumber);
    formData.append('decktype', decktype);

    fetch('ajax/ajaxduplicatedeck.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.decknumber) {
                alert('Deck duplicated successfully!');
                window.location.href = 'deckdetail.php?deck=' + data.decknumber;
            } else {
                alert('Deck duplicated successfully, but no deck number returned.');
                window.location.href = 'decks.php';
            }
        } else {
            if (data.error === 'User not logged in') {
                window.location.href = '/login.php';
            } else {
                alert('Error duplicating deck: ' + data.error);
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('There was an issue duplicating the deck.');
    });
};
