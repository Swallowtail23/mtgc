function toggleNoCollectionClass(cardid) {
    const cellone = document.getElementById('cell' + cardid + '_one');
    const celltwo = document.getElementById('cell' + cardid + '_two');
    const cellthree = document.getElementById('cell' + cardid + '_three');
    var celloneValue = cellone ? cellone.value : 0;
    var celltwoValue = celltwo ? celltwo.value : 0;
    var cellthreeValue = cellthree ? cellthree.value : 0;
    var totalCards = parseInt(celloneValue) + parseInt(celltwoValue) + parseInt(cellthreeValue);
    const imageID = (cardid + 'img');
    const image = document.getElementById(cardid + 'img');
    var checkBox = document.getElementById("float_cview");

    // Check if the image element exists
    if (image) {
        if (totalCards > 0 && checkBox.checked === true) {
            // Remove the 'none' and 'no_collection' classes
            image.classList.remove('none', 'no_collection');
        } else if (totalCards === 0 && checkBox.checked === true) {
            // Add the 'none' and 'no_collection' classes
            image.classList.add('none', 'no_collection');
        } else if (totalCards > 0 && checkBox.checked === false) {
            image.classList.remove('none');
        } else if (totalCards === 0 && checkBox.checked === false) {
            image.classList.add('none');
        }
    } else {
        // Log an error if the image element is not found
        console.error(`Image element with ID '${cardid}' not found.`);
    }
};

function removeNoCollectionClass() {
    $('.cardimg.none.no_collection').removeClass('no_collection');
};

function addNoCollectionClass() {
    $('.cardimg.none').addClass('no_collection');
};

$(document).ready(function(){
    var sliderElement = $('.slider.round.material-symbols-outlined');

    $('#float_cview').on('change', function() {
        var isChecked = $("#float_cview").is(":checked");
        var cview = isChecked ? "TURN ON" : "TURN OFF";
        var title = isChecked ? "Toggle collection view off" : "Toggle collection view on";
        var classAction = isChecked ? 'removeClass' : 'addClass';

        if (isChecked) {
            addNoCollectionClass();
        } else {
            removeNoCollectionClass();
        }

        $("#floating_button_label").attr("title", title);
        sliderElement[classAction]('book_2');

        $.ajax({  
            url: "/ajax/ajaxcview.php",  
            method: "POST",  
            data: { "collection_view": cview },
            success: function(data) {
                // Handle response if needed
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX error: " + textStatus + " - " + errorThrown);
            }
        });
    });
});