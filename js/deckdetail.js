/*
Version:     1.7
Date:        24/12/25
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
if (window.mtgDeckDetailConfig) {
    deckNumber = window.mtgDeckDetailConfig.deckNumber || 0;
    isCommanderDeck = window.mtgDeckDetailConfig.isCommanderDeck === true;
    deckName = window.mtgDeckDetailConfig.deckName || '';
    randomDrawEnabled = window.mtgDeckDetailConfig.randomDrawEnabled === true;
    if (Array.isArray(window.mtgDeckDetailConfig.randomDrawRefs)) {
        randomDrawRefs = window.mtgDeckDetailConfig.randomDrawRefs;
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
    if (randomDrawEnabled) {
        $('#random-draw-button').off('click').on('click', refreshTable);
    }
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
        include_check: true
    });
    xhr.open('POST', 'ajax/ajaxrandomdraw.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            document.getElementById('table-container').innerHTML = xhr.responseText;
            if (window.bindRandomCardEvents) {
                window.bindRandomCardEvents();
            }
            window.dispatchEvent(new Event('resize'));
        }
    };
    xhr.send(data);
}

window.closeMe = closeMe;
window.submitForm = submitForm;
window.refreshTable = refreshTable;

function bindDeckCardActions() {
    $('.js-plusmain').off('click').on('click', function (e) {
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
                cardid: cardId
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
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
            refreshDeckFragments();
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $('.js-minusmain').off('click').on('click', function (e) {
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
                cardid: cardId
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            if (response.cardqty <= 0) {
                $row.remove();
                if (cardRef) {
                    $('#list-' + cardRef).remove();
                }
                updateDeckTotals();
                refreshDeckFragments();
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
            refreshDeckFragments();
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $('.js-deletemain').off('click').on('click', function (e) {
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
                cardid: cardId
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
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
            refreshDeckFragments();
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $('.js-maintoside').off('click').on('click', function (e) {
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
                cardid: cardId
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
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
            refreshDeckFragments({
                refreshImages: false,
                newCardIds: response.side_row_html ? [cardId] : [],
                fragments: [
                    'colour_identity',
                    'warnings',
                    'mana_value',
                    'mana_costs',
                    'deck_value',
                    'deck_lists',
                    'export_list',
                    'missing',
                    'buy_missing',
                    'random_draw'
                ]
            });
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $('.js-plusside').off('click').on('click', function (e) {
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
                cardid: cardId
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
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
            refreshDeckFragments();
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $('.js-minusside').off('click').on('click', function (e) {
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
                cardid: cardId
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
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
            refreshDeckFragments();
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $('.js-deleteside').off('click').on('click', function (e) {
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
                cardid: cardId
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            $row.remove();
            if (cardRef) {
                $('#listside-' + cardRef).remove();
            }
            updateDeckTotals();
            refreshDeckFragments();
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $('.js-sidetomain').off('click').on('click', function (e) {
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
                cardid: cardId
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
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
            refreshDeckFragments();
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
        });
    });

    $('.js-commander-add, .js-partner-add, .js-commander-remove').off('click').on('click', function (e) {
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
                cardid: cardId
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                alert('That did not work. Please try again.');
                return;
            }
            refreshDeckFragments();
        }).fail(function () {
            alert('That did not work. Please try again.');
        }).always(function () {
            $button.data('busy', false);
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
        var fragments = [];
        if (requestedFragments) {
            fragments = requestedFragments;
        } else if (window.mtgDeckDetailConfig && Array.isArray(window.mtgDeckDetailConfig.fragments)) {
            fragments = window.mtgDeckDetailConfig.fragments;
        }
        if (fragments.length === 0) {
            fragments = [
                'decklist',
                'colour_identity',
                'warnings',
                'mana_value',
                'mana_costs',
                'deck_value',
                'deck_lists',
                'export_list',
                'missing',
                'buy_missing',
                'random_draw'
            ];
        }
        $.ajax({
            url: 'ajax/ajaxdeckfragments.php',
            method: 'POST',
            dataType: 'json',
            data: {
                decknumber: deckNumber,
                fragments: fragments
            }
        }).done(function (response) {
            if (!response || response.success !== true || !response.fragments) {
                return;
            }
            if (replaceDecklist && response.fragments.decklist) {
                $('#decklist-fragment').replaceWith(response.fragments.decklist);
            }
            if (response.fragments.colour_identity) {
                $('#deck-colour-identity-fragment').replaceWith(response.fragments.colour_identity);
            }
            if (response.fragments.warnings) {
                $('#deck-warnings-fragment').replaceWith(response.fragments.warnings);
            }
            if (response.fragments.mana_value) {
                $('#deck-mana-value-fragment').replaceWith(response.fragments.mana_value);
            }
            if (response.fragments.mana_costs) {
                $('#deck-mana-costs-fragment').replaceWith(response.fragments.mana_costs);
            }
            if (response.fragments.deck_value) {
                $('#deck-value-fragment').replaceWith(response.fragments.deck_value);
            }
            if (response.fragments.deck_lists) {
                $('#deck-lists-fragment').replaceWith(response.fragments.deck_lists);
            } else {
                if (response.fragments.export_list) {
                    $('#deck-export-fragment').replaceWith(response.fragments.export_list);
                }
                if (response.fragments.missing) {
                    $('#deck-missing-fragment').replaceWith(response.fragments.missing);
                }
                if (response.fragments.buy_missing) {
                    $('#deck-buy-fragment').replaceWith(response.fragments.buy_missing);
                }
            }
            if (response.fragments.random_draw) {
                $('#deck-random-draw-fragment').replaceWith(response.fragments.random_draw);
                updateRandomDrawState();
            }
            bindDeckCardActions();
            if (window.bindRandomCardEvents) {
                window.bindRandomCardEvents();
            }
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
        });
    }

    $(document).ready(function () {
        bindDeckCardActions();
        renderManaValueChart();
        updateRandomDrawState();

        $('#changeType select[name="updatetype"]').on('change', function (event) {
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
                    updatetype: newType
                }
            }).done(function (response) {
                if (!response || response.success !== true) {
                    alert('That did not work. Please try again.');
                    return;
                }
                isCommanderDeck = response.is_commander === true;
                if (window.mtgDeckDetailConfig) {
                    window.mtgDeckDetailConfig.isCommanderDeck = isCommanderDeck;
                }
                $('#currentType').html(
                    "<span style='font-weight:500'>" + $select.find('option:selected').text() + "</span><br>"
                );
                $("#changeType").hide();
                $("#currentType").show();
                refreshDeckFragments();
            }).fail(function () {
                alert('That did not work. Please try again.');
            }).always(function () {
                $select.prop('disabled', false);
            });
        });

        $('#renameForm').on('submit', function (event) {
            event.preventDefault();
            var fieldValue = $('#newname').val();
            if (!fieldValue || fieldValue.trim() === '') {
                alert('Rename field cannot be empty');
                return;
            }
            if (deckName && fieldValue.trim() === deckName.trim()) {
                alert('To cancel rename click edit button again');
                return;
            }
            this.submit();
        });

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
