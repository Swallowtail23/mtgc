$(function()
{
$(".headersearch").keyup(function() 
{ 

var searchid = $(this).val();
var dataString = 'search='+ searchid;
if(searchid!='')
{
    $.ajax({
    type: "POST",
    url: "/ajaxsearch.php",
    data: dataString,
    cache: false,
    success: function(html)
    {
    $("#ajaxresult").html(html).show();
    }
    });
}return false;    
});

jQuery("#ajaxresult").on("click",function(e){ 
    var $clicked = $(e.target);
    var $name = $clicked.find('.name').html();
    var decoded = $("<div/>").html($name).text();
    $('#searchid').val(decoded);
});
jQuery(document).on("click", function(e) { 
    var $clicked = $(e.target);
    if (! $clicked.hasClass("headersearch")){
    jQuery("#ajaxresult").fadeOut(); 
    }
});
$('#searchid').click(function(){
    jQuery("#ajaxresult").fadeIn();
});
});