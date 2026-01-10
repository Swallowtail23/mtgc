<?php

/*
Version:     1.3
Date:        23/12/25
Name:        PriceDisplay.php
Purpose:     Build price values and HTML for card detail pricing displays.
Notes:       -
Author:      Simon Wilson
Copyright:   2025 MTG Collection
To do:       -
*/

namespace MTG\Cards;

use MTG\Core\Message;

class PriceDisplay
{
    public static function computePrices($scryfallResult, $row, $cardtypes, $rate, $logfile = null): array
    {
        $msg = null;
        if (!empty($logfile)) :
            $msg = new Message($logfile);
            $msg->logMessage('[DEBUG]', "Building price data for cardtypes '$cardtypes'");
        endif;

        $prices = [
            'normalprice' => false,
            'localnormal' => null,
            'foilprice' => false,
            'localfoil' => null,
            'etchprice' => false,
            'localetched' => null
        ];

        if (
            isset($scryfallResult["price"])
            and $scryfallResult["price"] !== ""
            and $scryfallResult["price"] != 0.00
            and $scryfallResult["price"] !== null
            and str_contains($cardtypes, 'normal')
        ) :
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', "Using Scryfall normal price");
            endif;
            $prices['normalprice'] = number_format($scryfallResult['price'], 2);
            $prices['localnormal'] = number_format(($scryfallResult["price"] * $rate), 2, '.', ',');
        elseif (
            isset($row["price"])
            and $row["price"] !== ""
            and $row["price"] != 0.00
            and str_contains($cardtypes, 'normal')
        ) :
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', "Using database normal price");
            endif;
            $prices['normalprice'] = number_format($row['price'], 2);
            $prices['localnormal'] = number_format(($row["price"] * $rate), 2, '.', ',');
        else :
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', "No normal price");
            endif;
        endif;

        if (
            isset($scryfallResult["price_foil"])
            and $scryfallResult["price_foil"] !== ""
            and $scryfallResult["price_foil"] != 0.00
            and $scryfallResult["price_foil"] !== null
            and str_contains($cardtypes, 'foil')
        ) :
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', "Using Scryfall foil price");
            endif;
            $prices['foilprice'] = number_format($scryfallResult['price_foil'], 2);
            $prices['localfoil'] = number_format(($scryfallResult["price_foil"] * $rate), 2, '.', ',');
        elseif (
            isset($row["price_foil"])
            and $row["price_foil"] !== ""
            and $row["price_foil"] != 0.00
            and str_contains($cardtypes, 'foil')
        ) :
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', "Using database foil price");
            endif;
            $prices['foilprice'] = number_format($row['price_foil'], 2);
            $prices['localfoil'] = number_format(($row["price_foil"] * $rate), 2, '.', ',');
        else :
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', "No foil price");
            endif;
        endif;

        if (
            isset($scryfallResult["price_etched"])
            and $scryfallResult["price_etched"] !== ""
            and $scryfallResult["price_etched"] != 0.00
            and $scryfallResult["price_etched"] !== null
            and str_contains($cardtypes, 'etch')
        ) :
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', "Using Scryfall etched price");
            endif;
            $prices['etchprice'] = number_format($scryfallResult['price_etched'], 2);
            $prices['localetched'] = number_format(($scryfallResult["price_etched"] * $rate), 2, '.', ',');
        elseif (
            isset($row["price_etched"])
            and $row["price_etched"] !== ""
            and $row["price_etched"] != 0.00
            and str_contains($cardtypes, 'etch')
        ) :
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', "Using database etched price");
            endif;
            $prices['etchprice'] = number_format($row['price_etched'], 2);
            $prices['localetched'] = number_format(($row["price_etched"] * $rate), 2, '.', ',');
        else :
            if ($msg instanceof Message) :
                $msg->logMessage('[DEBUG]', "No etched price");
            endif;
        endif;

        return $prices;
    }

    public static function renderTable(array $prices, $fx, $targetCurrency): string
    {
        ob_start();
        if ($prices['normalprice'] === false and $prices['foilprice'] === false and $prices['etchprice'] === false) :
            ?>
            <table id='tcgplayer' width="100%">
                <tr>
                    <td colspan="2" class="buycellleft">
                        No prices available <br>
                    </td>
                </tr>
            </table>
            <?php
        else :
            ?>
            <table id='tcgplayer' width="100%">
                <tr>
                    <td>
                        <b>Price</b>
                    </td>
                    <td>
                        <b>USD
                            <?php
                            if ($fx === true) :
                                echo "($targetCurrency)";
                            endif;
                            ?>
                        </b>
                    </td>
                </tr>
                <?php if ($prices['normalprice'] !== false) : ?>
                <tr>
                    <td class="buycellleft">
                        Normal
                    </td>
                    <td class="buycell mid">
                        <?php
                        echo ($fx === true)
                            ? $prices['normalprice'] . " ({$prices['localnormal']})"
                            : $prices['normalprice'];
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($prices['foilprice'] !== false) : ?>
                <tr>
                    <td class="buycellleft">
                        Foil
                    </td>
                    <td class="buycell mid">
                        <?php
                        echo ($fx === true)
                            ? $prices['foilprice'] . " ({$prices['localfoil']})"
                            : $prices['foilprice'];
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($prices['etchprice'] !== false) : ?>
                <tr>
                    <td class="buycellleft">
                        Etched
                    </td>
                    <td class="buycell mid">
                        <?php
                        echo ($fx === true)
                            ? $prices['etchprice'] . " ({$prices['localetched']})"
                            : $prices['etchprice'];
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <?php
        endif;

        return ob_get_clean();
    }

    public static function buildAjaxResponse($priceHtml, $tcgLink): array
    {
        return [
            'success' => true,
            'price_html' => $priceHtml,
            'tcg_link' => $tcgLink
        ];
    }
}
