<?php

use PHPUnit\Framework\TestCase;

function getRealPriceManagerClass(): string
{
    if (class_exists('PriceManagerReal', false)) :
        return 'PriceManagerReal';
    endif;

    $source = file_get_contents(__DIR__ . '/../src/MTG/Cards/PriceManager.php');
    $source = preg_replace('/^<\\?php\\s*/', '', $source, 1);
    $source = preg_replace('/^\\s*namespace\\s+MTG\\\\Cards;\\s*/m', '', $source, 1);
    $source = preg_replace('/class\\s+PriceManager\\b/', 'class PriceManagerReal', $source, 1);
    eval($source);
    return 'PriceManagerReal';
}

class PriceResultStub
{
    public $num_rows;
    private $row;

    public function __construct($numRows, $row)
    {
        $this->num_rows = $numRows;
        $this->row = $row;
    }

    public function fetch_assoc()
    {
        return $this->row;
    }
}

class PriceDbStub
{
    public $error = '';
    private $cardsResult;
    private $jsonResult;

    public function __construct($cardsResult, $jsonResult)
    {
        $this->cardsResult = $cardsResult;
        $this->jsonResult = $jsonResult;
    }

    public function real_escape_string($value)
    {
        return $value;
    }

    public function execute_query($query, $params)
    {
        if (strpos($query, 'FROM cards_scry') !== false) :
            return $this->cardsResult;
        endif;
        if (strpos($query, 'FROM scryfalljson') !== false) :
            return $this->jsonResult;
        endif;
        return false;
    }
}

class PriceManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['max_card_data_age'] = 99999;
    }

    public function testScryfallReturnsNoCardWhenMissing()
    {
        $class = getRealPriceManagerClass();
        $db = new PriceDbStub(new PriceResultStub(0, []), new PriceResultStub(0, []));
        $manager = new $class($db, $GLOBALS['logfile'], 'user@example.com');

        $result = $manager->scryfall('missing-id');

        $this->assertSame('nocard', $result['action']);
    }

    public function testScryfallReadReturnsExistingUri()
    {
        $class = getRealPriceManagerClass();
        $cardsResult = new PriceResultStub(1, ['id' => 'card-id']);
        $jsonResult = new PriceResultStub(1, [
            'jsonupdatetime' => time(),
            'tcg_buy_uri' => 'https://example.com'
        ]);
        $db = new PriceDbStub($cardsResult, $jsonResult);
        $manager = new $class($db, $GLOBALS['logfile'], 'user@example.com');

        $result = $manager->scryfall('card-id');

        $this->assertSame('read', $result['action']);
        $this->assertSame('https://example.com', $result['tcg_uri']);
    }
}
