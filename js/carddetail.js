/*
Version:     1.7
Date:        23/02/26
Name:        carddetail.js
Purpose:     Card detail page JS handlers.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

(function () {
    'use strict';

    var cardDetailConfig = window.mtgCardDetailConfig || {};
    var ajaxConfig = window.mtgAjaxConfig || {};
    var csrfToken = ajaxConfig.csrfToken || '';
    var cardIdForRedirect = cardDetailConfig.cardId || '';

    function updateFlipButtonsForViewport() {
        var showFlip = window.matchMedia('(max-width: 1208px)').matches;
        $('.flipbuttondetail').each(function () {
            var $btn = $(this);
            if (showFlip && $btn.data('ready') === 1) {
                $btn.show();
            } else {
                $btn.hide();
            }
        });
    }

    function refreshCardImagesAsync() {
        if (!window.mtgRefreshCardImagesAsync) {
            return;
        }
        window.mtgRefreshCardImagesAsync({
            selector: 'img[data-cardid]',
            useFaces: true,
            onFlipReady: function (cardId) {
                $('.flipbuttondetail[data-cardid="' + cardId + '"]')
                    .data('ready', 1);
                updateFlipButtonsForViewport();
            }
        });
    }

    function updateUrlWithTimestamp(url) {
        var timestamp = new Date().getTime();
        if (url.indexOf('?') !== -1) {
            return url.replace(/(\&|\?)timestamp=\d*/, '') + '&timestamp=' + timestamp;
        }
        return url + '?timestamp=' + timestamp;
    }

    function refreshImage() {
        var cardId = $('#refreshid').val();
        if (!cardId) {
            return;
        }

        $.ajax({
            url: 'ajax/ajaxcardrefreshimg.php',
            type: 'POST',
            data: { cardid: cardId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (!response || !response.success) {
                    alert('Error refreshing image: ' + (response ? response.message : 'Unknown error'));
                    return;
                }

                var frontSrc = response.front || $('.mainimg').attr('src') || '';
                var backSrc = response.back || $('.backimg').attr('src') || '';
                var swapper = window.mtgSwapImageWithFade || null;

                if ($('.mainimg').length && frontSrc) {
                    var newSrcMain = updateUrlWithTimestamp(frontSrc);
                    if (swapper) {
                        $('.mainimg').each(function () {
                            $(this).attr('data-front-src', frontSrc);
                            swapper($(this), newSrcMain, true);
                        });
                    } else {
                        $('.mainimg').fadeOut(100, function () {
                            $(this).attr('src', newSrcMain).fadeIn(100);
                        });
                    }
                }

                if ($('.backimg').length && backSrc) {
                    var newSrcBack = updateUrlWithTimestamp(backSrc);
                    if (swapper) {
                        $('.backimg').each(function () {
                            $(this).attr('data-back-src', backSrc);
                            swapper($(this), newSrcBack, true);
                        });
                    } else {
                        $('.backimg').fadeOut(100, function () {
                            $(this).attr('src', newSrcBack).fadeIn(100);
                        });
                    }
                }
            },
            error: function () {
                alert('An error occurred while refreshing the image.');
            }
        });
    }

    function bindDeckSelect() {
        var $deckSelect = $('#deckselect');
        if (!$deckSelect.length) {
            return;
        }

        $deckSelect.on('change', function () {
            if ($(this).val() === 'newdeck') {
                $('#deckqtyspan').attr('style', 'display: inline');
                $('#deckqty').removeAttr('disabled').attr('placeholder', '1');
                $('#newdecknamespan').attr('style', 'display: block');
                $('#newdeckname').removeAttr('disabled').attr('placeholder', 'New deck name');
                $('#addtodecksubmitspan').attr('style', 'display: block');
                $('#addtodeckbutton').removeAttr('disabled');
            } else if ($(this).val() === 'none') {
                $('#deckqtyspan').attr('style', 'display: none');
                $('#deckqty').attr('disabled', 'disabled').attr('placeholder', 'N/A');
                $('#newdecknamespan').attr('style', 'display: none');
                $('#newdeckname').attr('disabled', 'disabled').attr('placeholder', 'N/A');
                $('#addtodecksubmitspan').attr('style', 'display: none');
                $('#addtodeckbutton').attr('disabled', 'disabled');
            } else {
                $('#deckqtyspan').attr('style', 'display: inline');
                $('#deckqty').attr('placeholder', '1').removeAttr('disabled');
                $('#newdecknamespan').attr('style', 'display: none');
                $('#newdeckname').attr('disabled', 'disabled');
                $('#addtodecksubmitspan').attr('style', 'display: block');
                $('#addtodeckbutton').removeAttr('disabled');
            }
        });
    }

    function bindDeckSubmitValidation() {
        var $addToDeck = $('#addtodeck');
        if (!$addToDeck.length) {
            return;
        }
        $addToDeck.on('submit', function () {
            if ($('#deckselect').val() === 'newdeck' && $('#newdeckname').val() === '') {
                alert('You need to complete the form...');
                return false;
            }
        });
    }

    function bindImageHover() {
        var mainImg = $('.mainimg');
        var imgFloat = $('.imgfloat');
        var backImg = $('.backimg');
        var backImgFloat = $('.backimgfloat');

        if (mainImg.length && imgFloat.length) {
            mainImg.on('mousemove', function (e) {
                var transform = mainImg.css('transform');
                if (window.innerWidth <= 1208) {
                    return;
                }
                if (transform === 'rotate(180deg)') {
                    imgFloat.show().css({
                        top: (e.pageY - 170) + 'px',
                        left: (e.pageX + 95) + 'px',
                        transform: 'rotate(180deg)'
                    });
                } else {
                    imgFloat.show().css({
                        top: (e.pageY - 170) + 'px',
                        left: (e.pageX + 95) + 'px',
                        transform: ''
                    });
                }
            }).on('mouseout', function () {
                imgFloat.hide();
            });
        }

        if (backImg.length && backImgFloat.length) {
            backImg.on('mousemove', function (e) {
                if (window.innerWidth <= 1208) {
                    return;
                }
                backImgFloat.show().css({
                    top: (e.pageY - 170) + 'px',
                    left: (e.pageX + 95) + 'px'
                });
            }).on('mouseout', function () {
                backImgFloat.hide();
            });
        }
    }

    function bindQtyValidation() {
        $('.carddetailqtyinput').on('change', function () {
            var myqty = $(this).val();
            if (myqty === '') {
                alert('Enter a number');
                $(this).focus();
            } else if (!window.isInteger(myqty)) {
                alert('Enter a valid quantity');
                $(this).focus();
            }
        });
    }

    function bindNotesForm() {
        var notesTextarea = document.getElementById('cardnotes');
        var saveButton = document.querySelector('.save_icon');
        if (!notesTextarea || !saveButton) {
            return;
        }

        var initialNotesValue = notesTextarea.value;
        notesTextarea.addEventListener('input', function () {
            saveButton.disabled = (notesTextarea.value === initialNotesValue);
        });

        saveButton.addEventListener('click', function () {
            var notes = notesTextarea.value;
            var cardInput = document.querySelector('#updatenotesform input[name="id"]');
            if (!cardInput) {
                return;
            }

            var data = new URLSearchParams();
            data.append('newnotes', notes);
            data.append('cardid', cardInput.value);
            data.append('csrf_token', csrfToken);

            fetch('ajax/ajaxcardnotes.php', {
                method: 'POST',
                body: data,
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (result) {
                    if (result.success) {
                        initialNotesValue = notesTextarea.value;
                        saveButton.disabled = true;
                    } else {
                        alert('Error updating notes: ' + result.error);
                    }
                })
                .catch(function () {
                    alert('An error occurred while updating the notes.');
                });
        });
    }

    function initBulkShortcuts() {
        var $config = $('#carddetail-bulk-config');
        if (!$config.length) {
            return;
        }

        var cardtypes = $config.data('cardtypes');
        var cellIdOne = $config.data('cellidOne');
        var cellIdTwo = $config.data('cellidTwo');
        var cellIdThree = $config.data('cellidThree');
        var newqty = parseInt($config.data('myqty'), 10);
        var newfoil = parseInt($config.data('myfoil'), 10);
        var newetch = parseInt($config.data('myetch'), 10);

        var validkeysArray = [];
        var cellidnormal;
        var cellidfoil;
        var cellidetch;

        if (cardtypes === 'normalonly') {
            validkeysArray.push('n');
            cellidnormal = document.getElementById(cellIdOne);
        } else if (cardtypes === 'foilonly') {
            validkeysArray.push('f');
            cellidfoil = document.getElementById(cellIdOne);
        } else if (cardtypes === 'etchedonly') {
            validkeysArray.push('e');
            cellidetch = document.getElementById(cellIdOne);
        } else if (cardtypes === 'normaletched') {
            validkeysArray.push('n', 'e');
            cellidnormal = document.getElementById(cellIdOne);
            cellidetch = document.getElementById(cellIdTwo);
        } else if (cardtypes === 'normalfoiletched') {
            validkeysArray.push('n', 'f', 'e');
            cellidnormal = document.getElementById(cellIdOne);
            cellidfoil = document.getElementById(cellIdTwo);
            cellidetch = document.getElementById(cellIdThree);
        } else {
            validkeysArray.push('n', 'f');
            cellidnormal = document.getElementById(cellIdOne);
            cellidfoil = document.getElementById(cellIdTwo);
        }

        var operation = '';
        var ajaxTrigger = false;

        $(document).on('keydown', function (event) {
            var pressedKey = event.key;
            if (pressedKey === '+') {
                operation = 'add';
            } else if (pressedKey === '-') {
                operation = 'subtract';
            } else if (pressedKey === 'Escape' && (operation === 'add' || operation === 'subtract')) {
                operation = 'None';
            } else if (validkeysArray.includes(pressedKey)) {
                if (operation === 'add' && pressedKey === 'n') {
                    newqty = parseInt(newqty, 10) + 1;
                    ajaxTrigger = true;
                } else if (operation === 'add' && pressedKey === 'f') {
                    newfoil = parseInt(newfoil, 10) + 1;
                    ajaxTrigger = true;
                } else if (operation === 'add' && pressedKey === 'e') {
                    newetch = parseInt(newetch, 10) + 1;
                    ajaxTrigger = true;
                } else if (operation === 'subtract' && pressedKey === 'n') {
                    newqty = Math.max(0, parseInt(newqty, 10) - 1);
                    ajaxTrigger = true;
                } else if (operation === 'subtract' && pressedKey === 'f') {
                    newfoil = Math.max(0, parseInt(newfoil, 10) - 1);
                    ajaxTrigger = true;
                } else if (operation === 'subtract' && pressedKey === 'e') {
                    newetch = Math.max(0, parseInt(newetch, 10) - 1);
                    ajaxTrigger = true;
                }
            }
        });

        $(document).on('keyup', function (event) {
            var pressedKey = event.key;
            if (validkeysArray.includes(pressedKey)) {
                event.preventDefault();
                if (ajaxTrigger === true) {
                    if (pressedKey === 'n' && cellidnormal) {
                        cellidnormal.value = newqty;
                    } else if (pressedKey === 'f' && cellidfoil) {
                        cellidfoil.value = newfoil;
                    } else if (pressedKey === 'e' && cellidetch) {
                        cellidetch.value = newetch;
                    }
                    ajaxTrigger = false;
                    var changeEvent = new Event('change');
                    if (pressedKey === 'n' && cellidnormal) {
                        cellidnormal.dispatchEvent(changeEvent);
                    } else if (pressedKey === 'f' && cellidfoil) {
                        cellidfoil.dispatchEvent(changeEvent);
                    } else if (pressedKey === 'e' && cellidetch) {
                        cellidetch.dispatchEvent(changeEvent);
                    }
                }
            }
        });
    }

    function initPriceBlock() {
        var $priceBlock = $('#priceblock');
        if (!$priceBlock.length) {
            return;
        }
        var cardId = $priceBlock.data('cardid');
        if (!cardId) {
            return;
        }

        $.ajax({
            url: 'ajax/ajaxcardprice.php',
            type: 'POST',
            data: { cardid: cardId, csrf_token: csrfToken },
            dataType: 'json',
            success: function (response) {
                if (!response || response.success !== true) {
                    return;
                }
                if (response.price_html) {
                    $priceBlock.html(response.price_html);
                }
                if (response.tcg_link) {
                    $('#tcgplayerlink')
                        .attr('href', response.tcg_link)
                        .attr('data-loading', '0')
                        .text('TCGPlayer')
                        .attr('style', '');
                } else {
                    $('#tcgplayerlink')
                        .attr('data-loading', '1')
                        .text('TCGPlayer (unavailable)')
                        .attr('style', 'opacity:0.6;pointer-events:none;');
                }
            }
        });
    }

    function bindInlineReplacementHandlers() {
        $(document).on('click', '.msg-new', function () {
            window.closeMe(this);
        });

        $(document).on('click', '.js-rotate-img', function () {
            window.rotateImg();
        });

        $(document).on('click', '.js-swap-image', function () {
            var $el = $(this);
            var imageId = $el.data('imageId');
            var cardId = $el.data('cardid');
            var front = $el.data('imageFront');
            var back = $el.data('imageBack');
            if (!imageId || !cardId || !front || !back) {
                return;
            }
            window.swapImage(imageId, cardId, front, back);
        });

        $(document).on('click', '.js-submit-form', function (event) {
            if ($(event.target).closest('button, a, input, select, textarea').length) {
                return;
            }
            var formId = $(this).data('submitForm');
            if (!formId) {
                return;
            }
            var form = document.getElementById(formId);
            if (form) {
                form.submit();
            }
        });

        $(document).on('change', '.js-ajax-update', function () {
            if (typeof ajaxUpdate !== 'function') {
                return;
            }
            var $el = $(this);
            var cardId = $el.data('ajaxCardid');
            var cellId = $el.data('ajaxCellid');
            var flashId = $el.data('ajaxFlash');
            var postString = $el.data('ajaxPost');
            if (!cardId || !cellId || !flashId || !postString) {
                return;
            }
            ajaxUpdate(cardId, cellId, flashId, postString);
        });
    }

    function initSwipeBindings() {
        var swipeTarget = document;
        var MIN_DISTANCE = 60;
        var MAX_VERTICAL = 80;
        var MAX_TIME = 600;
        var usePointer = ('PointerEvent' in window);

        var startX = 0;
        var startY = 0;
        var lastX = 0;
        var lastY = 0;
        var moved = false;
        var startTime = 0;
        var active = false;
        var pointerId = null;

        function isEditable(el) {
            if (!el) {
                return false;
            }
            var tag = (el.tagName || '').toUpperCase();
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                return true;
            }
            if (el.isContentEditable) {
                return true;
            }
            return false;
        }

        function moveLeft() {
            var prevForm = document.getElementById('prev_card');
            if (prevForm) {
                prevForm.submit();
            }
        }

        function moveRight() {
            var nextForm = document.getElementById('next_card');
            if (nextForm) {
                nextForm.submit();
            }
        }

        document.addEventListener('keydown', function (event) {
            if (isEditable(event.target)) {
                return;
            }
            if (event.key === 'ArrowLeft') {
                moveLeft();
            } else if (event.key === 'ArrowRight') {
                moveRight();
            }
        });

        function handleSwipeEnd(dx, dy, dt) {
            if (dt > MAX_TIME) {
                return;
            }
            if (Math.abs(dx) < MIN_DISTANCE) {
                return;
            }
            if (Math.abs(dy) > MAX_VERTICAL) {
                return;
            }
            if (Math.abs(dx) < Math.abs(dy)) {
                return;
            }
            if (dx < 0) {
                moveRight();
            } else {
                moveLeft();
            }
        }

        var usingPointer = false;
        var lastPointerTime = 0;

        if (usePointer) {
            swipeTarget.addEventListener('pointerdown', function (e) {
                if (e.isPrimary === false) {
                    return;
                }
                if (isEditable(e.target)) {
                    return;
                }
                active = true;
                pointerId = e.pointerId;
                startX = e.clientX;
                startY = e.clientY;
                lastX = startX;
                lastY = startY;
                moved = false;
                startTime = Date.now();
                usingPointer = true;
                lastPointerTime = startTime;
            }, { passive: true, capture: true });

            swipeTarget.addEventListener('pointermove', function (e) {
                if (!active || e.pointerId !== pointerId) {
                    return;
                }
                lastX = e.clientX;
                lastY = e.clientY;
                if (Math.abs(lastX - startX) > 10 || Math.abs(lastY - startY) > 10) {
                    moved = true;
                }
            }, { passive: true, capture: true });

            swipeTarget.addEventListener('pointerup', function (e) {
                if (!active) {
                    return;
                }
                if (e.pointerId !== pointerId) {
                    return;
                }
                var endX = moved ? lastX : e.clientX;
                var endY = moved ? lastY : e.clientY;
                var dx = endX - startX;
                var dy = endY - startY;
                var dt = Date.now() - startTime;

                active = false;
                pointerId = null;
                usingPointer = false;

                handleSwipeEnd(dx, dy, dt);
            }, { passive: true, capture: true });

            swipeTarget.addEventListener('pointercancel', function (e) {
                if (e.pointerId === pointerId) {
                    active = false;
                    pointerId = null;
                    usingPointer = false;
                }
            }, { passive: true, capture: true });
        }

        swipeTarget.addEventListener('touchstart', function (e) {
            if (usingPointer || (Date.now() - lastPointerTime < 500)) {
                return;
            }
            if (isEditable(e.target)) {
                return;
            }
            var touch = e.touches[0];
            if (!touch) {
                return;
            }
            active = true;
            startX = touch.clientX;
            startY = touch.clientY;
            lastX = startX;
            lastY = startY;
            moved = false;
            startTime = Date.now();
        }, { passive: true, capture: true });

        swipeTarget.addEventListener('touchmove', function (e) {
            if (!active) {
                return;
            }
            var touch = e.touches[0];
            if (!touch) {
                return;
            }
            lastX = touch.clientX;
            lastY = touch.clientY;
            var dx = lastX - startX;
            var dy = lastY - startY;
            if (Math.abs(dx) > 10 || Math.abs(dy) > 10) {
                moved = true;
            }
            if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 10) {
                e.preventDefault();
            }
        }, { passive: false, capture: true });

        swipeTarget.addEventListener('touchend', function (e) {
            if (!active) {
                return;
            }
            var touch = e.changedTouches[0];
            if (!touch) {
                return;
            }
            var endX = moved ? lastX : touch.clientX;
            var endY = moved ? lastY : touch.clientY;
            var dx = endX - startX;
            var dy = endY - startY;
            var dt = Date.now() - startTime;

            active = false;
            moved = false;
            handleSwipeEnd(dx, dy, dt);
        }, { passive: true, capture: true });

        swipeTarget.addEventListener('touchcancel', function () {
            active = false;
            moved = false;
        }, { passive: true, capture: true });
    }

    window.closeMe = function (obj) {
        obj.style.display = 'none';
        if (cardIdForRedirect) {
            window.location.href = 'carddetail.php?id=' + encodeURIComponent(cardIdForRedirect);
        } else {
            window.location.href = 'carddetail.php';
        }
    };

    window.isInteger = function (x) {
        if (x < 0) {
            return false;
        }
        return x % 1 === 0;
    };

    window.rotateImg = function () {
        var mainImg = document.getElementById('cardimg');
        if (!mainImg) {
            mainImg = document.querySelector('#carddetailimage img.mainimg');
        }
        if (!mainImg) {
            console.debug('[DEBUG]', 'Card detail rotate skipped, image not found.');
            return;
        }
        var rotateOn = mainImg.style.transform !== 'rotate(180deg)';
        var targetTransform = rotateOn ? 'rotate(180deg)' : 'none';
        mainImg.style.transform = targetTransform;
        var cardId = mainImg.getAttribute('data-cardid') || '';
        var hoverImg = null;
        if (cardId) {
            hoverImg = document.querySelector('#image-' + cardId + ' img.mainimg');
        }
        if (hoverImg && hoverImg !== mainImg) {
            hoverImg.style.transform = targetTransform;
        }
        console.debug('[DEBUG]', 'Card detail rotate toggled.', {
            cardId: cardId || null,
            transform: targetTransform
        });
    };

    window.swapImage = function (img_id, card_id, imageurl, imagebackurl) {
        var ImageId = document.getElementById(img_id);
        if (!ImageId) {
            return;
        }
        var FrontImg = card_id + '.jpg';
        var BackImg = card_id + '_b.jpg';

        if (ImageId.src.match(FrontImg)) {
            ImageId.classList.add('flipped');
            setTimeout(function () {
                ImageId.src = imagebackurl;
            }, 80);
        } else {
            ImageId.classList.remove('flipped');
            setTimeout(function () {
                ImageId.src = imageurl;
            }, 80);
        }
    };

    $(function () {
        var button = document.getElementById('addtodeckbutton');
        if (button) {
            button.value = 'ADD';
        }

        $('img').on('error', function () {
            $(this).css('opacity', '1').attr('src', '/images/back.jpg');
        });

        var $importSubmit = $('#importsubmit');
        if ($importSubmit.length) {
            $importSubmit.prop('disabled', true);
            $('#importfile').on('change', function () {
                $importSubmit.prop('disabled', !$(this).val());
            });
        }

        bindDeckSelect();
        bindDeckSubmitValidation();
        bindImageHover();
        bindQtyValidation();
        bindNotesForm();
        bindInlineReplacementHandlers();
        initBulkShortcuts();
        initPriceBlock();

        $('#refreshsubmit').on('click', function () {
            refreshImage();
        });

        refreshCardImagesAsync();
        updateFlipButtonsForViewport();
        $(window).on('resize', updateFlipButtonsForViewport);

        initSwipeBindings();
    });
})();
