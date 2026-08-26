<?php

/*
Version:     4.38
Date:        26/08/26
Name:        sets.php
Purpose:     Lists all setcodes and sets in the database.
Notes:       This page is the only one NOT mobile responsive design. Access via profile link hidden on mobile.
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

use MTG\Auth\SessionManager;

// Bootstrap
$ctx                        = require __DIR__ . '/bootstrap_secure.php';

$appConfig                  = $ctx->config();
$db                         = $ctx->db();
$msg                        = $ctx->message();
$gameRules                  = $ctx->rules();
$cssver                     = (string) $ctx->meta('cssver', '');
$serviceWorkerVersion       = (string) $ctx->meta('serviceWorkerVersion', 'v6');
$sessionUser                = $ctx->sessionUser();

$siteTitle                  = (string) $appConfig->general('title', '');
$tier                       = (string) $appConfig->general('tier', 'prod');
$copyright                  = (string) $appConfig->general('copyright', '');

$userEmail                  = $sessionUser->email();
$admin                      = $sessionUser->adminLevel();

// Content
$siteTitleEsc = htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8');

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$initialFilter = isset($_GET['filter']) ? trim((string) $_GET['filter']) : '';
$useAjaxInitialLoad = ($page > 1 || strlen($initialFilter) >= 3);
$setsPerPage = 30;
$range = 4;
$totalSets = 0;
$totalPages = 1;

$totalSetsQuery = $db->query("SELECT COUNT(DISTINCT name) as totalSets FROM sets");
if ($totalSetsQuery) :
    $totalSetsRow = $totalSetsQuery->fetch_assoc();
    if (is_array($totalSetsRow) && isset($totalSetsRow['totalSets'])) :
        $totalSets = (int) $totalSetsRow['totalSets'];
        $totalPages = max(1, (int) ceil($totalSets / $setsPerPage));
    else :
        $msg->logMessage('[DEBUG]', 'sets.php: total sets query returned no rows');
    endif;
else :
    $msg->logMessage('[ERROR]', 'sets.php: failed to query total sets');
endif;

if ($totalSets > 0 && $page > $totalPages) :
    header('Location: /sets.php?page=' . max(1, $totalPages));
    exit;
endif;
?>
<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="initial-scale=1">
    <title><?php echo $siteTitleEsc;?> - sets</title>
    <link rel="manifest" href="/manifest.json" />
    <link
        rel="stylesheet"
        type="text/css"
        href="css/style<?php echo htmlspecialchars($cssver, ENT_QUOTES, 'UTF-8');?>.css?v=<?php
        echo $serviceWorkerVersion; ?>"
    >
    <link href="//cdn.jsdelivr.net/npm/keyrune@latest/css/keyrune.css" rel="stylesheet" type="text/css" />
    <?php include APP_ROOT . '/includes/googlefonts.php';?>
    <script src="/js/jquery.js?v=<?php echo $serviceWorkerVersion; ?>"></script>
    <script>
        const csrfToken = <?php echo json_encode(SessionManager::generateCsrfToken()); ?>;

        function createReloadImagesControl(setcode) {
            var control = document.createElement('div');
            control.className = 'set-image-reload-control';
            control.dataset.setcode = setcode;

            var trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'set-image-reload-trigger';
            trigger.setAttribute('aria-haspopup', 'menu');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.setAttribute('aria-label', 'Choose image reload scope for ' + setcode.toUpperCase());
            trigger.title = 'Reload set images';

            var icon = document.createElement('span');
            icon.className = 'material-symbols-outlined';
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = 'frame_reload';
            trigger.appendChild(icon);

            var menu = document.createElement('div');
            menu.className = 'set-image-reload-menu';
            menu.setAttribute('role', 'menu');
            menu.setAttribute('aria-label', 'Image reload scope');
            menu.hidden = true;

            [
                { scope: 'primary', label: 'Primary language only' },
                { scope: 'all', label: 'All languages...' }
            ].forEach(function(option) {
                var menuItem = document.createElement('button');
                menuItem.type = 'button';
                menuItem.className = 'set-image-reload-option';
                menuItem.dataset.scope = option.scope;
                menuItem.setAttribute('role', 'menuitem');
                menuItem.textContent = option.label;
                menu.appendChild(menuItem);
            });

            control.appendChild(trigger);
            control.appendChild(menu);
            return control;
        }

        function populateReloadImagesCell(cell, setcode) {
            cell.textContent = '';
            cell.appendChild(createReloadImagesControl(setcode));
        }

        function closeReloadImageMenus() {
            document.querySelectorAll('.set-image-reload-menu').forEach(function(menu) {
                menu.hidden = true;
                menu.parentElement.querySelector('.set-image-reload-trigger')
                    .setAttribute('aria-expanded', 'false');
            });
        }

        document.addEventListener('click', function(event) {
            var trigger = event.target.closest('.set-image-reload-trigger');
            if (trigger) {
                event.preventDefault();
                var menu = trigger.parentElement.querySelector('.set-image-reload-menu');
                var shouldOpen = menu.hidden;
                closeReloadImageMenus();
                if (shouldOpen) {
                    menu.hidden = false;
                    trigger.setAttribute('aria-expanded', 'true');
                    menu.querySelector('.set-image-reload-option').focus();
                }
                return;
            }

            var option = event.target.closest('.set-image-reload-option');
            if (option) {
                event.preventDefault();
                var control = option.closest('.set-image-reload-control');
                var setcode = control.dataset.setcode;
                var scope = option.dataset.scope;
                var controlTrigger = control.querySelector('.set-image-reload-trigger');
                closeReloadImageMenus();
                controlTrigger.focus();

                if (
                    scope === 'all'
                    && !window.confirm(
                        'Reload images for all languages in set ' + setcode.toUpperCase()
                        + '? This can start a large background job.'
                    )
                ) {
                    return;
                }

                reloadImages(setcode, scope);
                return;
            }

            closeReloadImageMenus();
        });

        document.addEventListener('keydown', function(event) {
            var menuItem = event.target.closest('.set-image-reload-option');
            if (menuItem && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
                event.preventDefault();
                var menuItems = Array.from(menuItem.parentElement.querySelectorAll('.set-image-reload-option'));
                var direction = event.key === 'ArrowDown' ? 1 : -1;
                var nextIndex = (menuItems.indexOf(menuItem) + direction + menuItems.length) % menuItems.length;
                menuItems[nextIndex].focus();
                return;
            }

            if (event.key !== 'Escape') {
                return;
            }

            var openMenu = document.querySelector('.set-image-reload-menu:not([hidden])');
            if (openMenu) {
                var openTrigger = openMenu.parentElement.querySelector('.set-image-reload-trigger');
                closeReloadImageMenus();
                openTrigger.focus();
            }
        });

        function reloadImages(setcode, scope) {
            document.body.style.cursor = "wait";
            $.ajax({
                type: 'POST',
                url: 'ajax/ajaxsetimg.php',
                dataType: 'json',
                data: { setcode: setcode, scope: scope, csrf_token: csrfToken },
                success: function(response) {
                    var result = response;
                    if (typeof response === 'string') {
                        try {
                            result = JSON.parse(response);
                        } catch (e) {
                            result = { status: 'error', message: 'Unexpected response from server.' };
                        }
                    }

                    showMessage(result.status, result.message);

                    if (result.status === 'success') {
                        console.log(result.message);
                    } else {
                        console.error(result.message);
                    }
                },
                error: function(error) {
                    showMessage('error', 'An error occurred.');
                    console.error(error);
                },
                complete: function() {
                    document.body.style.cursor = "default";
                }
            });
        }

        function showMessage(status, message) {
            var $message = $('<div class="msg-new"></div>');
            if (status === 'success') {
                $message.addClass('success-new');
            } else {
                $message.addClass('error-new');
            }
            $message.append($('<span></span>').text(message)).append('<br>');
            $message.append("<p onmouseover=\"\" style=\"cursor: pointer;\" id='dismiss'>OK</p>");
            $message.on('click', function () {
                $(this).remove();
            });
            $('body').append($message);
            setTimeout(function () {
                $message.fadeOut(200, function () {
                    $(this).remove();
                });
            }, 5000);
        }

        var isAdmin = <?php echo json_encode($admin == 1); ?>;

        function buildPagination(totalPages, currentPage, setsPerPage) {
            console.log('Total: ' + totalPages + ', Current: ' + currentPage);
            var paginationHTML = '';
            paginationHTML += '<br>Page &nbsp;';
            var range = <?php echo $range; ?>;

            if (currentPage !== 1) {
                paginationHTML += buildPageLink('previous', currentPage, setsPerPage);
            }

            for (var i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - range && i <= currentPage + range)) {
                    paginationHTML += buildPageLink(i, currentPage, setsPerPage);
                } else if ((i === currentPage - range - 1 || i === currentPage + range + 1)) {
                    paginationHTML += '<span class="pagination-item">...&nbsp;</span>';
                }
            }

            if (currentPage !== totalPages) {
                paginationHTML += buildPageLink('next', currentPage, setsPerPage);
            }

            paginationHTML += '<br>&nbsp;';
            return paginationHTML;
        }

        function buildPageLink(page, currentPage, setsPerPage) {
            if (page === currentPage) {
                return '<span class="pagination-item" style="font-weight: bold">' + page + '&nbsp;&nbsp;</span>';
            } else if (page === 'next') {
                var nextPage = currentPage + 1;
                return '<a class="pagination-item" href="javascript:loadPage(' + nextPage + ', ' + setsPerPage + ');">'
                    + '<span class="material-symbols-outlined set-page pagination-item">skip_next</span></a>';
            } else if (page === 'previous') {
                var previousPage = currentPage - 1;
                return '<a class="pagination-item" href="javascript:loadPage(' + previousPage + ', '
                    + setsPerPage + ');">'
                    + '<span class="material-symbols-outlined set-page pagination-item">skip_previous</span></a>&nbsp;';
            } else {
                return '<a class="pagination-item" href="javascript:loadPage(' + page + ', ' + setsPerPage + ');">'
                    + page + '&nbsp;&nbsp;</a>';
            }
        }

        var currentPage = <?php echo (int) $page; ?>;
        var currentFilter = '';

        function normalizeFilter(value) {
            var trimmed = (value || '').trim();
            if (trimmed.length > 0 && trimmed.length < 3) {
                return '';
            }
            return trimmed;
        }

        function buildSetsUrl(pageNumber, filterValue) {
            var params = [];
            if (pageNumber > 1) {
                params.push('page=' + encodeURIComponent(pageNumber));
            }
            if (filterValue) {
                params.push('filter=' + encodeURIComponent(filterValue));
            }
            if (!params.length) {
                return 'sets.php';
            }
            return 'sets.php?' + params.join('&');
        }

        function updateHistoryState(pageNumber, filterValue, replace) {
            if (!window.history || !window.history.pushState) {
                return;
            }
            var state = { page: pageNumber, filter: filterValue };
            var url = buildSetsUrl(pageNumber, filterValue);
            if (replace) {
                window.history.replaceState(state, '', url);
            } else {
                window.history.pushState(state, '', url);
            }
            console.debug('[DEBUG]', 'Sets pagination state updated', state);
        }

        function getStateFromUrl() {
            var params = new URLSearchParams(window.location.search);
            var pageParam = parseInt(params.get('page'), 10);
            var pageNumber = isNaN(pageParam) ? 1 : Math.max(1, pageParam);
            var filterValue = params.get('filter') || '';
            return {
                page: pageNumber,
                filter: normalizeFilter(filterValue)
            };
        }

        function fetchAndDisplaySets(filterValue, pageNumber, setsPerPage, options) {
            var opts = options || {};
            var normalizedFilter = normalizeFilter(filterValue);
            var normalizedPage = Math.max(1, parseInt(pageNumber, 10) || 1);
            currentPage = normalizedPage;
            currentFilter = normalizedFilter;
            if (opts.updateHistory) {
                updateHistoryState(currentPage, currentFilter, opts.replaceHistory === true);
            }
            var offset = (pageNumber * setsPerPage) - (setsPerPage);
            offset = Math.max(0, offset);

            $.ajax({
                type: 'GET',
                url: 'ajax/ajaxsets.php',
                data: {
                    filter: normalizedFilter,
                    setsPerPage: setsPerPage,
                    offset: offset,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    var loadingEl = document.getElementById('setsLoading');
                    if (loadingEl) {
                        loadingEl.style.display = 'none';
                    }
                    if (response.numResults === 0) {
                        document.getElementById('setlist').style = "display: none";
                        document.getElementById('paginationTop').style = "display: none";
                        document.getElementById('paginationBottom').style = "display: none";
                        document.getElementById('noResults').style = "display: block";
                        console.log('Set search: No results');
                    } else if (response.numPages === 1) {
                        document.getElementById('setlist').style = "display: table";
                        updateTable(response.filteredSets);
                        window.scrollTo(0, 0);
                        document.getElementById('paginationTop').style = "display: none";
                        document.getElementById('paginationBottom').style = "display: none";
                        document.getElementById('noResults').style = "display: none";
                        console.log(
                            'Set search: Results: ' + response.numResults
                            + '; Pages: ' + response.numPages + '; Page: ' + pageNumber
                        );
                    } else {
                        document.getElementById('setlist').style = "display: table";
                        updateTable(response.filteredSets);
                        window.scrollTo(0, 0);
                        document.getElementById('paginationTop').style = "display: block";
                        document.getElementById('paginationBottom').style = "display: block";
                        document.getElementById('noResults').style = "display: none";
                        var paginationHTML = buildPagination(response.numPages, pageNumber, setsPerPage);
                        document.getElementById('paginationTop').innerHTML = paginationHTML;
                        document.getElementById('paginationBottom').innerHTML = paginationHTML;
                        console.log(
                            'Set search: Results: ' + response.numResults
                            + '; Pages: ' + response.numPages + '; Page: ' + pageNumber
                        );
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    var loadingEl = document.getElementById('setsLoading');
                    if (loadingEl) {
                        loadingEl.style.display = 'none';
                    }
                    console.error('Set search: AJAX error: ' + textStatus + ' - ' + errorThrown);
                }
            });
        }

        function loadPage(pageNumber, setsPerPage) {
            var filterValue = document.getElementById('setCodeFilter').value;
            fetchAndDisplaySets(filterValue, pageNumber, setsPerPage, { updateHistory: true });
        }

        var debounceTimer;

        function filterSets() {
            var filterValue = document.getElementById('setCodeFilter').value;
            var setsPerPage = <?php echo $setsPerPage; ?>;

            clearTimeout(debounceTimer);

            debounceTimer = setTimeout(function() {
                if (filterValue.length >= 3 || filterValue.length === 0) {
                    fetchAndDisplaySets(filterValue, 1, setsPerPage, { updateHistory: true });
                }
            }, 300);
        }

        function updateTable(filteredSets) {
            var table = document.querySelector('#setlist');
            var tableBody = table.getElementsByTagName('tbody')[0];
            var currentYear = '';
            var totalColumns = isAdmin ? 8 : 7;

            while (tableBody.rows.length > 1) {
                tableBody.deleteRow(1);
            }

            filteredSets.forEach(function (set) {
                var setYear = new Date(set.setdate).getFullYear().toString();
                if (setYear != currentYear) {
                    var yearRow = tableBody.insertRow(tableBody.rows.length);
                    var yearCell = yearRow.insertCell(0);
                    yearCell.colSpan = totalColumns;
                    yearCell.className = 'year-header';
                    yearCell.innerHTML = '<h3>' + setYear + '</h3>';
                    currentYear = setYear;
                }

                var row = tableBody.insertRow(tableBody.rows.length);
                var iconCell = row.insertCell(0);
                var setcode = set.setcode;
                var time = new Date().getTime();
                var img = document.createElement('img');
                img.className = 'seticon';
                img.src = 'cardimg/seticons/' + setcode + '.svg?' + time;
                img.alt = setcode.toUpperCase();
                iconCell.appendChild(img);

                var codeCell = row.insertCell(1);
                var setcodeupper = set.setcode.toUpperCase();
                var link = document.createElement('a');
                link.href = 'index.php?complex=yes&searchname=yes&legal=any&set%5B%5D='
                    + encodeURIComponent(setcodeupper) + '&sortBy=setdown&layout=grid';
                link.textContent = setcodeupper;
                codeCell.appendChild(link);

                var nameCell = row.insertCell(2);
                nameCell.textContent = set.set_name;

                var typeCell = row.insertCell(3);
                var setType = set.set_type.split('_').map(function(word) {
                    return word.charAt(0).toUpperCase() + word.slice(1);
                    }).join(' ');
                setType = setType.replace(/_/g, ' ');
                typeCell.textContent = setType;
                typeCell.classList.add('columnhide');

                var parentCell = row.insertCell(4);
                parentCell.textContent = set.parent_set_code.toUpperCase();
                parentCell.classList.add('columnhide');

                var dateCell = row.insertCell(5);
                var inputDate = set.setdate;

                var dateComponents = inputDate.split('-');
                var year = dateComponents[0];
                var month = dateComponents[1];
                var day = parseInt(dateComponents[2]);

                var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

                dateCell.textContent = monthNames[parseInt(month) - 1] + ' ' + day;

                var countCell = row.insertCell(6);
                countCell.textContent = set.card_count.toLocaleString();
                countCell.classList.add('columnhide');

                if (isAdmin) {
                    var reloadCell = row.insertCell(7);
                    reloadCell.style.textAlign = 'center';
                    populateReloadImagesCell(reloadCell, set.setcode);
                }
            });
        }

        $(document).ready(function() {
            $('#setCodeFilter').focus();
            var initialState = getStateFromUrl();
            var setsPerPage = <?php echo $setsPerPage; ?>;
            if (initialState.filter) {
                $('#setCodeFilter').val(initialState.filter);
            }
            if (initialState.page > 1 || initialState.filter) {
                fetchAndDisplaySets(
                    initialState.filter,
                    initialState.page,
                    setsPerPage,
                    { updateHistory: true, replaceHistory: true }
                );
            } else {
                updateHistoryState(currentPage, currentFilter, true);
            }
            document.querySelectorAll('[data-set-image-reload]').forEach(function(cell) {
                populateReloadImagesCell(cell, cell.dataset.setImageReload);
            });
            window.addEventListener('popstate', function(e) {
                var state = e.state || getStateFromUrl();
                var pageNumber = state.page || 1;
                var filterValue = normalizeFilter(state.filter || '');
                $('#setCodeFilter').val(filterValue);
                console.debug('[DEBUG]', 'Sets pagination state restored', state);
                fetchAndDisplaySets(filterValue, pageNumber, setsPerPage, { updateHistory: false });
            });
        });
    </script>
</head>

<body class="body">
<?php
include_once APP_ROOT . '/includes/analyticstracking.php';
require APP_ROOT . '/includes/overlays.php';
require APP_ROOT . '/includes/header.php';
require APP_ROOT . '/includes/menu.php';
?>

<div id='page'>
    <div class='staticpagecontent'>
        <?php
        $sql = "SELECT 
                    name as set_name,
                    code as setcode,
                    parent_set_code,
                    set_type,
                    card_count,
                    nonfoil_only,
                    foil_only,
                    min(release_date) as date,
                    release_date as setdate
                FROM sets 
                GROUP BY 
                    name
                ORDER BY 
                    setdate DESC, length(setcode) ASC, length(parent_set_code) ASC, parent_set_code DESC, setcode ASC
                LIMIT ?";
        $msg->logMessage('[DEBUG]', "Query is: $sql");
        $stmt = $db->prepare($sql);
        if ($stmt === false) :
            throw new Exception(
                '[ERROR] ' . basename(__FILE__) . ' ' . __LINE__ . ', Preparing SQL: ' . $db->error
            );
        endif;
        $stmt->bind_param('i', $setsPerPage);
        $exec = $stmt->execute();
        if ($exec === false) :
            throw new Exception(
                '[ERROR] ' . basename(__FILE__) . ' ' . __LINE__ . ', Executing SQL: ' . $db->error
            );
        else :
            $result = $stmt->get_result();
            if ($result->num_rows === 0) :
                $msg->logMessage('[NOTICE]', 'sets.php: No sets found to display');
            endif;
        endif;
        ?>
        <div class="sets-header-container">
            <h2 class='h2pad sets-header'>Sets</h2>
            <div class="filter-container">
                <input type="text" class="textinput" id="setCodeFilter"
                    oninput="filterSets(this.value, <?php echo $setsPerPage; ?>)"
                    placeholder="NAME/CODE/YEAR FILTER">
                <div id='cancelsetfilter'><span class="material-symbols-outlined">close</span></div>
            </div>
        </div> <?php
        $initialPaginationDisplay = $useAjaxInitialLoad ? 'none' : 'block';
        $initialTableDisplay = $useAjaxInitialLoad ? 'none' : 'table';
        $initialLoadingDisplay = $useAjaxInitialLoad ? 'block' : 'none';
        echo "<div id=\"setsLoading\" style=\"display: {$initialLoadingDisplay};\">"
            . "<span class=\"material-symbols-outlined\">sync</span> Loading sets..."
            . "</div>";
        echo '<div id="paginationTop" class="pagination" style="display: ' . $initialPaginationDisplay
            . ';"><br>Page &nbsp;';
        for ($i = 1; $i <= $totalPages; $i++) :
            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) :
                if ($i == $page) :
                    echo '<span class="pagination-item" style="font-weight: bold">' . $i . '&nbsp;&nbsp;</span>';
                else :
                    echo '<a class="pagination-item" href="javascript:loadPage(' . $i . ', '
                        . $setsPerPage . ');">' . $i . '&nbsp;&nbsp;</a>';
                endif;
            elseif ($i === $page - $range - 1 || $i === $page + $range + 1) :
                echo '<span class="pagination-item">...&nbsp;</span>';
            endif;
        endfor;
        echo '<a class="pagination-item" href="javascript:loadPage(' . ($page + 1) . ', ' . $setsPerPage
            . ');"><span class="material-symbols-outlined set-page pagination-item">skip_next</span></a>';
        echo '<br>&nbsp;</div>'; ?>
        <table id='setlist' style="display: <?php echo $initialTableDisplay; ?>;">
            <tr>
                <td class='setcell'>
                    <b>Icon</b>
                </td>
                <td class='setcell'>
                    <b>Code</b>
                </td>
                <td class='setcell'>
                    <b>Name</b>
                </td>
                <td class='setcell columnhide'>
                    <b>Type</b>
                </td>
                <td class='setcell columnhide'>
                    <b>Parent set</b>
                </td>
                <td class='setcell'>
                    <b>Release date</b>
                </td>
                <td class='setcell columnhide'>
                    <b>Card count</b>
                </td>
                <?php if ($admin == 1) : ?>
                    <td class='setcell'>
                        <b>Reload images</b>
                    </td>
                <?php endif; ?>
            </tr>
            <?php
            $currentYear = null;
            if ($result === false) :
                $msg->logMessage('[ERROR]', 'Error retrieving data'); ?>
                <tr>
                    <td colspan="2">Error retrieving data</td>
                </tr> <?php
            else :
                while ($row = $result->fetch_assoc()) :
                    $setYear = date('Y', strtotime($row['setdate']));
                    if (isset($row['setcode']) && $row['setcode'] !== null) :
                        $setcodeupper = strtoupper($row['setcode']);
                    else :
                        $setcodeupper = '';
                    endif;
                    $setcodeEsc = htmlspecialchars((string) ($row['setcode'] ?? ''), ENT_QUOTES, 'UTF-8');
                    if (isset($row['set_name']) && $row['set_name'] !== null) :
                        $setname = $row['set_name'];
                    else :
                        $setname = '';
                    endif;
                    if (isset($row['set_type']) && $row['set_type'] !== null) :
                        $settype = ucwords(str_replace('_', ' ', $row['set_type']));
                    else :
                        $settype = '';
                    endif;
                    if (isset($row['parent_set_code']) && $row['parent_set_code'] !== null) :
                        $parentsetcode = strtoupper($row['parent_set_code']);
                    else :
                        $parentsetcode = '';
                    endif;
                    if (isset($row['setdate']) && $row['setdate'] !== null) :
                        $setdate = strtoupper($row['setdate']);
                    else :
                        $setdate = '';
                    endif;
                    if (isset($row['card_count']) && $row['card_count'] !== null) :
                        $cardcount = strtoupper($row['card_count']);
                    else :
                        $cardcount = '';
                    endif;
                    if ($setYear != $currentYear) :
                        echo '<tr>';
                        if ($admin == 1) :
                            echo '<td colspan="8" class="year-header"><h3>' . $setYear . '</h3></td>';
                        else :
                            echo '<td colspan="7" class="year-header"><h3>' . $setYear . '</h3></td>';
                        endif;
                        echo '</tr>';
                        $currentYear = $setYear;
                    endif;

                    ?>
                    <tr>
                        <td class='setcell'>
                            <?php
                            $time = time();
                            echo "<img class='seticon' src='cardimg/seticons/{$row['setcode']}.svg?$time'"
                                . " alt='$setcodeupper'>"; ?>
                        </td>
                        <td class='setcell'>
                            <?php echo "<a href='index.php?complex=yes&amp;searchname=yes&amp;legal=any&amp;set%5B%5D="
                                . "$setcodeupper&amp;sortBy=setdown&amp;layout=grid'>$setcodeupper</a>"; ?>
                        </td>
                        <td class='setcell'>
                            <?php echo $setname; ?>
                        </td>
                        <td class='setcell columnhide'>
                            <?php echo $settype; ?>
                        </td>
                        <td class='setcell columnhide'>
                            <?php echo $parentsetcode; ?>
                        </td>
                        <td class='setcell'>
                            <?php echo date('M j', strtotime($setdate)); ?>
                        </td>
                        <td class='setcell columnhide' style='text-align: center;'>
                            <?php echo number_format($cardcount); ?>
                        </td>
                        <?php if ($admin == 1) : ?>
                        <td
                            class='setcell'
                            style='text-align: center;'
                            data-set-image-reload='<?php echo $setcodeEsc; ?>'
                        ></td>
                        <?php endif; ?>
                    </tr>
                    <?php
                endwhile;
            endif;
            ?>
        </table> <?php
        echo '<div id="noResults" style="display:none"><br>No results<br></div>';
        echo '<div id="paginationBottom" class="pagination" style="display: ' . $initialPaginationDisplay
            . ';"><br>Page &nbsp;';
        for ($i = 1; $i <= $totalPages; $i++) :
            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) :
                if ($i == $page) :
                    echo '<span class="pagination-item" style="font-weight: bold">' . $i . '&nbsp;&nbsp;</span>';
                else :
                    echo '<a class="pagination-item" href="javascript:loadPage(' . $i . ', '
                        . $setsPerPage . ');">' . $i . '&nbsp;&nbsp;</a>';
                endif;
            elseif ($i === $page - $range - 1 || $i === $page + $range + 1) :
                echo '<span class="pagination-item">...&nbsp;</span>';
            endif;
        endfor;
        echo '<a class="pagination-item" href="javascript:loadPage(' . ($page + 1) . ', ' . $setsPerPage
            . ');"><span class="material-symbols-outlined set-page pagination-item">skip_next</span></a>';
        echo '</div>';
        ?>
        <br>&nbsp;
    </div>echo '<br>&nbsp;</div>';
</div>
<?php
    require APP_ROOT . '/includes/footer.php';
?>
    <script>
        document.getElementById('cancelsetfilter').addEventListener('click', function() {
            document.getElementById('setCodeFilter').value = '';
            var filterValue = document.getElementById('setCodeFilter').value;
            var setsPerPage = <?php echo $setsPerPage; ?>;
            fetchAndDisplaySets(filterValue, 1, setsPerPage);
        });
    </script>
</body>
</html>
