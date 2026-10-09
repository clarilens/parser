<?php

declare(strict_types=1);

use App\Packages\ProductCatalog\Domain\Offer;
use App\Packages\ProductCatalog\Domain\Type\Availability;
use App\Packages\ProductCatalog\Domain\Type\OfferDetails;
use App\Packages\ProductCatalog\Domain\Type\OfferId;
use App\Packages\ProductCatalog\Domain\Type\OfferTerms;
use App\Packages\ProductCatalog\Domain\Type\ProductId;
use App\Packages\ProductCatalog\Domain\Type\Term;
use Clarilens\Parser\Config\DocumentFactConfig;
use Clarilens\Parser\Parser\DocumentFactParser;
use Lib\Blueprint\Core\Domain\Type\Currency;
use Lib\Blueprint\Core\Domain\Type\InterestRate;
use Lib\Blueprint\Core\Domain\Type\InternalId;

$banksAutoload = $argv[1] ?? '';
if (!is_file($banksAutoload)) {
    throw new RuntimeException('Pass Banks backend/vendor/autoload.php as the first argument.');
}
require $banksAutoload;
require dirname(__DIR__, 2) . '/vendor/autoload.php';

$at = new DateTimeImmutable('2026-10-09T00:00:00Z');
$config = new DocumentFactConfig(['version' => 1, 'fields' => [[
    'id' => 'annualRate', 'selector' => '#credit p:first-child', 'pattern' => '/(?<value>\d+,\d+)\s*%/u',
    'type' => 'number', 'required' => true, 'transforms' => [['op' => 'number', 'decimal' => ',']],
]]]);
$batch = (new DocumentFactParser())->parse($config, file_get_contents(dirname(__DIR__) . '/Fixtures/declarative/text-credit.html'), 'credit', 'https://example.org/credit', $at);
if (!$batch->complete || count($batch->facts) !== 1) {
    throw new RuntimeException('Universal credit batch is incomplete.');
}
$terms = new OfferTerms('Credit', '', 'https://example.org/credit', new Currency('AMD'), new InterestRate($batch->facts[0]->value), new Term(null, 36), null, new OfferDetails());
$offer = new Offer(new OfferId(hash('sha256', 'credit-offer')), new ProductId(hash('sha256', 'credit-product')), new InternalId(), $terms, Availability::Available, Availability::Available, $at, $at);
if ($offer->terms->annualRate?->value !== '12.5') {
    throw new RuntimeException('Banks offer mapping failed.');
}
echo "Banks Offer mapping passed\n";
