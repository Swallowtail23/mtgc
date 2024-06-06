<?php 
/* Version:     1.1
    Date:       02/01/24
    Name:       header.php
    Purpose:    PHP script to display header
    Notes:      {none}
 * 
    1.0
                Initial version
 * 
 *  1.1         02/01/24
 *              Add debounce to search calls
*/
if (__FILE__ == $_SERVER['PHP_SELF']) :
die('Direct access prohibited');
endif;
?>

<script>
    $(function() {
        var debounce = function(func, delay) {
            var inDebounce;
            return function() {
                var context = this;
                var args = arguments;
                clearTimeout(inDebounce);
                inDebounce = setTimeout(function() {
                    func.apply(context, args);
                }, delay);
            }
        };

        var ajaxCall = debounce(function(searchid) {
            var dataString = 'search=' + searchid;
            
            $('body').css('cursor', 'wait');
            
            $.ajax({
                type: "POST",
                url: "/ajax/ajaxsearch.php",
                data: dataString,
                cache: false,
                success: function(html) {
                    $("#ajaxresult").html(html).show();
                },
                complete: function() {
                    $('body').css('cursor', 'default');
                }
            });
        }, 300); // Delay of 300 milliseconds

        $("#searchid").on("input keyup", function() {
            var searchid = $(this).val();
            if(searchid.length >= 3) {
                ajaxCall(searchid);
            } else if (searchid.length === 0) {
                $("#ajaxresult").html('').hide(); // Hide and clear results if search is empty
            }
            return false;
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
</script>
<script type="text/javascript"> 
    $(document).ready(function() {
        $('#ajaxresult').click(function(e){
            e.stopPropagation();
        });
        $('.searchicon').click(function(e){
            e.stopPropagation();
            $('.searchicon').css("opacity", "0");
            $('.searchicon').css("z-index", "0");
            
            $('#headerresults').css("opacity", "0");
            $('#headerresults').css("z-index", "0");
            
            $('#ajaxresult').css("opacity", "1");
            $('#ajaxresult').css("z-index", "99999");
            
            $('#headersearch_div').css("opacity", "1");
            $('#headersearch_div').css("z-index", "99999");
            document.getElementById('searchid').focus();
        });
        var menuout = 0;
        $('#menubuttondiv').click(function(e){
            if(menuout === 0) {
                e.stopPropagation();
                $('#menu').css("left", "0");
                $('#menu-icon').text('menu_open'); 
                menuout = 1;
            } else if(menuout === 1) {
                e.stopPropagation();
                $('#menu').css("left", "-185px");
                $('#menu-icon').text('menu'); 
                menuout = 0;
            };
        });
        $('#headersearch_div').click(function(e) {
            e.stopPropagation();
        });
        $('#grey').click(function(e) {
            e.stopPropagation();
        });
        $('#cancelsearch').click(function(e) {
            e.stopPropagation();
            $('#headersearch_div').css("opacity", "0");
            $('#headersearch_div').css("z-index", "0");
        
            $('#headerresults').css("opacity", "1");
            $('#headerresults').css("z-index", "9999");               
            
            $('#ajaxresult').css("opacity", "0");
            $('#ajaxresult').css("z-index", "-1");
            
            $('.searchicon').css("opacity", "1");
            $('.searchicon').css("z-index", "100000");
            
            $('#searchid').val('');
        });
    });
    $(document).click(function() {
        $('#headersearch_div').css("opacity", "0");
        $('#headersearch_div').css("z-index", "0");
        
        $('#headerresults').css("opacity", "1");
        $('#headerresults').css("z-index", "9999");        
        
        $('#ajaxresult').css("opacity", "0");
        $('#ajaxresult').css("z-index", "-1");
        
        $('.searchicon').css("opacity", "1");
        $('.searchicon').css("z-index", "100000");
        
        $('#searchid').val('');
    });
</script>  
<?php
$adminpages = strpos($_SERVER['PHP_SELF'],"/admin/");
if((isset($mtcestatus)) AND ($mtcestatus != 1) AND (!isset($_SESSION["chgpwd"])) AND ($adminpages === FALSE)):
   ?>
    <div id="ajaxresult">
    </div>
    <div class="searchicon"><span class="material-symbols-outlined searchicon">search</span>
    </div>
    <div id='headersearch_div'>
        <div id='cancelsearch'><span class="material-symbols-outlined">close</span></div>
        <form action="/index.php" method="get">
            <input type="text" class='headersearch' id="searchid" name="name" autocomplete='off' placeholder="Basic search">
            <input type='hidden' name='layout' value='grid'>
        </form>
    </div>
    <?php
elseif ($adminpages !== FALSE):
    include 'adminmenus.php';
endif;
?>
<div class='image'>
    
</div>
<div id="headerdivider">
</div>

<div id="title">
    <a class="headername" href="/index.php"><?php echo $siteTitle;?> </a>
</div> 
<div <?php 
    if ($tier == 'dev'):
        echo "class='headerdev'";
    else:
        echo "class='headerprod'";
    endif; 
    ?>
    id='header' class='fullsize'> 
       
    <div id='headerresults'>
            <?php 
            if (isset($validsearch) AND ($validsearch === "true")) :
                if(isset($nametrim)):
                    echo "<span id='searchname'>$nametrim</span>";
                endif;
                if ($qtyresults === 0) :
                    echo "<span id='searchnametip'> - No results found &nbsp;</span>";
                endif;
            elseif (isset($validsearch) AND ($validsearch === "toomany")):
                $qtyresults = 0;
                echo "<span id='searchnametip'>{$maxresults}+ results, try again</span>";
            elseif (isset($validsearch) AND ($validsearch === "zero")):
                $qtyresults = 0;
                echo "<span id='searchnametip'>No results</span>";
            elseif (empty($validsearch)) :
                echo "<span id='searchnametip'>&nbsp;</span>";
            else:
                echo "<span id='searchnametip'>Search for 3 characters or more</span>";
            endif; ?>
            <span id="resultscount">
            <?php
            if (isset($validsearch) AND ($validsearch === "true")) :
                if (!$qtyresults === 0) :

                elseif ($qtyresults === 1) :
                        echo $qtyresults." match";
                elseif ($qtyresults <= $perpage) :
                        echo $qtyresults." matches";
                else:
                    echo $qtyresults." matches";
                endif;
            endif;    
            ?>
            </span>
    </div>
    </div>
    