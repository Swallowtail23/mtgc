function ajaxUpdate(cardid, cellid, qty, flash, type) {
    var activeCell = $('#' + cellid);
    var activeFlash = $('#' + flash);
    var poststring = type + '=' + activeCell.val() + '&cardid=' + cardid;

    if (activeCell.val() === '') {
        alert("Enter a number");
        activeCell.focus();
    } else if (!isInteger(activeCell.val())) {
        alert("Enter an integer");
        activeCell.focus();
    } else {
        $.ajax({
            type: "POST",
            url: "ajax/ajaxgrid.php",
            data: poststring,
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
                var response = JSON.parse(xhr.responseText);
                activeFlash.removeClass(["bulksubmitsuccessfont", "bulksubmitsuccessbg", "bulksubmitnormalfont"])
                           .addClass("bulksubmiterrorfont");
            }
        });
    }
    return false;
};