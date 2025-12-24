function toggleInfoBox() {
    var infoBox = document.getElementById("infoBox");
    infoBox.style.display = (infoBox.style.display === "none" || infoBox.style.display === "")
        ? "block"
        : "none";
}

var deckNumber = 0;
var isCommanderDeck = false;
if (window.mtgDeckDetailConfig) {
    deckNumber = window.mtgDeckDetailConfig.deckNumber || 0;
    isCommanderDeck = window.mtgDeckDetailConfig.isCommanderDeck === true;
}

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

$(document).ready(function () {
    bindDeckCardActions();
});
