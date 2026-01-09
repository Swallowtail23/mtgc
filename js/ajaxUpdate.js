/*
Version:     1.0
Date:        09/01/26
Name:        ajaxUpdate.js
Purpose:     Update card quantities via AJAX grid actions.
Notes:       -
Author:      Simon Wilson
Copyright:   2026 MTG Collection
To do:       -
*/

// Update a card quantity for the given cell/card via AJAX.
// Reads the current input value from DOM, posts it to ajaxgrid, and flashes status styles.
function getAjaxCsrfToken() {
    if (window.mtgAjaxConfig && window.mtgAjaxConfig.csrfToken) {
        return window.mtgAjaxConfig.csrfToken;
    }
    return '';
}

function ajaxUpdate(cardid, cellid, flash, type) {
    var activeCell = $('#' + cellid);
    var activeFlash = $('#' + flash);

    if (activeCell.length === 0 || activeFlash.length === 0) {
        console.error('ajaxUpdate: missing element(s)', cellid, flash);
        return false;
    }

    var currentValue = activeCell.val();

    if (currentValue === '') {
        alert("Enter a number");
        activeCell.focus();
    } else if (!isInteger(currentValue)) {
        alert("Enter an integer");
        activeCell.focus();
    } else {
        var csrfToken = getAjaxCsrfToken();
        $.ajax({
            type: "POST",
            url: "ajax/ajaxgrid.php",
            data: { [type]: currentValue, cardid: cardid, csrf_token: csrfToken },
            success: function (data) {
                activeFlash.removeClass(["bulksubmitsuccessfont", "bulksubmiterrorfont", "bulksubmitsuccessbg"])
                           .addClass(["bulksubmitnormalfont", "bulksubmitsuccessbg"]);

                setTimeout(function() {
                    activeFlash.removeClass("bulksubmitsuccessbg")
                               .addClass("bulksubmitsuccessfont");
                }, 2000);
                // If called from index.php in grid view, update grid view classes
                if (document.location.pathname === '/index.php' && typeof toggleNoCollectionClass === 'function') {
                    toggleNoCollectionClass(cardid); // located in cviewClassToggle.js
                }
            },
            error: function (xhr, status, error) {
                console.error("Error response:", xhr.responseText);
                try {
                    var response = JSON.parse(xhr.responseText);
                } catch (parseError) {
                    console.error("Failed to parse error response as JSON");
                }
                activeFlash.removeClass(["bulksubmitsuccessfont", "bulksubmitsuccessbg", "bulksubmitnormalfont"])
                           .addClass("bulksubmiterrorfont");
            }
        });
    }
    return false;
};
